<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UploadFaviconRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission:settings.manage.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Extension + real MIME checked; max 512 KB.
            'file' => [
                'required', 'file', 'mimes:png,ico',
                'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon',
                'max:512',
            ],
        ];
    }
}
