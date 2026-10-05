<?php

namespace App\Actions\Files;

use App\Models\Concerns\HasDocuments;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class StoreDocumentAction
{
    /**
     * Private disk, random filename; the original name is kept only as metadata.
     *
     * @param  Model&HasDocuments  $owner
     */
    public function execute(Model $owner, string $title, UploadedFile $file, User $uploader): Document
    {
        $path = $file->store($owner->fileFolder().'/documents', 'local');

        return $owner->documents()->create([
            'title' => $title,
            'path' => $path,
            'original_name' => self::safeName($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType(), // sniffed from content, not the client's claim
            'size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);
    }

    /**
     * The client's filename, made safe to send back in Content-Disposition.
     * Symfony already drops any path before the last "/" or "\"; this also
     * removes control characters (e.g. newlines) and bounds the length.
     */
    private static function safeName(string $name): string
    {
        $name = trim(preg_replace('/[\/\\\\\x00-\x1F\x7F]+/', '_', $name) ?? '');

        return mb_substr($name === '' ? 'document' : $name, -200);
    }
}
