<?php

namespace App\Http\Requests\Ntf;

use App\Models\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validTypes = array_keys(NotificationPreference::NOTIFICATION_TYPES);

        return [
            // Format 1: Single item update
            'notif_type' => ['required_without_all:preferences,topic', 'nullable', 'string', Rule::in($validTypes)],
            'topic' => ['required_without_all:preferences,notif_type', 'nullable', 'string', Rule::in($validTypes)],
            'channel_push' => ['nullable', 'boolean'],
            'channel_wa' => ['nullable', 'boolean'],
            'channel_email' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],

            // Format 2: Batch / bulk update
            'preferences' => ['nullable', 'array'],
            'preferences.*.notif_type' => ['required_without:preferences.*.topic', 'nullable', 'string', Rule::in($validTypes)],
            'preferences.*.topic' => ['required_without:preferences.*.notif_type', 'nullable', 'string', Rule::in($validTypes)],
            'preferences.*.channel_push' => ['nullable', 'boolean'],
            'preferences.*.channel_wa' => ['nullable', 'boolean'],
            'preferences.*.channel_email' => ['nullable', 'boolean'],
            'preferences.*.is_enabled' => ['nullable', 'boolean'],
            'preferences.*.enabled' => ['nullable', 'boolean'],
        ];
    }
}
