<?php

namespace App\Http\Requests\Student;

use App\Models\Student;

class UpdateStudentRequest extends StudentRequest
{
    protected function student(): ?Student
    {
        return $this->route('student');
    }
}
