<?php

namespace Tests\Feature\Api;

use App\Enums\ConsultationStatus;
use App\Enums\ConsultationType;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsultationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication_to_access_consultations(): void
    {
        $this->getJson('/api/consultations')->assertUnauthorized();
    }

    public function test_creates_consultation_for_current_user_and_returns_doctor(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $doctor = $this->createDoctor();
        $scheduledAt = now()->addDay()->setTime(10, 0);

        $response = $this->postJson('/api/consultations', [
            'doctor_id' => $doctor->id,
            'type' => 'chat',
            'scheduled_at' => $scheduledAt->copy()->utc()->toIso8601String(),
            'reason' => 'Discuss recurring headaches.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'chat')
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.user.id', $john->id)
            ->assertJsonPath('data.user.name', 'John')
            ->assertJsonPath('data.user.email', 'john@example.com')
            ->assertJsonPath('data.doctor.id', $doctor->id)
            ->assertJsonPath('data.doctor.name', 'Dr. Sarah Lim')
            ->assertJsonPath('data.doctor.specialty', 'General Practitioner');

        $this->assertDatabaseHas('consultations', [
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
            'type' => ConsultationType::Chat->value,
            'status' => ConsultationStatus::Scheduled->value,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'reason' => 'Discuss recurring headaches.',
        ]);
        $this->assertDatabaseCount('consultations', 1);
    }

    public function test_uses_authenticated_user_instead_of_a_client_supplied_user_id(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $otherUser = User::factory()->create();
        $doctor = $this->createDoctor();

        $response = $this->postJson('/api/consultations', [
            ...$this->bookingPayload($doctor),
            'user_id' => $otherUser->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.id', $john->id);

        $this->assertDatabaseHas('consultations', [
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
        ]);
        $this->assertDatabaseMissing('consultations', [
            'user_id' => $otherUser->id,
            'doctor_id' => $doctor->id,
        ]);
    }

    public function test_rejects_reason_longer_than_500_characters(): void
    {
        $this->freezeApiTime();
        $this->createJohn();
        $doctor = $this->createDoctor();
        $payload = $this->bookingPayload($doctor);
        $payload['reason'] = str_repeat('a', 501);

        $response = $this->postJson('/api/consultations', $payload);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.reason.0', 'The reason may not be greater than 500 characters.');
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_rejects_inactive_doctor(): void
    {
        $this->freezeApiTime();
        $this->createJohn();
        $doctor = $this->createDoctor();
        $doctor->update(['is_active' => false]);

        $response = $this->postJson(
            '/api/consultations',
            $this->bookingPayload($doctor),
        );

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.doctor_id.0',
                'The selected doctor does not exist or is inactive.',
            );
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_rejects_consultation_type_other_than_chat_or_video(): void
    {
        $this->freezeApiTime();
        $this->createJohn();
        $doctor = $this->createDoctor();
        $payload = $this->bookingPayload($doctor);
        $payload['type'] = 'phone';

        $response = $this->postJson('/api/consultations', $payload);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.type.0',
                'The consultation type must be chat or video.',
            );
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_rejects_consultation_scheduled_in_the_past(): void
    {
        $this->freezeApiTime();
        $this->createJohn();
        $doctor = $this->createDoctor();
        $payload = $this->bookingPayload($doctor);
        $payload['scheduled_at'] = now()->subMinute()->toIso8601String();

        $response = $this->postJson('/api/consultations', $payload);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.scheduled_at.0', 'The scheduled time must be in the future.');
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_rejects_consultation_outside_doctor_available_hours(): void
    {
        $this->freezeApiTime();
        $this->createJohn();
        $doctor = $this->createDoctor();
        $payload = $this->bookingPayload($doctor);
        $payload['scheduled_at'] = now()->addDay()->setTime(8, 0)->toIso8601String();

        $response = $this->postJson('/api/consultations', $payload);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                "The selected time is outside the doctor's available hours.",
            );
        $this->assertDatabaseCount('consultations', 0);
    }

    public function test_rejects_doctor_already_booked_at_the_selected_time(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $doctor = $this->createDoctor();
        $scheduledAt = now()->addDay()->setTime(10, 0);
        Consultation::factory()->create([
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
            'type' => ConsultationType::Chat,
            'scheduled_at' => $scheduledAt,
            'reason' => 'Existing appointment.',
            'status' => ConsultationStatus::Scheduled,
        ]);

        $payload = $this->bookingPayload($doctor);
        $payload['scheduled_at'] = $scheduledAt->toIso8601String();
        $response = $this->postJson('/api/consultations', $payload);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                'This doctor is already booked at the selected time.',
            );
        $this->assertDatabaseCount('consultations', 1);
    }

    public function test_returns_a_validation_error_when_a_booking_race_hits_the_unique_index(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $doctor = $this->createDoctor();
        $scheduledAt = now()->addDay()->setTime(10, 0);
        $racingBooking = [
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
            'type' => ConsultationType::Chat,
            'scheduled_at' => $scheduledAt,
            'reason' => 'Concurrent appointment.',
            'status' => ConsultationStatus::Scheduled,
        ];

        Consultation::creating(function () use ($racingBooking): void {
            Consultation::withoutEvents(
                fn () => Consultation::query()->create($racingBooking),
            );
        });
        $this->beforeApplicationDestroyed(fn () => Consultation::flushEventListeners());

        $response = $this->postJson('/api/consultations', [
            'doctor_id' => $doctor->id,
            'type' => 'chat',
            'scheduled_at' => $scheduledAt->toIso8601String(),
            'reason' => 'Discuss symptoms.',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath(
                'errors.scheduled_at.0',
                'This doctor is already booked at the selected time.',
            );
    }

    public function test_returns_only_consultations_for_the_authenticated_user(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $otherUser = User::factory()->create([
            'name' => 'Other User',
            'email' => 'other@example.com',
        ]);
        $doctor = $this->createDoctor();

        Consultation::factory()->create([
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
            'reason' => 'John appointment.',
        ]);
        Consultation::factory()->create([
            'user_id' => $otherUser->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'reason' => 'Other appointment.',
        ]);

        $response = $this->getJson('/api/consultations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'John appointment.')
            ->assertJsonPath('data.0.user.id', $john->id)
            ->assertJsonPath('data.0.user.name', 'John')
            ->assertJsonPath('data.0.user.email', 'john@example.com')
            ->assertJsonPath('data.0.doctor.name', 'Dr. Sarah Lim')
            ->assertJsonPath('data.0.doctor.specialty', 'General Practitioner');
    }

    public function test_filters_consultations_by_completed_status(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $doctor = $this->createDoctor();

        Consultation::factory()->create([
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
            'status' => ConsultationStatus::Completed,
            'scheduled_at' => now()->subDay(),
            'reason' => 'Completed appointment.',
        ]);
        Consultation::factory()->create([
            'user_id' => $john->id,
            'doctor_id' => $doctor->id,
            'status' => ConsultationStatus::Scheduled,
            'scheduled_at' => now()->addDay(),
            'reason' => 'Upcoming appointment.',
        ]);

        $response = $this->getJson('/api/consultations?status=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.reason', 'Completed appointment.');
    }

    private function freezeApiTime(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    }

    private function createJohn(): User
    {
        $user = User::factory()->create([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), 1, true)");
        Sanctum::actingAs($user);

        return $user;
    }

    private function createDoctor(): Doctor
    {
        return Doctor::factory()->create([
            'name' => 'Dr. Sarah Lim',
            'specialty' => 'General Practitioner',
            'photo_url' => null,
            'available_from' => '09:00:00',
            'available_to' => '22:00:00',
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, int|string>
     */
    private function bookingPayload(Doctor $doctor): array
    {
        return [
            'doctor_id' => $doctor->id,
            'type' => 'chat',
            'scheduled_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'reason' => 'Discuss symptoms.',
        ];
    }
}
