<?php

namespace App\Enums;

/** What a fee type is for (spec §18). */
enum FeeCategory: string
{
    case Admission = 'admission';
    case Tuition = 'tuition';
    case Exam = 'exam';
    case Transport = 'transport';
    case Library = 'library';
    case Hostel = 'hostel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Admission => 'Admission',
            self::Tuition => 'Tuition',
            self::Exam => 'Exam',
            self::Transport => 'Transport',
            self::Library => 'Library',
            self::Hostel => 'Hostel',
            self::Other => 'Other',
        };
    }
}
