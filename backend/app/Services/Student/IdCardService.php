<?php

namespace App\Services\Student;

use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\SchoolLetterhead;
use Illuminate\Database\Eloquent\Builder;

/**
 * ID card data for a year (spec §9): the student's photo, IDs and place
 * (class/section/roll) from their active enrollment, the school letterhead,
 * and validity to the end of the academic year. Only students actively
 * enrolled that year get a card.
 */
class IdCardService
{
    /**
     * @return array<string, mixed>|null null when the student isn't actively enrolled that year
     */
    public function forStudent(Student $student, AcademicYear $year): ?array
    {
        $enrollment = $this->enrollments($year)->where('student_id', $student->id)->first();

        return $enrollment ? $this->sheet($year, [$this->card($enrollment)]) : null;
    }

    /** @return array<string, mixed> */
    public function forSection(Section $section, AcademicYear $year): array
    {
        $cards = $this->enrollments($year)
            ->where('section_id', $section->id)
            // The relation includes archived students; they get no card.
            ->whereHas('student', fn ($q) => $q->withoutTrashed())
            ->orderBy('roll')
            ->get()
            ->map(fn (StudentEnrollment $e) => $this->card($e))
            ->all();

        return $this->sheet($year, $cards);
    }

    /**
     * @return Builder<StudentEnrollment>
     */
    private function enrollments(AcademicYear $year): Builder
    {
        return StudentEnrollment::query()
            ->active()
            ->where('academic_year_id', $year->id)
            ->with(['student.primaryGuardian', 'schoolClass', 'section']);
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    private function sheet(AcademicYear $year, array $cards): array
    {
        return [
            'school' => SchoolLetterhead::toArray(),
            'academic_year' => ['id' => $year->id, 'name' => $year->name, 'valid_until' => $year->end_date->toDateString()],
            'cards' => $cards,
        ];
    }

    /** @return array<string, mixed> */
    private function card(StudentEnrollment $enrollment): array
    {
        $student = $enrollment->student;

        return [
            'student_id' => $student->id,
            'name' => $student->name,
            'id_no' => $student->student_id,
            'admission_no' => $student->admission_no,
            'photo_url' => $student->photoUrl(),
            'dob' => $student->dob->toDateString(),
            'blood_group' => $student->blood_group?->value,
            'class' => $enrollment->schoolClass->name,
            'section' => $enrollment->section->name,
            'roll' => $enrollment->roll,
            // Emergency contact: the primary guardian, else the student's own number.
            'contact_phone' => $student->primaryGuardian->first()->phone ?? $student->phone,
        ];
    }
}
