<?php

namespace App\Services\Exam;

use App\Enums\DashboardAudience;
use App\Enums\EnrollmentStatus;
use App\Enums\MarkStatus;
use App\Enums\ResultStatus;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Guardian;
use App\Models\Mark;
use App\Models\Result;
use App\Models\ResultDetail;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Support\SchoolLetterhead;
use App\Support\TeacherScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reading results: which mark sheets a class still waits for, each class's
 * place in the workflow, merit lists, tabulation sheets, statistics and
 * report cards. Teachers (result.view) see only their own sections.
 */
class ResultService
{
    public function __construct(private readonly ExamScheduleService $schedules) {}

    /**
     * Every mark sheet (paper × section) results depend on, with its status
     * ('not_started' | 'draft' | 'submitted'; submitted only when every mark
     * on it is — stricter than the marks screens, which lock on any).
     * Sections without students that year and without marks are left out.
     *
     * @return Collection<int, array{class_id: int, section: Section, paper: ExamSubject, status: string}>
     */
    public function sheets(Exam $exam, ?int $classId = null): Collection
    {
        $papers = $exam->subjects()->with('classSubject.subject')
            ->when($classId, fn ($q, int $id) => $q->where('class_id', $id))->get();
        $sections = Section::query()->whereIn('class_id', $papers->pluck('class_id')->unique())->orderBy('name')->get();

        $counts = Mark::query()
            ->join('student_enrollments as se', 'se.id', '=', 'marks.student_enrollment_id')
            ->whereIn('marks.exam_subject_id', $papers->modelKeys())
            ->groupBy('marks.exam_subject_id', 'se.section_id')
            ->selectRaw('marks.exam_subject_id, se.section_id, count(*) as entered, sum(case when marks.status = ? then 1 else 0 end) as submitted', [MarkStatus::Submitted->value])
            ->toBase()->get()
            ->keyBy(fn (object $row) => $row->exam_subject_id.':'.$row->section_id);

        // Sections with students that year (on the roll at some point of the exam).
        $populated = StudentEnrollment::query()
            ->where('academic_year_id', $exam->academic_year_id)
            ->whereIn('section_id', $sections->modelKeys())
            ->where(fn ($q) => $q->where('status', EnrollmentStatus::Active->value)->orWhereDate('ended_on', '>=', $exam->start_date->toDateString()))
            ->whereHas('student', fn ($q) => $q->withoutTrashed())
            ->distinct()->pluck('section_id')->all();

        return $papers->flatMap(fn (ExamSubject $paper) => $sections
            ->filter(fn (Section $s) => $s->class_id === $paper->class_id && ($paper->section_id === null || $paper->section_id === $s->id))
            ->map(function (Section $section) use ($paper, $counts, $populated) {
                $row = $counts->get($paper->id.':'.$section->id);
                if ($row === null && ! in_array($section->id, $populated, true)) {
                    return null;
                }

                return [
                    'class_id' => $paper->class_id,
                    'section' => $section,
                    'paper' => $paper,
                    'status' => match (true) {
                        $row !== null && (int) $row->submitted === (int) $row->entered => 'submitted', // every mark on it
                        $row !== null => 'draft',
                        default => 'not_started',
                    },
                ];
            })
            ->filter()
        )->values();
    }

    /**
     * Each class sitting the exam: its marks progress and where its results
     * are in the workflow. Teachers get the classes of their own sections,
     * with figures for those sections only.
     *
     * @return list<array<string, mixed>>
     */
    public function overview(User $user, Exam $exam): array
    {
        $visible = $this->sectionScope($user, $exam);
        $sheets = $this->sheets($exam)
            ->filter(fn (array $s) => $visible === null || in_array($s['section']->id, $visible, true))
            ->groupBy('class_id');
        $results = Result::query()->where('exam_id', $exam->id)
            ->when($visible !== null, fn ($q) => $q->whereIn('section_id', $visible))
            ->selectRaw('class_id, status, count(*) as students, sum(case when passed then 1 else 0 end) as passed, max(calculated_at) as calculated_at, max(published_at) as published_at')
            ->groupBy('class_id', 'status')
            ->toBase()->get()->keyBy('class_id');

        return SchoolClass::query()
            ->whereIn('id', $exam->subjects()->distinct()->pluck('class_id'))
            ->when($visible !== null, fn ($q) => $q->whereHas('sections', fn ($s) => $s->whereKey($visible)))
            ->orderBy('numeric_level')
            ->get()
            ->map(function (SchoolClass $class) use ($exam, $sheets, $results) {
                $classSheets = $sheets->get($class->id, collect());
                $pending = $classSheets->where('status', '!=', 'submitted');
                $result = $results->get($class->id);

                return [
                    'class' => ['id' => $class->id, 'name' => $class->name],
                    'sheets' => ['total' => $classSheets->count(), 'submitted' => $classSheets->count() - $pending->count()],
                    'pending' => $pending->take(6)->map(fn (array $s) => "{$class->name} {$s['section']->name} · {$s['paper']->classSubject->subject->name}")->values()->all(),
                    'status' => $result?->status,
                    'students' => (int) ($result->students ?? 0),
                    'passed' => (int) ($result->passed ?? 0),
                    'calculated_at' => $this->iso($result->calculated_at ?? null),
                    'published_at' => $this->iso($result->published_at ?? null),
                    // Calculated before the grade scale changed: recalculate before moving on.
                    'stale' => $result !== null && $this->stale($exam, $class->id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Sections whose results the user may read for this exam; null = all.
     * Teachers get their own sections, and only for the current year's
     * exams: teaching assignments carry no year, so last year's 6A belongs
     * to whoever taught it then.
     *
     * @return list<int>|null
     */
    public function sectionScope(User $user, Exam $exam): ?array
    {
        $ids = TeacherScope::sectionIds($user);
        if ($ids === null) {
            return null;
        }

        return $exam->academic_year_id === AcademicYear::current()?->id ? $ids : [];
    }

    /**
     * Results the user may see for an exam (sectionScope).
     *
     * @return Builder<Result>
     */
    public function visible(User $user, Exam $exam): Builder
    {
        $ids = $this->sectionScope($user, $exam);

        return Result::query()
            ->where('exam_id', $exam->id)
            ->when($ids !== null, fn ($q) => $q->whereIn('section_id', $ids));
    }

    /**
     * Merit order: passed first by position, then the rest by GPA and marks.
     *
     * @param  Builder<Result>  $query
     * @return Builder<Result>
     */
    public function meritOrder(Builder $query, bool $bySection): Builder
    {
        return $query
            ->orderByDesc('passed')
            ->orderByRaw(($bySection ? 'position' : 'class_position').' is null')
            ->orderBy($bySection ? 'position' : 'class_position')
            ->orderByDesc('gpa')
            ->orderByDesc('total_marks')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Result $result): array
    {
        return [
            'id' => $result->id,
            'student' => ['id' => $result->student->id, 'name' => $result->student->name, 'student_id' => $result->student->student_id],
            'roll' => $result->enrollment->roll,
            'class' => ['id' => $result->schoolClass->id, 'name' => $result->schoolClass->name],
            'section' => ['id' => $result->section->id, 'name' => $result->section->name],
            'total_marks' => (float) $result->total_marks,
            'full_marks' => (float) $result->full_marks,
            'percentage' => (float) $result->percentage,
            'gpa' => (float) $result->gpa,
            'grade' => $result->grade,
            'passed' => $result->passed,
            'failed_subjects' => $result->failed_subjects,
            'position' => $result->position,
            'class_position' => $result->class_position,
            'status' => $result->status->value,
        ];
    }

    /**
     * The tabulation sheet: one row per student, one column per paper.
     *
     * @param  Collection<int, Result>  $results  with details, student, enrollment, section, class
     * @return array<string, mixed>
     */
    public function tabulation(Collection $results): array
    {
        $papers = $results->flatMap(fn (Result $r) => $r->details)
            ->unique('exam_subject_id')
            ->sortBy('id')
            ->map(fn (ResultDetail $d) => [
                'exam_subject_id' => $d->exam_subject_id,
                'subject' => $d->subject,
                'code' => $d->subject_code,
                'full_marks' => (float) $d->full_marks,
            ])
            ->values();

        return [
            'subjects' => $papers->all(),
            'rows' => $results->map(fn (Result $r) => [
                ...$this->row($r),
                'subjects' => $r->details->mapWithKeys(fn (ResultDetail $d) => [$d->exam_subject_id => [
                    'obtained' => $this->number($d->obtained),
                    'absent' => $d->absent,
                    'grade' => $d->grade,
                    'grade_point' => (float) $d->grade_point,
                    'passed' => $d->passed,
                ]])->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Pass rate, GPA and grade spread, and per-subject figures.
     *
     * @param  Collection<int, Result>  $results  with details
     * @return array<string, mixed>
     */
    public function statistics(Collection $results): array
    {
        $count = $results->count();
        $passed = $results->where('passed', true)->count();
        $details = $results->flatMap(fn (Result $r) => $r->details);

        return [
            'students' => $count,
            'passed' => $passed,
            'failed' => $count - $passed,
            'pass_rate' => $count === 0 ? null : round($passed / $count * 100, 1),
            'average_gpa' => $count === 0 ? null : round($results->avg(fn (Result $r) => (float) $r->gpa), 2),
            'highest_total' => $count === 0 ? null : (float) $results->max(fn (Result $r) => (float) $r->total_marks),
            'grades' => $results->countBy('grade')->sortKeys()->map(fn (int $n, string $grade) => ['grade' => $grade, 'students' => $n])->values()->all(),
            'by_section' => $results->groupBy('section_id')->map(fn (Collection $rows) => [
                'section' => ['id' => $rows->first()->section->id, 'name' => $rows->first()->section->name],
                'students' => $rows->count(),
                'passed' => $rows->where('passed', true)->count(),
                'pass_rate' => round($rows->where('passed', true)->count() / $rows->count() * 100, 1),
                'average_gpa' => round($rows->avg(fn (Result $r) => (float) $r->gpa), 2),
            ])->sortBy('section.name')->values()->all(),
            'subjects' => $details->groupBy('exam_subject_id')->map(function (Collection $lines) {
                $sat = $lines->where('absent', false);
                $first = $lines->first();

                return [
                    'exam_subject_id' => $first->exam_subject_id,
                    'subject' => $first->subject,
                    'full_marks' => (float) $first->full_marks,
                    'students' => $lines->count(),
                    'absent' => $lines->count() - $sat->count(),
                    'passed' => $lines->where('passed', true)->count(),
                    'pass_rate' => round($lines->where('passed', true)->count() / $lines->count() * 100, 1),
                    'highest' => $sat->isEmpty() ? null : (float) $sat->max(fn (ResultDetail $d) => (float) $d->obtained),
                    'average' => $sat->isEmpty() ? null : round($sat->avg(fn (ResultDetail $d) => (float) $d->obtained), 2),
                ];
            })->sortBy('exam_subject_id')->values()->all(),
        ];
    }

    /**
     * A student's report card / mark sheet.
     *
     * @return array<string, mixed>
     */
    public function reportCard(Result $result): array
    {
        $result->loadMissing(['details', 'student', 'enrollment', 'section', 'schoolClass', 'exam.academicYear', 'publishedBy']);
        $student = $result->student;

        return [
            ...$this->row($result),
            'school' => SchoolLetterhead::toArray(),
            'exam' => [...$this->schedules->header($result->exam), 'academic_year' => $result->exam->academicYear->name],
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'student_id' => $student->student_id,
                'admission_no' => $student->admission_no,
                'father_name' => $student->father_name,
                'mother_name' => $student->mother_name,
            ],
            'subjects' => $result->details->map(fn (ResultDetail $d) => [
                'exam_subject_id' => $d->exam_subject_id,
                'subject' => $d->subject,
                'code' => $d->subject_code,
                'full_marks' => (float) $d->full_marks,
                'pass_marks' => (float) $d->pass_marks,
                'cq' => $this->number($d->cq),
                'mcq' => $this->number($d->mcq),
                'practical' => $this->number($d->practical),
                'obtained' => $this->number($d->obtained),
                'absent' => $d->absent,
                'percentage' => (float) $d->percentage,
                'grade' => $d->grade,
                'grade_point' => (float) $d->grade_point,
                'passed' => $d->passed,
            ])->values()->all(),
            'section_students' => Result::query()->where('exam_id', $result->exam_id)->where('section_id', $result->section_id)->count(),
            'class_students' => Result::query()->where('exam_id', $result->exam_id)->where('class_id', $result->class_id)->count(),
            'published_at' => $result->published_at?->toIso8601String(),
        ];
    }

    /**
     * The caller's own published results, newest exam first: a student's,
     * or each of a parent's children's. Ownership decides — nothing
     * unpublished is ever included.
     *
     * @return array{type: string, students: list<array{student: array{id: int, name: string}, results: Collection<int, Result>}>}
     */
    public function mine(User $user): array
    {
        [$type, $students] = match (DashboardAudience::forUser($user)) {
            DashboardAudience::Student => ['student', collect([$this->personOf($user, Student::class)])->filter()],
            DashboardAudience::Parent => ['parent', $this->personOf($user, Guardian::class)?->students()->orderBy('name')->get() ?? collect()],
            default => ['none', collect()],
        };

        return [
            'type' => $type,
            'students' => $students->map(fn (Student $student) => [
                'student' => ['id' => $student->id, 'name' => $student->name],
                'results' => Result::query()->published()
                    ->where('student_id', $student->id)
                    ->with(['exam', 'section', 'schoolClass'])
                    ->get()
                    ->filter(fn (Result $r) => $r->exam !== null) // archived exams drop out
                    ->sortByDesc(fn (Result $r) => $r->exam->start_date->toDateString())
                    ->values(),
            ])->values()->all(),
        ];
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function personOf(User $user, string $model)
    {
        return $user->personable_type === (new $model)->getMorphClass() ? $model::find($user->personable_id) : null;
    }

    /** Whether the class's results were calculated before its exam's grade scale last changed. */
    public function stale(Exam $exam, int $classId): bool
    {
        $calculated = Result::query()->where('exam_id', $exam->id)->where('class_id', $classId)->min('calculated_at');
        $scaleChanged = $exam->gradeScale()->value('updated_at');

        return $calculated !== null && $scaleChanged !== null && Carbon::parse($calculated)->lt(Carbon::parse($scaleChanged));
    }

    /** Whether the class's results are past draft (so its marks and papers are frozen). */
    public function frozen(Exam $exam, int $classId): ?ResultStatus
    {
        return Result::query()->where('exam_id', $exam->id)->where('class_id', $classId)
            ->where('status', '!=', ResultStatus::Draft->value)->first(['status'])?->status;
    }

    private function number(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }
}
