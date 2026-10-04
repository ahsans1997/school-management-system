<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Page size for list endpoints. The raw ?per_page value can't go straight to
 * paginate(): a negative one breaks the SQL (500) and an unbounded one dumps
 * a whole table in a single response.
 */
final class PerPage
{
    public const DEFAULT = 25;

    public const MAX = 100;

    /** The requested size clamped to MAX; the default when absent or below 1. */
    public static function from(Request $request): int
    {
        $perPage = $request->integer('per_page', self::DEFAULT);

        return $perPage < 1 ? self::DEFAULT : min($perPage, self::MAX);
    }
}
