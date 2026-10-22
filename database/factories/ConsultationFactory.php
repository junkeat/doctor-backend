<?php

namespace Database\Factories;

use App\Enums\ConsultationType;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'type' => fake()->randomElement(ConsultationType::cases())->value,
            'scheduled_at' => fake()->dateTimeBetween('now', '+1 month'),
            'reason' => fake()->sentence(),
            'status' => 'scheduled',
        ];
    }
}
