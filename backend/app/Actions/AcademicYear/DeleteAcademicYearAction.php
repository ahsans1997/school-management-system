<?php

namespace App\Actions\AcademicYear;

use App\Models\AcademicYear;
use App\Support\GuardedDelete;

class DeleteAcademicYearAction
{
    /**
     * Only an unused, non-current year may be deleted. "In use" is enforced
     * by FK RESTRICT on every referencing table (see GuardedDelete).
     */
    public function execute(AcademicYear $year): void
    {
        if ($year->is_current) {
            abort(409, 'The current academic year cannot be deleted. Set another year as current first.');
        }

        GuardedDelete::delete(
            $year,
            'This academic year is in use (for example by classes or enrollments) and cannot be deleted.',
        );

        AcademicYear::forgetCurrentCache();
    }
}
