<?php

namespace App\Actions\Student;

use App\Models\StudentDocument;
use Illuminate\Support\Facades\Storage;

class DeleteStudentDocumentAction
{
    public function execute(StudentDocument $document): void
    {
        $document->delete();
        Storage::disk('local')->delete($document->path);
    }
}
