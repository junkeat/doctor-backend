<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'specialty' => fake()->randomElement([
                'Cardiology',
                'Dermatology',
                'Family Medicine',
                'Neurology',
                'Pediatrics',
            ]),
            'photo_url' => fake()->imageUrl(),
            'available_from' => '09:00:00',
            'available_to' => '17:00:00',
            'is_active' => true,
        ];
    }
}
