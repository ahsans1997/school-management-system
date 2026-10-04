<?php

namespace App\Enums;

/** Whether a subject is taught/assessed as theory, practical, or both. */
enum SubjectType: string
{
    case Theory = 'theory';
    case Practical = 'practical';
    case TheoryPractical = 'theory_practical';

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Theory',
            self::Practical => 'Practical',
            self::TheoryPractical => 'Theory + practical',
        };
    }
}
