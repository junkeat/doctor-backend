<?php

namespace App\Models;

use App\Enums\ConsultationStatus;
use App\Enums\ConsultationType;
use Database\Factories\ConsultationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    /** @use HasFactory<ConsultationFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'type',
        'scheduled_at',
        'reason',
        'status',
    ];

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConsultationType::class,
            'status' => ConsultationStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }
}
