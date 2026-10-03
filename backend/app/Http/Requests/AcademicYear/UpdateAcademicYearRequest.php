<?php

namespace App\Http\Requests\AcademicYear;

use App\Models\AcademicYear;

class UpdateAcademicYearRequest extends AcademicYearRequest
{
    protected function ignoredYear(): ?AcademicYear
    {
        return $this->route('academic_year');
    }
}
