<?php

namespace App\Services\Exam;

use App\Enums\DashboardAudience;
use App\Http\Resources\ExamSubjectResource;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Guardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Exam schedules, read from the papers (exam_subjects): an exam's whole
 * timetable, one class's or section's, and the caller's own ("my exams").
 */
class ExamScheduleService
{
    private const RELATIONS = ['schoolClass', 'section', 'classSubject.subject'];

    /**
     * An exam's papers in timetable order, optionally for one section (its
     * class-wide papers plus its own) or one class.
     *
     * @return Collection<int, ExamSubject>
     */
    public function papers(Exam $exam, ?int $classId = null, ?Section $section = null): Collection
    {
        $papers = $exam->subjects()
            ->with(self::RELATIONS)
            ->when($section, fn ($q) => $q->forSection($section))
            ->when(! $section && $classId, fn ($q) => $q->where('class_id', $classId))
            ->get();

        return $this->sorted($papers);
    }

    /**
     * Exams of the year still to come or under way (last day today or later).
     *
     * @return Builder<Exam>
     */
    public function upcoming(AcademicYear $year): Builder
    {
        $today = today()->toDateString();

        return Exam::query()
            ->where('academic_year_id', $year->id)
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $today)
                ->orWhere(fn ($q) => $q->whereNull('end_date')->whereDate('start_date', '>=', $today)))
            ->orderBy('start_date')
            ->orderBy('name');
    }

    /**
     * The caller's upcoming exams, one group per section they're tied to:
     * a teacher's sections, a student's own, or each of a parent's children.
     *
     * @return array{type: string, groups: list<array<string, mixed>>}
     */
    public function mine(User $user, AcademicYear $year): array
    {
        [$type, $targets] = $this->targets($user, $year);
        $exams = $this->upcoming($year)->get();

        $groups = array_map(function (array $target) use ($exams) {
            $section = $target['section'];
            $papers = $section
                ? $this->sorted(ExamSubject::query()->forSection($section)->whereIn('exam_id', $exams->modelKeys())->with(self::RELATIONS)->get())->groupBy('exam_id')
                : collect();

            return [
                'student' => $target['student'] ? ['id' => $target['student']->id, 'name' => $target['student']->name] : null,
                'section' => $section ? ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name] : null,
                'exams' => $exams->filter(fn (Exam $e) => $papers->has($e->id))
                    ->map(fn (Exam $e) => [...$this->header($e), 'papers' => ExamSubjectResource::collection($papers->get($e->id))->resolve()])
                    ->values()
                    ->all(),
            ];
        }, $targets);

        return ['type' => $type, 'groups' => $groups];
    }

    /** @return array<string, mixed> */
    public function header(Exam $exam): array
    {
        return [
            'id' => $exam->id,
            'name' => $exam->name,
            'type' => $exam->type->value,
            'start_date' => $exam->start_date->toDateString(),
            'end_date' => $exam->end_date?->toDateString(),
        ];
    }

    /**
     * @return array{0: string, 1: list<array{student: Student|null, section: Section|null}>}
     */
    private function targets(User $user, AcademicYear $year): array
    {
        $sectionOf = function (Student $student) use ($year): array {
            $enrollment = $student->enrollments()->where('academic_year_id', $year->id)->active()->with('section.schoolClass')->first();

            return ['student' => $student, 'section' => $enrollment?->section];
        };

        return match (DashboardAudience::forUser($user)) {
            DashboardAudience::Teacher => ['teacher', ($teacher = Teacher::forUser($user))
                ? Section::query()->with('schoolClass')->whereIn('id', $teacher->sectionIds())->get()
                    ->sortBy(fn (Section $s) => [$s->schoolClass->numeric_level, $s->name])
                    ->map(fn (Section $s) => ['student' => null, 'section' => $s])->values()->all()
                : []],
            DashboardAudience::Student => ['student', ($student = $this->personOf($user, Student::class)) ? [$sectionOf($student)] : []],
            DashboardAudience::Parent => ['parent', ($guardian = $this->personOf($user, Guardian::class))
                ? $guardian->students()->orderBy('name')->get()->map($sectionOf)->all()
                : []],
            default => ['none', []],
        };
    }

    /**
     * Dated papers first (by date, then time), undated ones last; ties by
     * class level and subject name.
     *
     * @param  Collection<int, ExamSubject>  $papers
     * @return Collection<int, ExamSubject>
     */
    private function sorted(Collection $papers): Collection
    {
        return $papers->sortBy([
            fn (ExamSubject $a, ExamSubject $b) => [$a->exam_date === null, $a->exam_date, $a->startsAt() === null, $a->startsAt()]
                <=> [$b->exam_date === null, $b->exam_date, $b->startsAt() === null, $b->startsAt()],
            fn (ExamSubject $a, ExamSubject $b) => [$a->schoolClass->numeric_level, $a->classSubject->subject->name, $a->section?->name]
                <=> [$b->schoolClass->numeric_level, $b->classSubject->subject->name, $b->section?->name],
        ])->values();
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
}
