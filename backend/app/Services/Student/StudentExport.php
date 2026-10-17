<?php

namespace App\Services\Student;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The student register as rows for a CSV/Excel export, with each student's
 * place (class/section/roll/status) in the chosen year. The headers match
 * the import's columns, so an export can be edited and imported elsewhere.
 */
class StudentExport
{
    public const HEADER = [
        'Student ID', 'Admission no', 'Name', 'Gender', 'Date of birth', 'Blood group', 'Phone', 'Address',
        "Father's name", "Mother's name", 'Admission date', 'Previous school',
        'Primary guardian', 'Guardian phone', 'Academic year', 'Class', 'Section', 'Roll', 'Enrollment status',
    ];

    /**
     * @param  array{class_id?: int|null, section_id?: int|null, status?: string|null, archived?: bool, search?: string|null}  $filters
     * @return Collection<int, list<string|int|null>>
     */
    public function rows(User $user, AcademicYear $year, array $filters): Collection
    {
        $inYear = fn ($q) => $q->where('academic_year_id', $year->id);

        $students = Student::query()
            ->visibleTo($user)
            ->when($filters['archived'] ?? false, fn ($q) => $q->onlyTrashed())
            ->with(['primaryGuardian', 'enrollments' => fn ($q) => $inYear($q)->with(['schoolClass', 'section'])])
            ->when($filters['class_id'] ?? null, fn ($q, int $id) => $q->whereHas('enrollments', fn ($e) => $inYear($e)->where('class_id', $id)))
            ->when($filters['section_id'] ?? null, fn ($q, int $id) => $q->whereHas('enrollments', fn ($e) => $inYear($e)->where('section_id', $id)))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $status === 'not_enrolled'
                ? $q->whereDoesntHave('enrollments', $inYear)
                : $q->whereHas('enrollments', fn ($e) => $inYear($e)->where('status', $status)))
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")
                ->orWhere('admission_no', 'like', "%{$search}%")))
            ->get();

        // Class order, then section and roll; students without a place last, by name.
        return $students
            ->map(fn (Student $s) => ['student' => $s, 'place' => $s->enrollments->first()])
            ->sortBy(fn (array $r) => $r['place'] === null
                ? [1, 0, '', 0, $r['student']->name]
                : [0, $r['place']->schoolClass->numeric_level, $r['place']->section->name, $r['place']->roll, $r['student']->name])
            ->values()
            ->map(fn (array $r) => $this->row($r['student'], $r['place'], $year));
    }

    /** @return list<string|int|null> */
    private function row(Student $student, ?StudentEnrollment $place, AcademicYear $year): array
    {
        $guardian = $student->primaryGuardian->first();

        return [
            $student->student_id,
            $student->admission_no,
            $student->name,
            $student->gender->label(),
            $student->dob->toDateString(),
            $student->blood_group?->value,
            $student->phone,
            $student->address,
            $student->father_name,
            $student->mother_name,
            $student->admission_date->toDateString(),
            $student->previous_school,
            $guardian?->name,
            $guardian?->phone,
            $year->name,
            $place?->schoolClass->name,
            $place?->section->name,
            $place?->roll,
            $place?->status->label() ?? 'Not enrolled',
        ];
    }
}
