<?php

namespace App\Http\Requests\Api\Ticket;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status'      => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority'    => ['sometimes', Rule::enum(TicketPriority::class)],
            'category'    => ['sometimes', 'string', Rule::in(['device_assignment', 'incident', 'control'])],
            'device_id'   => ['sometimes', 'nullable', 'integer', 'exists:devices,id'],
        ];
    }
}
