<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'photo_url' => $this->photo_url,
            'available_from' => $this->available_from,
            'available_to' => $this->available_to,
            'available_now' => $this->available_now,
        ];
    }
}
