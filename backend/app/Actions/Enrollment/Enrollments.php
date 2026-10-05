<?php

namespace App\Actions\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enrolling, moving and promoting students. Rules:
 *  - one enrollment per student per year (unique index);
 *  - rolls unique within a section for a year (unique index), assigned
 *    sequentially unless typed in;
 *  - a section never holds more active, non-archived students than its
 *    capacity.
 * Writes lock the affected section rows, so concurrent enrollments into the
 * same section queue up and can't both take the last seat.
 */
class Enrollments
{
    public function enroll(Student $student, AcademicYear $year, Section $section, ?int $roll): StudentEnrollment
    {
        return DB::transaction(function () use ($student, $year, $section, $roll) {
            $this->lock($section);

            $existing = StudentEnrollment::query()
                ->with(['schoolClass', 'section'])
                ->where('student_id', $student->id)
                ->where('academic_year_id', $year->id)
                ->first();
            if ($existing !== null) {
                $this->fail('student_id', "{$student->name} is already enrolled for {$year->name} ({$existing->schoolClass->name} {$existing->section->name}).");
            }

            $this->ensureSeat($section, $year, 1);
            if ($roll !== null) {
                $this->ensureRollFree($section, $year, $roll);
            }

            return StudentEnrollment::query()->forceCreate([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_id' => $section->class_id,
                'section_id' => $section->id,
                'roll' => $roll ?? $this->nextRoll($section, $year),
                'status' => EnrollmentStatus::Active,
            ]);
        });
    }

    /**
     * Move to another section, change the roll, or change the status. An
     * ending status needs an end date (defaults to today); `active` clears it.
     */
    public function update(
        StudentEnrollment $enrollment,
        Section $section,
        ?int $roll,
        EnrollmentStatus $status,
        ?string $endedOn,
        ?string $exitReason,
    ): StudentEnrollment {
        return DB::transaction(function () use ($enrollment, $section, $roll, $status, $endedOn, $exitReason) {
            $year = $enrollment->academicYear;
            $moving = $section->id !== $enrollment->section_id;
            $this->lock($section);

            // Taking a seat it didn't hold before: moving while active, or re-activating.
            $takesSeat = $status === EnrollmentStatus::Active
                && ($moving || $enrollment->status !== EnrollmentStatus::Active);
            if ($takesSeat) {
                $this->ensureSeat($section, $year, 1, ignore: $moving ? null : $enrollment);
            }

            $roll ??= $moving ? $this->nextRoll($section, $year) : $enrollment->roll;
            if ($roll !== $enrollment->roll || $moving) {
                $this->ensureRollFree($section, $year, $roll, $enrollment);
            }

            $enrollment->forceFill([
                'class_id' => $section->class_id,
                'section_id' => $section->id,
                'roll' => $roll,
                'status' => $status,
                'ended_on' => $status->ends() ? ($endedOn ?? today()->toDateString()) : null,
                'exit_reason' => $status->ends() ? $exitReason : null,
            ])->save();

            return $enrollment;
        });
    }

    /**
     * Move a cohort up a class: each chosen student's active enrollment in
     * $from/$fromYear becomes `promoted`, and a new active enrollment is
     * created in $to for $toYear, rolls numbered in their old roll order.
     * All or nothing.
     *
     * @param  list<int>  $studentIds
     * @return list<StudentEnrollment> the new enrollments
     */
    public function promote(AcademicYear $fromYear, Section $from, AcademicYear $toYear, Section $to, array $studentIds): array
    {
        $from->loadMissing('schoolClass');
        $to->loadMissing('schoolClass');

        if (! $toYear->start_date->greaterThan($fromYear->start_date)) {
            $this->fail('to_year_id', "Promote into a later year than {$fromYear->name}.");
        }
        $nextLevel = $from->schoolClass->numeric_level + 1;
        if ($to->schoolClass->numeric_level !== $nextLevel) {
            $this->fail('to_section_id', "Promote into a class one level above {$from->schoolClass->name} (level {$nextLevel}).");
        }

        return DB::transaction(function () use ($fromYear, $from, $toYear, $to, $studentIds) {
            $this->lock($from, $to);

            $current = StudentEnrollment::query()
                ->active()
                ->where('academic_year_id', $fromYear->id)
                ->where('section_id', $from->id)
                ->whereIn('student_id', $studentIds)
                ->orderBy('roll')
                ->get();
            if ($current->count() !== count(array_unique($studentIds))) {
                $this->fail('student_ids', "Some selected students aren't active in {$from->schoolClass->name} {$from->name} for {$fromYear->name}.");
            }

            $already = StudentEnrollment::query()
                ->with('student')
                ->where('academic_year_id', $toYear->id)
                ->whereIn('student_id', $studentIds)
                ->get();
            if ($already->isNotEmpty()) {
                $names = $already->map(fn (StudentEnrollment $e) => $e->student->name)->join(', ');
                $this->fail('student_ids', "Already enrolled for {$toYear->name}: {$names}.");
            }

            $this->ensureSeat($to, $toYear, $current->count());

            $roll = $this->nextRoll($to, $toYear);
            $today = Carbon::today()->toDateString();
            $created = [];

            foreach ($current as $enrollment) {
                $enrollment->forceFill([
                    'status' => EnrollmentStatus::Promoted,
                    'ended_on' => $today,
                    'exit_reason' => null,
                ])->save();

                $created[] = StudentEnrollment::query()->forceCreate([
                    'student_id' => $enrollment->student_id,
                    'academic_year_id' => $toYear->id,
                    'class_id' => $to->class_id,
                    'section_id' => $to->id,
                    'roll' => $roll++,
                    'status' => EnrollmentStatus::Active,
                ]);
            }

            return $created;
        });
    }

    /** Seats taken: active enrollments of students who aren't archived. */
    public static function seatsTaken(Section $section, AcademicYear $year, ?StudentEnrollment $ignore = null): int
    {
        return StudentEnrollment::query()
            ->active()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->whereHas('student', fn ($q) => $q->withoutTrashed())
            ->when($ignore, fn ($q, StudentEnrollment $e) => $q->whereKeyNot($e->id))
            ->count();
    }

    private function ensureSeat(Section $section, AcademicYear $year, int $needed, ?StudentEnrollment $ignore = null): void
    {
        $section->loadMissing('schoolClass');
        $taken = self::seatsTaken($section, $year, $ignore);

        if ($taken + $needed > $section->capacity) {
            $free = max(0, $section->capacity - $taken);
            $this->fail('section_id', $needed === 1
                ? "{$section->schoolClass->name} {$section->name} is full for {$year->name} ({$taken} of {$section->capacity} seats taken)."
                : "{$section->schoolClass->name} {$section->name} has {$free} free seat(s) for {$year->name}; {$needed} students selected.");
        }
    }

    private function ensureRollFree(Section $section, AcademicYear $year, int $roll, ?StudentEnrollment $self = null): void
    {
        $taken = StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->where('roll', $roll)
            ->when($self, fn ($q, StudentEnrollment $e) => $q->whereKeyNot($e->id))
            ->exists();

        if ($taken) {
            $section->loadMissing('schoolClass');
            $this->fail('roll', "Roll {$roll} is already taken in {$section->schoolClass->name} {$section->name} for {$year->name}.");
        }
    }

    private function nextRoll(Section $section, AcademicYear $year): int
    {
        return (int) StudentEnrollment::query()
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->max('roll') + 1;
    }

    private function lock(Section ...$sections): void
    {
        $ids = array_map(fn (Section $s) => $s->id, $sections);
        sort($ids); // consistent order → no deadlocks between opposite moves

        Section::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
