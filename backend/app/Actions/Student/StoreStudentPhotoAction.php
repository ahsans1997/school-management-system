<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreStudentPhotoAction
{
    /** Private disk, random filename; the previous photo is removed. */
    public function execute(Student $student, UploadedFile $file): Student
    {
        $previous = $student->photo_path;
        $path = $file->store("students/{$student->id}", 'local');

        $student->forceFill(['photo_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        return $student;
    }
}
