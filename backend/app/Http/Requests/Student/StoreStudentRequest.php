<?php

namespace App\Http\Requests\Student;

use App\Models\Student;

class StoreStudentRequest extends StudentRequest
{
    protected function student(): ?Student
    {
        return null;
    }
}
