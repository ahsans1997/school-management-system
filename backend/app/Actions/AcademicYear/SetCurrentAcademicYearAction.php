<?php

namespace App\Actions\AcademicYear;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

class SetCurrentAcademicYearAction
{
    /**
     * Atomically make $year the single current year.
     *
     * Two statements, in this order, inside one transaction: clear the old
     * current year first, then set the new one — so the unique index on
     * `current_marker` never sees two current rows, even mid-transaction.
     * (A single `SET is_current = (id = ?)` can trip MySQL's row-by-row
     * uniqueness check depending on row order.)
     */
    public function execute(AcademicYear $year): AcademicYear
    {
        DB::transaction(function () use ($year) {
            // Serialize concurrent switches (no-op on SQLite).
            AcademicYear::query()->lockForUpdate()->pluck('id');

            AcademicYear::query()
                ->where('is_current', true)
                ->whereKeyNot($year->getKey())
                ->update(['is_current' => false]);

            AcademicYear::query()
                ->whereKey($year->getKey())
                ->update(['is_current' => true]);
        });

        AcademicYear::forgetCurrentCache();

        return $year->refresh();
    }
}
