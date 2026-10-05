<?php

namespace App\Http\Resources;

use App\Models\StudentDocument;
use App\Support\SignedFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentDocument */
class StudentDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'download_url' => SignedFileUrl::to('files.student-document', ['document' => $this->id]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
