<?php

namespace App\Http\Requests\Api;

use App\Enums\ConsultationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConsultationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(ConsultationStatus::class)],
        ];
    }
}
