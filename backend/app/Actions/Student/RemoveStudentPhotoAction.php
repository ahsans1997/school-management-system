<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;

class RemoveStudentPhotoAction
{
    public function execute(Student $student): Student
    {
        if ($student->photo_path !== null) {
            Storage::disk('local')->delete($student->photo_path);
            $student->forceFill(['photo_path' => null])->save();
        }

        return $student;
    }
}
