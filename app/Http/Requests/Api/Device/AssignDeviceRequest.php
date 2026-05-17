<?php

namespace App\Http\Requests\Api\Device;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeviceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'user_id'   => ['required', 'integer', 'exists:users,id'],
            'notes'     => ['nullable', 'string', 'max:500'],
        ];
    }
}
