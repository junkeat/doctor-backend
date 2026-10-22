<?php

namespace Database\Seeders;

use App\Models\Doctor;
use Illuminate\Database\Seeder;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $doctors = [
            ['name' => 'Dr. Sarah Lim', 'specialty' => 'General Practitioner'],
            ['name' => 'Dr. Aiman Rashid', 'specialty' => 'Family Medicine'],
            ['name' => 'Dr. Priya Nair', 'specialty' => 'Internal Medicine'],
        ];

        foreach ($doctors as $doctor) {
            Doctor::query()->updateOrCreate(
                ['name' => $doctor['name']],
                [
                    'specialty' => $doctor['specialty'],
                    'available_from' => '09:00:00',
                    'available_to' => '22:00:00',
                    'is_active' => true,
                ],
            );
        }
    }
}
