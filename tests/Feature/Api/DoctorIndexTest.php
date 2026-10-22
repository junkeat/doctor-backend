<?php

namespace Tests\Feature\Api;

use App\Models\Doctor;
use Database\Seeders\DoctorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_seeded_active_doctors_with_availability(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
        $this->seed(DoctorSeeder::class);
        Doctor::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/doctors');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Dr. Sarah Lim')
            ->assertJsonPath('data.0.available_now', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'specialty',
                        'photo_url',
                        'available_from',
                        'available_to',
                        'available_now',
                    ],
                ],
            ]);
    }

    public function test_allows_cors_requests_from_the_configured_frontend_origin(): void
    {
        $response = $this->options('/api/doctors', [], [
            'Origin' => 'http://localhost:4200',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:4200');
    }
}
