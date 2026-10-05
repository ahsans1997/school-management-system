<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Named counters for human-facing numbers (admission numbers now; receipts
 * and invoices later). The row lock makes concurrent callers queue, and
 * when called inside an outer transaction a rollback also returns the
 * number, so no gaps appear for failed saves.
 */
final class Sequence
{
    /** The next value of the counter (starting at 1). */
    public static function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            $now = now();
            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key, 'next_value' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $current = (int) DB::table('number_sequences')
                ->where('key', $key)
                ->lockForUpdate()
                ->value('next_value');

            DB::table('number_sequences')
                ->where('key', $key)
                ->update(['next_value' => $current + 1, 'updated_at' => $now]);

            return $current;
        });
    }
}
