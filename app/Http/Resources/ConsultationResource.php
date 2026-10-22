<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'scheduled_at' => $this->scheduled_at,
            'reason' => $this->reason,
            'status' => $this->status->value,
            'doctor' => [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
                'specialty' => $this->doctor->specialty,
            ],
        ];
    }
}
