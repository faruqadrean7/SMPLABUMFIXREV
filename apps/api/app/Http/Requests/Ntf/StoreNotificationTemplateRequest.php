<?php

namespace App\Http\Requests\Ntf;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'max:100', 'unique:notification_templates,code'],
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', 'string', 'in:push,wa,whatsapp,email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'template_text' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
