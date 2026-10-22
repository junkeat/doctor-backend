<?php

namespace App\Models;

use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'specialty',
        'photo_url',
        'available_from',
        'available_to',
        'is_active',
    ];

    protected $appends = ['available_now'];

    /**
     * @return HasMany<Consultation, $this>
     */
    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    protected function availableNow(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                if (! $this->is_active) {
                    return false;
                }

                $currentTime = now()->format('H:i:s');
                $availableFrom = (string) $this->available_from;
                $availableTo = (string) $this->available_to;

                if ($availableFrom <= $availableTo) {
                    return $currentTime >= $availableFrom && $currentTime <= $availableTo;
                }

                return $currentTime >= $availableFrom || $currentTime <= $availableTo;
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
