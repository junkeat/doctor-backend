<?php

namespace Database\Seeders;

use App\Enums\ConsultationStatus;
use App\Enums\ConsultationType;
use App\Models\Consultation;
use App\Models\Doctor;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ConsultationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timezone = 'Asia/Kuala_Lumpur';
        $patient = Patient::query()->where('email', 'john@example.com')->firstOrFail();
        $consultations = [
            [
                'doctor' => 'Dr. Sarah Lim',
                'type' => ConsultationType::Chat,
                'scheduled_at' => CarbonImmutable::today($timezone)->setTime(15, 30),
                'status' => ConsultationStatus::Scheduled,
            ],
            [
                'doctor' => 'Dr. Aiman Rashid',
                'type' => ConsultationType::Video,
                'scheduled_at' => CarbonImmutable::create(2026, 9, 8, 10, 0, 0, $timezone),
                'status' => ConsultationStatus::Completed,
            ],
            [
                'doctor' => 'Dr. Priya Nair',
                'type' => ConsultationType::Chat,
                'scheduled_at' => CarbonImmutable::create(2026, 9, 2, 14, 0, 0, $timezone),
                'status' => ConsultationStatus::Completed,
            ],
        ];

        foreach ($consultations as $consultation) {
            $doctor = Doctor::query()
                ->where('name', $consultation['doctor'])
                ->firstOrFail();

            Consultation::query()->updateOrCreate(
                [
                    'doctor_id' => $doctor->id,
                    'scheduled_at' => $consultation['scheduled_at'],
                ],
                [
                    'patient_id' => $patient->id,
                    'type' => $consultation['type'],
                    'reason' => 'General consultation',
                    'status' => $consultation['status'],
                ],
            );
        }
    }
}
