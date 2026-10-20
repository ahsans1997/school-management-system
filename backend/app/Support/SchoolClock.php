<?php

namespace App\Support;

use App\Models\SchoolSetting;
use Illuminate\Support\Carbon;

/**
 * The school's calendar day. Timestamps are stored in UTC (config
 * app.timezone), but "today" — the attendance date, upcoming exams, the
 * default admission date, the date on a certificate — is the school's own
 * day, in the timezone from School Settings (default Asia/Dhaka). Between
 * midnight and 06:00 in Dhaka, UTC is still on the previous day.
 */
final class SchoolClock
{
    public static function timezone(): string
    {
        return SchoolSetting::current()->timezone ?: config('app.timezone');
    }

    /** Midnight at the start of the school's current day. */
    public static function today(): Carbon
    {
        return Carbon::today(self::timezone());
    }

    /** The school's current day as "YYYY-MM-DD". */
    public static function date(): string
    {
        return self::today()->toDateString();
    }
}
