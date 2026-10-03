<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission:settings.manage.
    }

    /**
     * Partial update: every field is optional.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Identity
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'eiin' => ['sometimes', 'nullable', 'string', 'max:50'],
            'school_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'established_year' => ['sometimes', 'nullable', 'integer', 'between:1800,'.date('Y')],
            'principal_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'socials' => ['sometimes', 'nullable', 'array'],
            'socials.*' => ['nullable', 'url', 'max:255'],

            // System
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'date_format' => ['sometimes', 'string', 'max:20'],
            'language' => ['sometimes', 'string', 'max:5'],

            // Secret groups — merged over existing values; omitted keys are kept.
            'email_settings' => ['sometimes', 'array'],
            'email_settings.*' => ['nullable', 'string', 'max:255'],
            'sms_settings' => ['sometimes', 'array'],
            'sms_settings.*' => ['nullable', 'string', 'max:255'],
            'payment_settings' => ['sometimes', 'array'],
            'payment_settings.*' => ['nullable', 'string', 'max:255'],

            // The current academic year is managed via /academic-years/{id}/set-current.
        ];
    }
}
