<?php

namespace App\Enums;

/** Language medium/version a class is taught in (spec §8 academic settings). */
enum Medium: string
{
    case Bangla = 'bangla';
    case English = 'english';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
