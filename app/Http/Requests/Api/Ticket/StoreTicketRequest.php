<?php

namespace App\Http\Requests\Api\Ticket;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority'    => ['required', Rule::enum(TicketPriority::class)],
            'category'    => ['required', 'string', Rule::in(['device_assignment', 'incident', 'control'])],
            'device_id'   => ['nullable', 'integer', 'exists:devices,id'],
        ];
    }
}
