<?php

namespace App\Actions\Subject;

use App\Models\Subject;

class UpdateSubjectAction
{
    /**
     * @param  array{name: string, code?: ?string, type?: ?string}  $data
     */
    public function execute(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        return $subject;
    }
}
