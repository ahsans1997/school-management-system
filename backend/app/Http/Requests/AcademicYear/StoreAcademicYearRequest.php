<?php

namespace App\Http\Requests\AcademicYear;

use App\Models\AcademicYear;

class StoreAcademicYearRequest extends AcademicYearRequest
{
    protected function ignoredYear(): ?AcademicYear
    {
        return null;
    }
}
