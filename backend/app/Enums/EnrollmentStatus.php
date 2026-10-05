<?php

namespace App\Enums;

/** Where a student's enrollment for a year stands. Only `active` takes a seat. */
enum EnrollmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';       // moved on to the next class/year
    case Transferred = 'transferred'; // left for another school
    case Left = 'left';               // dropped out / left for other reasons
    case Graduated = 'graduated';     // completed the final class

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Statuses that end the enrollment and need an end date. */
    public function ends(): bool
    {
        return $this !== self::Active;
    }
}
