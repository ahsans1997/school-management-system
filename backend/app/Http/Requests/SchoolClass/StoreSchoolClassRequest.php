<?php

namespace App\Http\Requests\SchoolClass;

use App\Models\SchoolClass;

class StoreSchoolClassRequest extends SchoolClassRequest
{
    protected function ignoredClass(): ?SchoolClass
    {
        return null;
    }
}
