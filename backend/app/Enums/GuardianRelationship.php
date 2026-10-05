<?php

namespace App\Enums;

/** How a guardian is related to a student (student_guardians.relationship). */
enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Brother = 'brother';
    case Sister = 'sister';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
