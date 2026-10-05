<?php

namespace App\Enums;

/** A student's attendance on a day (spec §14). */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Late still counts as attended; leave (excused) does not. */
    public function attended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }
}
