<?php

namespace App\Http\Requests\Teacher;

use App\Models\Teacher;

class UpdateTeacherRequest extends TeacherRequest
{
    public function authorize(): bool
    {
        return true; // Gated by permission: middleware.
    }

    protected function teacher(): ?Teacher
    {
        return $this->route('teacher');
    }
}
