<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /**
     * Extension AND sniffed content type must both be an image (spec §51).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }
}
