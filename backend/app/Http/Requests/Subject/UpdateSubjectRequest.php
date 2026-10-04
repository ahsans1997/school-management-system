<?php

namespace App\Http\Requests\Subject;

use App\Models\Subject;

class UpdateSubjectRequest extends SubjectRequest
{
    protected function ignoredSubject(): ?Subject
    {
        return $this->route('subject');
    }
}
