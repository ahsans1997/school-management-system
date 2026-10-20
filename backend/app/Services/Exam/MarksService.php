<?php

namespace App\Services\Exam;

use App\Enums\MarkStatus;
use App\Http\Resources\ExamSubjectResource;
use App\Http\Resources\MarkResource;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolClock;
use App\Support\TeacherScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Validation\ValidationException;

/**
 * Mark sheets: one paper (exam_subject) as sat by one section. Resolves and
 * authorizes a sheet (two layers: exam.marks_entry by route, then the
 * teacher's own teaching assignment for that subject + section), and reads
 * its roll and marks.
 */
class MarksService
{
    public function __construct(private readonly ExamScheduleService $schedules) {}

    /**
     * The sheet for a paper of $exam and a section: the section must sit the
     * paper (same class; the paper is class-wide or that section's own), and
     * a teacher must teach the subject there (403).
     *
     * @return array{0: ExamSubject, 1: Section}
     */
    public function resolve(User $user, Exam $exam, int $examSubjectId, int $sectionId): array
    {
        $this->authorizeExam($user, $exam);
        $paper = ExamSubject::query()->with(['schoolClass', 'section', 'classSubject.subject'])
            ->where('exam_id', $exam->id)->findOrFail($examSubjectId);
        $section = Section::query()->with('schoolClass')->findOrFail($sectionId);

        if ($section->class_id !== $paper->class_id || ($paper->section_id !== null && $paper->section_id !== $section->id)) {
            throw ValidationException::withMessages(['section_id' => "{$section->schoolClass->name} {$section->name} doesn't sit this paper."]);
        }
        TeacherScope::authorizeSubject($user, $section, $paper->class_subject_id);
        $paper->setRelation('exam', $exam);

        return [$paper, $section];
    }

    /**
     * Teaching assignments carry no year, so a scoped teacher works only on
     * the current year's exams: older papers belong to whoever taught them
     * then, and only exam admins change them (403).
     */
    public function authorizeExam(User $user, Exam $exam): void
    {
        abort_if($this->outOfYear($user, $exam), 403, 'Marks for exams of past years can only be changed by an exam admin.');
    }

    private function outOfYear(User $user, Exam $exam): bool
    {
        return TeacherScope::subjectScoped($user) && $exam->academic_year_id !== AcademicYear::current()?->id;
    }

    /** The first day marks can be entered: the paper's date, or the exam's start when it isn't scheduled. */
    public function opensOn(ExamSubject $paper): string
    {
        return $paper->exam_date ?? $paper->exam->start_date->toDateString();
    }

    public function isOpen(ExamSubject $paper): bool
    {
        return $this->opensOn($paper) <= SchoolClock::date();
    }

    /**
     * Who sits the paper in this section: the exam year's roll on the paper's
     * day (so students who left before it aren't listed), by roll.
     *
     * @return Collection<int, StudentEnrollment>
     */
    public function roster(ExamSubject $paper, Section $section, bool $lock = false): Collection
    {
        return StudentEnrollment::query()->with('student')
            ->onRoll($section, $paper->exam->academicYear, $this->opensOn($paper))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->get();
    }

    /**
     * Marks already entered on the sheet (students whose enrollment that
     * year is in this section).
     *
     * @return Builder<Mark>
     */
    public function marksOn(ExamSubject $paper, Section $section): Builder
    {
        return Mark::query()
            ->where('exam_subject_id', $paper->id)
            ->whereHas('enrollment', fn ($q) => $q->where('section_id', $section->id));
    }

    /**
     * The sheet to fill in: the roll with any marks saved, plus marks of
     * students no longer on it (e.g. archived since).
     *
     * @return array<string, mixed>
     */
    public function sheet(User $user, ExamSubject $paper, Section $section): array
    {
        $marks = $this->marksOn($paper, $section)->with(['student', 'enrollment', 'submittedBy'])->get()->keyBy('student_id');
        $roster = $this->roster($paper, $section);

        $rows = $roster->map(fn (StudentEnrollment $e) => [
            'student_id' => $e->student_id,
            'name' => $e->student->name,
            'roll' => $e->roll,
            'mark' => $marks->has($e->student_id) ? (new MarkResource($marks[$e->student_id]))->resolve() : null,
        ]);
        $extra = $marks->except($roster->pluck('student_id')->all())->map(fn (Mark $m) => [
            'student_id' => $m->student_id,
            'name' => $m->student->name,
            'roll' => null,
            'mark' => (new MarkResource($m))->resolve(),
        ]);

        $status = $this->status($marks);
        $submitted = $marks->first(fn (Mark $m) => $m->isLocked());
        $open = $this->isOpen($paper);

        return [
            'exam' => $this->schedules->header($paper->exam),
            'paper' => (new ExamSubjectResource($paper))->resolve(),
            'section' => ['id' => $section->id, 'name' => $section->name, 'class' => $section->schoolClass->name],
            'parts' => array_keys($paper->parts()),
            'opens_on' => $this->opensOn($paper),
            'open' => $open,
            'status' => $status,
            'submitted' => $submitted ? [
                'at' => $submitted->submitted_at?->toIso8601String(),
                'by' => $submitted->submittedBy?->name,
            ] : null,
            'can_edit' => $open && $status !== 'submitted',
            'can_reopen' => $status === 'submitted' && $user->can('exam.edit'),
            'summary' => [
                'students' => $roster->count(),
                'entered' => $marks->count(),
                'absent' => $marks->where('absent', true)->count(),
            ],
            'students' => $rows->concat($extra->values())->values()->all(),
        ];
    }

    /**
     * The sheets of an exam the user may work on, paginated: every paper ×
     * section that sits it (a class-wide paper once per section), limited to
     * a teacher's own subject + section pairs in the current year's exams. By class, section, subject;
     * each row carries its section as `sheet_section_id`.
     *
     * @return Builder<ExamSubject>
     */
    public function sheets(User $user, Exam $exam, ?int $classId = null): Builder
    {
        $teacherId = TeacherScope::subjectScoped($user) ? (Teacher::forUser($user)->id ?? 0) : null;

        return ExamSubject::query()
            ->with(['schoolClass', 'classSubject.subject'])
            ->join('sections', 'sections.class_id', '=', 'exam_subjects.class_id')
            ->join('classes', 'classes.id', '=', 'exam_subjects.class_id')
            ->join('class_subjects', 'class_subjects.id', '=', 'exam_subjects.class_subject_id')
            ->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
            ->where('exam_subjects.exam_id', $exam->id)
            ->when($this->outOfYear($user, $exam), fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q->whereNull('exam_subjects.section_id')->orWhereColumn('exam_subjects.section_id', 'sections.id'))
            ->when($teacherId !== null, fn ($q) => $q->whereExists(fn ($s) => $s->from('teaching_assignments')
                ->whereColumn('teaching_assignments.section_id', 'sections.id')
                ->whereColumn('teaching_assignments.class_subject_id', 'exam_subjects.class_subject_id')
                ->where('teaching_assignments.teacher_id', $teacherId)))
            ->when($classId, fn ($q, int $id) => $q->where('exam_subjects.class_id', $id))
            ->select('exam_subjects.*', 'sections.id as sheet_section_id')
            ->orderBy('classes.numeric_level')
            ->orderBy('sections.name')
            ->orderBy('subjects.name');
    }

    /**
     * One row of the sheets list: a paper (exam relation set) and a section
     * that sits it, with progress.
     *
     * @return array<string, mixed>
     */
    public function sheetRow(ExamSubject $paper, Section $section): array
    {
        $marks = $this->marksOn($paper, $section)->get(['id', 'status']);

        return [
            'exam_subject_id' => $paper->id,
            'class' => ['id' => $paper->schoolClass->id, 'name' => $paper->schoolClass->name],
            'section' => ['id' => $section->id, 'name' => $section->name],
            'subject' => [
                'id' => $paper->classSubject->subject->id,
                'name' => $paper->classSubject->subject->name,
                'code' => $paper->classSubject->subject->code,
            ],
            'full_marks' => (float) $paper->full_marks,
            'exam_date' => $paper->exam_date,
            'opens_on' => $this->opensOn($paper),
            'open' => $this->isOpen($paper),
            'students' => StudentEnrollment::query()
                ->onRoll($section, $paper->exam->academicYear, $this->opensOn($paper))
                ->count(),
            'entered' => $marks->count(),
            'status' => $this->status($marks),
        ];
    }

    /**
     * 'submitted' once the sheet is submitted, 'draft' while marks are being
     * entered, 'not_started' before any are.
     *
     * @param  BaseCollection<array-key, Mark>  $marks
     */
    public function status(BaseCollection $marks): string
    {
        return match (true) {
            $marks->contains(fn (Mark $m) => $m->status === MarkStatus::Submitted) => 'submitted',
            $marks->isNotEmpty() => 'draft',
            default => 'not_started',
        };
    }
}
