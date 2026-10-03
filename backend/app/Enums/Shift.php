<?php

namespace App\Enums;

/** School shift a class runs in (spec §8 academic settings). */
enum Shift: string
{
    case Morning = 'morning';
    case Day = 'day';
    case Evening = 'evening';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
