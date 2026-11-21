<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'string', 'in:all,role,class,users'],
            'target_role' => ['nullable', 'required_if:target_type,role', 'string'],
            'target_class_id' => ['nullable', 'required_if:target_type,class', 'uuid', 'exists:classes,id'],
            'user_ids' => ['nullable', 'required_if:target_type,users', 'array'],
            'user_ids.*' => ['uuid', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'notif_type' => ['nullable', 'string', 'max:50'],
            'data' => ['nullable', 'array'],
        ];
    }
}
