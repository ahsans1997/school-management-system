<?php

namespace App\Services\Student;

use App\Models\Student;
use App\Support\Sequence;

/** Generates the two student identifiers when none is typed in. */
final class StudentNumberGenerator
{
    /**
     * Admission register number "YYYY-NNNN", counted per admission year.
     * Skips any number already taken (e.g. typed in when migrating records).
     */
    public function admissionNo(int $year): string
    {
        do {
            $number = sprintf('%d-%04d', $year, Sequence::next("admission_no:{$year}"));
        } while ($this->taken('admission_no', $number));

        return $number;
    }

    /**
     * Public-facing student ID: 8 random digits. Not sequential, so it can't
     * be enumerated through the public result lookup (Module 32).
     */
    public function studentId(): string
    {
        do {
            $id = (string) random_int(10_000_000, 99_999_999);
        } while ($this->taken('student_id', $id));

        return $id;
    }

    private function taken(string $column, string $value): bool
    {
        return Student::withTrashed()->where($column, $value)->exists();
    }
}
