<?php

namespace App\Http\Requests\Api;

use App\Enums\ConsultationType;
use App\Models\Consultation;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'doctor_id' => [
                'required',
                'integer',
                Rule::exists('doctors', 'id')->where('is_active', true),
            ],
            'type' => ['required', Rule::enum(ConsultationType::class)],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.exists' => 'The selected doctor does not exist or is inactive.',
            'type.enum' => 'The consultation type must be chat or video.',
            'scheduled_at.date' => 'The scheduled time must be a valid date.',
            'scheduled_at.after' => 'The scheduled time must be in the future.',
            'reason.max' => 'The reason may not be greater than 500 characters.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['doctor_id', 'scheduled_at'])) {
                    return;
                }

                $scheduledAt = CarbonImmutable::parse(
                    $this->input('scheduled_at'),
                    config('app.timezone'),
                )->setTimezone(config('app.timezone'));

                if (! $scheduledAt->isFuture()) {
                    $validator->errors()->add(
                        'scheduled_at',
                        'The scheduled time must be in the future.',
                    );

                    return;
                }

                $doctor = Doctor::query()->find($this->integer('doctor_id'));

                if (! $doctor || ! $doctor->is_active) {
                    $validator->errors()->add(
                        'doctor_id',
                        'The selected doctor does not exist or is inactive.',
                    );

                    return;
                }

                $scheduledTime = $scheduledAt
                    ->setTimezone(config('app.timezone'))
                    ->format('H:i:s');
                $availableFrom = (string) $doctor->available_from;
                $availableTo = (string) $doctor->available_to;

                $isWithinAvailableHours = $availableFrom <= $availableTo
                    ? $scheduledTime >= $availableFrom && $scheduledTime <= $availableTo
                    : $scheduledTime >= $availableFrom || $scheduledTime <= $availableTo;

                if (! $isWithinAvailableHours) {
                    $validator->errors()->add(
                        'scheduled_at',
                        "The selected time is outside the doctor's available hours.",
                    );

                    return;
                }

                $bookingExists = Consultation::query()
                    ->where('doctor_id', $doctor->id)
                    ->where('scheduled_at', $scheduledAt)
                    ->exists();

                if ($bookingExists) {
                    $validator->errors()->add(
                        'scheduled_at',
                        'This doctor is already booked at the selected time.',
                    );
                }
            },
        ];
    }
}
