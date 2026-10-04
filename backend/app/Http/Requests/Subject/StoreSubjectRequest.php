<?php

namespace App\Http\Requests\Subject;

use App\Models\Subject;

class StoreSubjectRequest extends SubjectRequest
{
    protected function ignoredSubject(): ?Subject
    {
        return null;
    }
}
