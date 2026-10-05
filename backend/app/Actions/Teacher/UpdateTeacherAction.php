<?php

namespace App\Actions\Teacher;

use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class UpdateTeacherAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Teacher $teacher, array $data): Teacher
    {
        return DB::transaction(function () use ($teacher, $data) {
            $teacher->update($data);

            // The login's display name follows the teacher's.
            $teacher->account?->update(['name' => $teacher->name]);

            return $teacher;
        });
    }
}
