<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status->value,
            'priority'    => $this->priority->value,
            'category'    => $this->category,
            'user'        => new UserResource($this->whenLoaded('user')),
            'device'      => $this->whenLoaded('device', fn () => [
                'id'     => $this->device?->id,
                'name'   => $this->device?->name,
                'type'   => $this->device?->type?->value,
            ]),
            'created_at'  => $this->created_at->toDateTimeString(),
            'updated_at'  => $this->updated_at->toDateTimeString(),
        ];
    }
}
