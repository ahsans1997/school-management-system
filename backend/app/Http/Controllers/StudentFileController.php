<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves private student files through expiring signed links (see
 * App\Support\SignedFileUrl). No bearer token: the signature is the
 * credential, and only callers who may view the student receive it.
 */
class StudentFileController extends Controller
{
    /** Never let a stored file run as a page, and keep it out of shared caches. */
    private const HEADERS = [
        'Cache-Control' => 'private, max-age=3600',
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
    ];

    public function photo(Student $student): StreamedResponse
    {
        abort_if($student->photo_path === null, 404);

        return Storage::disk('local')->response($student->photo_path, null, self::HEADERS);
    }

    public function document(StudentDocument $document): StreamedResponse
    {
        return Storage::disk('local')->download($document->path, $document->original_name, self::HEADERS);
    }
}
