<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A document upload for any record (students, teachers…). */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    /**
     * PDFs and images only; extension AND sniffed content type must match.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'file' => [
                'required', 'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'mimetypes:application/pdf,image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ];
    }
}
