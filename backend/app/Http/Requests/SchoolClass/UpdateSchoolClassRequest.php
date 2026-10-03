<?php

namespace App\Http\Requests\SchoolClass;

use App\Models\SchoolClass;

class UpdateSchoolClassRequest extends SchoolClassRequest
{
    protected function ignoredClass(): ?SchoolClass
    {
        return $this->route('school_class');
    }
}
