<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_fingerprint' => ['nullable', 'string', 'max:100'],
            'screen_state' => ['nullable', 'string', 'in:fullscreen,windowed,blur'],
            'active_tab' => ['nullable', 'boolean'],
            'battery_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'client_timestamp' => ['nullable', 'string'],
        ];
    }
}
