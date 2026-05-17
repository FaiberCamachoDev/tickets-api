<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'device'      => new DeviceResource($this->whenLoaded('device')),
            'user'        => new UserResource($this->whenLoaded('user')),
            'assigned_at' => $this->assigned_at?->toDateTimeString(),
            'returned_at' => $this->returned_at?->toDateTimeString(),
            'notes'       => $this->notes,
        ];
    }
}
