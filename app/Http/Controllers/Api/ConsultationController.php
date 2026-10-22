<?php

namespace App\Http\Controllers\Api;

use App\Enums\ConsultationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexConsultationsRequest;
use App\Http\Requests\Api\StoreConsultationRequest;
use App\Http\Resources\ConsultationResource;
use App\Models\Consultation;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use App\Models\Patient;

class ConsultationController extends Controller
{
    /**
     * List my consultations.
     */
    public function index(
        IndexConsultationsRequest $request,
        CurrentPatient $currentPatient,
    ): AnonymousResourceCollection {
        $consultations = Consultation::query()
            ->whereBelongsTo($currentPatient->patient())
            ->with([
                'doctor:id,name,specialty',
                'patient:id,name,email',
            ])
            ->when(
                $request->validated('status'),
                fn ($query, string $status) => $query->where(
                    'status',
                    ConsultationStatus::from($status)->value,
                ),
            )
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return ConsultationResource::collection($consultations);
    }

    /**
     * Book a consultation.
     */
    public function store(
        StoreConsultationRequest $request,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $consultation = Consultation::query()->create([
                'patient_id' => Patient::findOrFail($validated['patient_id'])->id,
                'doctor_id' => $validated['doctor_id'],
                'type' => $validated['type'],
                'scheduled_at' => CarbonImmutable::parse(
                    $validated['scheduled_at'],
                    config('app.timezone'),
                )->setTimezone(config('app.timezone')),
                'reason' => $validated['reason'],
                'status' => ConsultationStatus::Scheduled,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isDoctorBookingConflict($exception)) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'scheduled_at' => 'This doctor is already booked at the selected time.',
            ]);
        }

        return (new ConsultationResource($consultation->load([
            'doctor',
            'patient:id,name,email',
        ])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Determine whether the database error is the doctor booking constraint.
     */
    private function isDoctorBookingConflict(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? $exception->getCode();
        $message = $exception->getMessage();

        if ($sqlState === '23505') {
            return str_contains($message, 'consultations_doctor_id_scheduled_at_unique');
        }

        return $sqlState === '23000'
            && str_contains(
                $message,
                'consultations.doctor_id, consultations.scheduled_at',
            );
    }
}
