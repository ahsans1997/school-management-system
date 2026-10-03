<?php

namespace App\Actions\AcademicYear;

use App\Models\AcademicYear;

class UpdateAcademicYearAction
{
    /**
     * @param  array{name: string, start_date: string, end_date: string}  $data
     */
    public function execute(AcademicYear $year, array $data): AcademicYear
    {
        $year->update([
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ]);

        // The current year's name/dates may have changed.
        AcademicYear::forgetCurrentCache();

        return $year;
    }
}
