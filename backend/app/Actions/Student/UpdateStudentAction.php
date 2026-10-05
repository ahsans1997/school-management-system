<?php

namespace App\Actions\Student;

use App\Models\Student;

class UpdateStudentAction
{
    /**
     * @param  array<string, mixed>  $data  validated StudentRequest fields
     */
    public function execute(Student $student, array $data): Student
    {
        $student->update($data);

        return $student;
    }
}
