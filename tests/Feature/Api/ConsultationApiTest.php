<?php

namespace Tests\Feature\Api;

use App\Enums\ConsultationStatus;
use App\Enums\ConsultationType;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsultationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_consultation_for_current_patient_and_returns_doctor(): void
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
            ->assertJsonPath('data.patient.id', $john->id)
            ->assertJsonPath('data.patient.name', 'John')
            ->assertJsonPath('data.patient.email', 'john@example.com')
            ->assertJsonPath('data.doctor.id', $doctor->id)
            ->assertJsonPath('data.doctor.name', 'Dr. Sarah Lim')
            ->assertJsonPath('data.doctor.specialty', 'General Practitioner');

        $this->assertDatabaseHas('consultations', [
            'patient_id' => $john->id,
            'doctor_id' => $doctor->id,
            'type' => ConsultationType::Chat->value,
            'status' => ConsultationStatus::Scheduled->value,
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
            'reason' => 'Discuss recurring headaches.',
        ]);
        $this->assertDatabaseCount('consultations', 1);
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
            'patient_id' => $john->id,
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
            'patient_id' => $john->id,
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

    public function test_returns_only_consultations_for_the_current_patient(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $otherPatient = Patient::factory()->create([
            'name' => 'Other Patient',
            'email' => 'other@example.com',
        ]);
        $doctor = $this->createDoctor();

        Consultation::factory()->create([
            'patient_id' => $john->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2)->setTime(10, 0),
            'reason' => 'John appointment.',
        ]);
        Consultation::factory()->create([
            'patient_id' => $otherPatient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'reason' => 'Other appointment.',
        ]);

        $response = $this->getJson('/api/consultations');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'John appointment.')
            ->assertJsonPath('data.0.patient.id', $john->id)
            ->assertJsonPath('data.0.patient.name', 'John')
            ->assertJsonPath('data.0.patient.email', 'john@example.com')
            ->assertJsonPath('data.0.doctor.name', 'Dr. Sarah Lim')
            ->assertJsonPath('data.0.doctor.specialty', 'General Practitioner');
    }

    public function test_filters_consultations_by_completed_status(): void
    {
        $this->freezeApiTime();
        $john = $this->createJohn();
        $doctor = $this->createDoctor();

        Consultation::factory()->create([
            'patient_id' => $john->id,
            'doctor_id' => $doctor->id,
            'status' => ConsultationStatus::Completed,
            'scheduled_at' => now()->subDay(),
            'reason' => 'Completed appointment.',
        ]);
        Consultation::factory()->create([
            'patient_id' => $john->id,
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

    private function createJohn(): Patient
    {
        $patient = Patient::factory()->create([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('patients', 'id'), 1, true)");

        return $patient;
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
