<?php

namespace App\Actions\Files;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class DeleteDocumentAction
{
    public function execute(Document $document): void
    {
        $document->delete();
        Storage::disk('local')->delete($document->path);
    }
}
