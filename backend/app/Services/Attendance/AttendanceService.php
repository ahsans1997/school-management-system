<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceDetail;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Who belongs on a sheet, and the percentage/summary maths for reports. */
class AttendanceService
{
    /** The academic year whose dates cover $date (years never overlap). */
    public function yearFor(string $date): ?AcademicYear
    {
        return AcademicYear::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    /**
     * The section's roll on $date: enrollments for that year that were still
     * running that day (active, or ended after it), of students who aren't
     * archived. Ordered by roll.
     *
     * @return Collection<int, StudentEnrollment>
     */
    public function roster(Section $section, AcademicYear $year, string $date): Collection
    {
        return StudentEnrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where('academic_year_id', $year->id)
            ->where(fn ($q) => $q->where('status', EnrollmentStatus::Active->value)->orWhereDate('ended_on', '>', $date))
            ->whereHas('student', fn ($q) => $q->withoutTrashed())
            ->orderBy('roll')
            ->get();
    }

    /**
     * Counts per status plus the attendance percentage: attended days
     * (present + late) over the days the student was marked. Null when
     * nothing was marked.
     *
     * @param  iterable<AttendanceDetail>  $details
     * @return array{present: int, absent: int, late: int, leave: int, marked: int, percentage: float|null}
     */
    public function summarize(iterable $details): array
    {
        $counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];
        $attended = 0;

        foreach ($details as $detail) {
            $counts[$detail->status->value]++;
            $attended += $detail->status->attended() ? 1 : 0;
        }

        $marked = array_sum($counts);

        return [
            ...$counts,
            'marked' => $marked,
            'percentage' => $marked === 0 ? null : round($attended / $marked * 100, 1),
        ];
    }

    /**
     * A month grid for a section: the days with a sheet, and per student the
     * status on each day plus a summary. Students are everyone marked that
     * month plus everyone currently on the section's roll.
     *
     * @return array<string, mixed>
     */
    public function monthly(Section $section, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        $end = $start->copy()->endOfMonth();

        $details = AttendanceDetail::query()
            ->with('student')
            ->whereHas('attendance', fn ($q) => $q->where('section_id', $section->id))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $days = $details->map(fn (AttendanceDetail $d) => $d->date->toDateString())->unique()->sort()->values();

        $year = $this->yearFor($start->toDateString()) ?? $this->yearFor($end->toDateString());
        $roster = $year ? $this->roster($section, $year, $end->toDateString()) : collect();
        $rolls = $roster->mapWithKeys(fn (StudentEnrollment $e) => [$e->student_id => $e->roll]);

        $students = $details->map(fn (AttendanceDetail $d) => $d->student)
            ->merge($roster->map(fn (StudentEnrollment $e) => $e->student))
            ->unique('id')
            ->sortBy(fn (Student $s) => [$rolls[$s->id] ?? PHP_INT_MAX, $s->name])
            ->values();

        $byStudent = $details->groupBy('student_id');

        return [
            'month' => $month,
            'days' => $days->all(),
            'students' => $students->map(fn (Student $s) => [
                'student_id' => $s->id,
                'name' => $s->name,
                'roll' => $rolls[$s->id] ?? null,
                'statuses' => ($byStudent[$s->id] ?? collect())
                    ->mapWithKeys(fn (AttendanceDetail $d) => [$d->date->toDateString() => $d->status->value])
                    ->all(),
                'summary' => $this->summarize($byStudent[$s->id] ?? []),
            ])->all(),
        ];
    }

    /**
     * One student's attendance for a year: totals, a per-month breakdown and
     * the days they weren't simply present.
     *
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, AcademicYear $year): array
    {
        $details = AttendanceDetail::query()
            ->where('student_id', $student->id)
            ->whereBetween('date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
            ->orderBy('date')
            ->get();

        return [
            'academic_year' => ['id' => $year->id, 'name' => $year->name],
            'summary' => $this->summarize($details),
            'months' => $details->groupBy(fn (AttendanceDetail $d) => $d->date->format('Y-m'))
                ->map(fn (Collection $month, string $key) => ['month' => $key, ...$this->summarize($month)])
                ->values()
                ->all(),
            'exceptions' => $details->where('status', '!=', AttendanceStatus::Present)
                ->map(fn (AttendanceDetail $d) => [
                    'date' => $d->date->toDateString(),
                    'status' => $d->status->value,
                    'remark' => $d->remark,
                ])
                ->values()
                ->all(),
        ];
    }
}
