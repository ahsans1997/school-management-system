<?php

namespace App\Actions\Subject;

use App\Models\Subject;

class CreateSubjectAction
{
    /**
     * @param  array{name: string, code?: ?string, type?: ?string}  $data
     */
    public function execute(array $data): Subject
    {
        return Subject::create($data);
    }
}
