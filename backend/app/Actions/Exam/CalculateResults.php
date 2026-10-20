<?php

namespace App\Actions\Exam;

use App\Enums\MarkStatus;
use App\Enums\ResultStatus;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Grade;
use App\Models\Mark;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Exam\ResultService;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calculate a class's results for an exam from its submitted marks (spec
 * §17), as drafts. All server-side, in hundredths so sums are exact:
 *
 *  - Subject: percentage of the paper's full marks → band on the exam's own
 *    grade scale, compared exactly. Below the paper's pass marks (or absent)
 *    fails, and gets the scale's bottom band whatever the percentage says;
 *    the bottom band is the fail band, so a mark landing in it fails too.
 *  - Overall: GPA = mean of the subject grade points, 0 with any failed
 *    subject; the letter is the band with the highest point ≤ GPA (the
 *    bottom band on a fail).
 *  - Merit: students who passed every subject, by GPA then total marks;
 *    equal GPA and total share a position (1, 2, 2, 4). Within the section
 *    (`position`) and across the class (`class_position`).
 *
 * Students are those with submitted marks on the class's papers; a paper a
 * student has no mark for counts as absent. Recalculating replaces the
 * draft results; once in review or beyond it's refused (409).
 */
class CalculateResults
{
    public function __construct(private readonly ResultService $results) {}

    /** @return int how many results were written */
    public function execute(User $user, Exam $exam, SchoolClass $class): int
    {
        return DB::transaction(function () use ($user, $exam, $class) {
            // One calculation or workflow step per exam at a time.
            Exam::query()->whereKey($exam->id)->lockForUpdate()->first();

            if ($status = $this->results->frozen($exam, $class->id)) {
                abort(409, "Results for {$class->name} are {$status->label()}; return them to draft before recalculating.");
            }

            $papers = $exam->subjects()->with('classSubject.subject')->where('class_id', $class->id)->orderBy('id')->get();
            if ($papers->isEmpty()) {
                $this->fail("{$class->name} has no papers in this exam.");
            }
            $pending = $this->results->sheets($exam, $class->id)->where('status', '!=', 'submitted');
            if ($pending->isNotEmpty()) {
                $names = $pending->map(fn (array $s) => "{$s['section']->name} {$s['paper']->classSubject->subject->name}");
                $this->fail("Submit all of {$class->name}'s marks first. Not submitted yet: "
                    .$names->take(5)->join(', ').($names->count() > 5 ? ' and '.($names->count() - 5).' more' : '').'.');
            }

            $scale = $exam->gradeScale()->with('grades')->firstOrFail();
            $grades = $scale->grades; // highest band first
            $bottom = $grades->sortBy(fn (Grade $g) => (float) $g->min_percent)->first();

            $marks = Mark::query()->with(['enrollment', 'student'])
                ->whereIn('exam_subject_id', $papers->modelKeys())
                ->where('status', MarkStatus::Submitted->value)
                ->get()
                ->groupBy('student_id');

            // A student's marks must all be on papers their section sits
            // (enrollment moves are blocked once marks exist; this is a backstop).
            $misplaced = $marks->filter(function (Collection $studentMarks) use ($papers) {
                $sectionId = $studentMarks->first()->enrollment->section_id;

                return $studentMarks->contains(fn (Mark $m) => ($paper = $papers->find($m->exam_subject_id)) && $paper->section_id !== null && $paper->section_id !== $sectionId);
            });
            if ($misplaced->isNotEmpty()) {
                $this->fail('Some students have marks on papers their section doesn\'t sit: '
                    .$misplaced->map(fn (Collection $m) => $m->first()->student->name)->join(', ').'. Fix their marks or enrollment first.');
            }

            $rows = $marks->map(function (Collection $studentMarks) use ($papers, $grades, $bottom) {
                $enrollment = $studentMarks->first()->enrollment;
                $byPaper = $studentMarks->keyBy('exam_subject_id');
                $sat = $papers->filter(fn (ExamSubject $p) => $p->section_id === null || $p->section_id === $enrollment->section_id);

                $details = $sat->map(function (ExamSubject $paper) use ($byPaper, $grades, $bottom) {
                    $mark = $byPaper->get($paper->id);
                    $full = $this->cents($paper->full_marks);
                    $obtained = $mark !== null && ! $mark->absent ? $this->cents($mark->total) : null;
                    $percent = $obtained === null ? 0 : intdiv($obtained * 10000 + intdiv($full, 2), $full); // hundredths of a %, for display
                    // The band from exact integer maths (79.997 % is not 80 %);
                    // the bottom band is the fail band, so a mark at or above
                    // the paper's pass marks that still lands there fails too.
                    $grade = $obtained === null ? $bottom : $this->band($obtained, $full, $grades) ?? $bottom;
                    $passed = $obtained !== null && $obtained >= $this->cents($paper->pass_marks) && $grade->isNot($bottom);
                    $grade = $passed ? $grade : $bottom;

                    return [
                        'exam_subject_id' => $paper->id,
                        'subject' => $paper->classSubject->subject->name,
                        'subject_code' => $paper->classSubject->subject->code,
                        'full_marks' => $paper->full_marks,
                        'pass_marks' => $paper->pass_marks,
                        'cq' => $mark?->cq,
                        'mcq' => $mark?->mcq,
                        'practical' => $mark?->practical,
                        'obtained' => $obtained === null ? null : $this->decimal($obtained),
                        'absent' => $obtained === null,
                        'percentage' => $this->decimal($percent),
                        'grade' => $grade->name,
                        'grade_point' => $grade->grade_point,
                        'passed' => $passed,
                        // For the totals below.
                        '_obtained' => $obtained ?? 0,
                        '_full' => $full,
                        '_point' => $this->cents($grade->grade_point),
                    ];
                })->values();

                $failed = $details->where('passed', false)->count() + ($details->isEmpty() ? 1 : 0);
                $total = $details->sum('_obtained');
                $full = $details->sum('_full');
                $gpa = $failed > 0 || $details->isEmpty() ? 0 : intdiv(2 * $details->sum('_point') + $details->count(), 2 * $details->count());
                $overall = $failed > 0
                    ? $bottom
                    : ($grades->sortByDesc(fn (Grade $g) => (float) $g->grade_point)->first(fn (Grade $g) => $this->cents($g->grade_point) <= $gpa) ?? $bottom);

                return [
                    'enrollment' => $enrollment,
                    'details' => $details,
                    'total' => $total,
                    'full' => $full,
                    'gpa' => $gpa,
                    'grade' => $overall->name,
                    'passed' => $failed === 0,
                    'failed' => $failed,
                ];
            })->values();

            $positions = $this->rank($rows, fn (array $r) => $r['enrollment']->section_id);
            $classPositions = $this->rank($rows, fn () => 0);

            Result::query()->where('exam_id', $exam->id)->where('class_id', $class->id)->delete();
            $now = now();
            foreach ($rows as $i => $row) {
                $result = (new Result)->forceFill([
                    'exam_id' => $exam->id,
                    'student_id' => $row['enrollment']->student_id,
                    'student_enrollment_id' => $row['enrollment']->id,
                    'class_id' => $class->id,
                    'section_id' => $row['enrollment']->section_id,
                    'total_marks' => $this->decimal($row['total']),
                    'full_marks' => $this->decimal($row['full']),
                    'percentage' => $this->decimal($row['full'] === 0 ? 0 : intdiv($row['total'] * 10000 + intdiv($row['full'], 2), $row['full'])),
                    'gpa' => $this->decimal($row['gpa']),
                    'grade' => $row['grade'],
                    'passed' => $row['passed'],
                    'failed_subjects' => $row['failed'],
                    'position' => $positions[$i] ?? null,
                    'class_position' => $classPositions[$i] ?? null,
                    'status' => ResultStatus::Draft,
                    'calculated_at' => $now,
                    'calculated_by' => $user->id,
                ]);
                $result->save();
                foreach ($row['details'] as $detail) {
                    $result->details()->make()->forceFill(array_filter($detail, fn ($key) => ! str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY))->save();
                }
            }

            Audit::record($user, 'results.calculated', 'results', $exam, new: [
                'class_id' => $class->id,
                'students' => $rows->count(),
                'passed' => $rows->where('passed', true)->count(),
            ]);

            return $rows->count();
        });
    }

    /**
     * Competition ranking of the students who passed, by GPA then total,
     * within each group. Keys match $rows; failed students get no entry.
     *
     * @param  Collection<int, covariant array<string, mixed>>  $rows
     * @return array<int, int>
     */
    private function rank(Collection $rows, callable $group): array
    {
        $positions = [];
        $rows->filter(fn (array $r) => $r['passed'])
            ->groupBy($group, preserveKeys: true)
            ->each(function (Collection $members) use (&$positions) {
                $sorted = $members->sortBy([['gpa', 'desc'], ['total', 'desc']]);
                $rank = 0;
                $seen = 0;
                $previous = null;
                foreach ($sorted as $key => $row) {
                    $seen++;
                    if ($previous === null || $previous['gpa'] !== $row['gpa'] || $previous['total'] !== $row['total']) {
                        $rank = $seen;
                    }
                    $positions[$key] = $rank;
                    $previous = $row;
                }
            });

        return $positions;
    }

    /**
     * The highest band whose cut-off the mark reaches, compared exactly:
     * obtained / full >= min% / 100, all in hundredths.
     *
     * @param  Collection<int, Grade>  $grades  highest band first
     */
    private function band(int $obtained, int $full, Collection $grades): ?Grade
    {
        return $grades->first(fn (Grade $g) => $obtained * 10000 >= $this->cents($g->min_percent) * $full);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['class_id' => $message]);
    }

    /** Hundredths as an integer, so sums and comparisons are exact. */
    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
