<?php

namespace App\Actions\AcademicYear;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

class CreateAcademicYearAction
{
    /**
     * The first year ever created becomes current automatically, so the
     * "exactly one current year" invariant holds from the start.
     *
     * @param  array{name: string, start_date: string, end_date: string}  $data
     */
    public function execute(array $data): AcademicYear
    {
        $year = DB::transaction(function () use ($data) {
            $isFirst = ! AcademicYear::query()->exists();

            return AcademicYear::create([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_current' => $isFirst,
            ]);
        });

        AcademicYear::forgetCurrentCache();

        return $year;
    }
}
