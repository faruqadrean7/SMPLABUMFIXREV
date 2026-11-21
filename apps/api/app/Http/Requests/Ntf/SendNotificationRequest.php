<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['nullable', 'uuid', 'exists:notification_templates,id'],
            'template_code' => ['nullable', 'string', 'exists:notification_templates,code'],
            'channel' => ['nullable', 'string', 'in:push,wa,whatsapp,email'],
            'recipient' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'parameters' => ['nullable', 'array'],
            'params' => ['nullable', 'array'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ];
    }
}
