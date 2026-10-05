<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/** Days of the school week, in display order (the week starts on Saturday). */
enum Weekday: string
{
    case Saturday = 'sat';
    case Sunday = 'sun';
    case Monday = 'mon';
    case Tuesday = 'tue';
    case Wednesday = 'wed';
    case Thursday = 'thu';
    case Friday = 'fri';

    public function label(): string
    {
        return $this->name;
    }

    /** 0-based position in the school week, for sorting. */
    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }

    public static function fromDate(Carbon $date): self
    {
        return self::from(strtolower($date->format('D')));
    }
}
