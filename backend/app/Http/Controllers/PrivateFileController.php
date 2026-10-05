<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private files (photos, documents) through expiring signed links
 * (see App\Support\SignedFileUrl). No bearer token: the signature is the
 * credential, and only callers who may view the record receive it.
 */
class PrivateFileController extends Controller
{
    /** Never let a stored file run as a page, and keep it out of shared caches. */
    private const HEADERS = [
        'Cache-Control' => 'private, max-age=3600',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
    ];

    public function studentPhoto(Student $student): StreamedResponse
    {
        return $this->photo($student->photo_path);
    }

    public function teacherPhoto(Teacher $teacher): StreamedResponse
    {
        return $this->photo($teacher->photo_path);
    }

    public function document(Document $document): StreamedResponse
    {
        return Storage::disk('local')->download($document->path, $document->original_name, self::HEADERS);
    }

    private function photo(?string $path): StreamedResponse
    {
        abort_if($path === null, 404);

        return Storage::disk('local')->response($path, null, self::HEADERS);
    }
}
