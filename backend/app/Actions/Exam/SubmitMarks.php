<?php

namespace App\Actions\Exam;

use App\Enums\MarkStatus;
use App\Enums\ResultStatus;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Result;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Exam\MarksService;
use App\Services\Exam\ResultService;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move a sheet (one paper, one section) through the marks workflow (spec
 * §56): submit locks the drafts for results once every student on the roll
 * has marks or is marked absent; reopen (exam.edit) turns them back into
 * drafts for corrections. Both are idempotent and audited.
 *
 * Reopening is refused while the class's results are in review or beyond,
 * and clears its draft results (they'd no longer match the marks).
 */
class SubmitMarks
{
    public function __construct(
        private readonly MarksService $marks,
        private readonly ResultService $results,
    ) {}

    public function submit(User $user, ExamSubject $paper, Section $section): void
    {
        DB::transaction(function () use ($user, $paper, $section) {
            $this->lock($paper);

            $marks = $this->marks->marksOn($paper, $section)->get()->keyBy('student_id');
            $roster = $this->marks->roster($paper, $section);
            if ($roster->isEmpty() && $marks->isEmpty()) {
                throw ValidationException::withMessages(['section_id' => 'There are no students on this section\'s roll to submit marks for.']);
            }

            $missing = $roster->reject(fn (StudentEnrollment $e) => $marks->has($e->student_id));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'records' => 'Enter marks (or mark absent) for every student before submitting. Missing: '
                        .$missing->map(fn (StudentEnrollment $e) => $e->student->name)->join(', ').'.',
                ]);
            }

            $count = $this->marks->marksOn($paper, $section)
                ->where('status', MarkStatus::Draft->value)
                ->update([
                    'status' => MarkStatus::Submitted->value,
                    'submitted_at' => now(),
                    'submitted_by' => $user->id,
                ]);

            if ($count > 0) {
                Audit::record($user, 'marks.submitted', 'marks', $paper, new: ['section_id' => $section->id, 'marks' => $count]);
            }
        });
    }

    public function reopen(User $user, ExamSubject $paper, Section $section): void
    {
        DB::transaction(function () use ($user, $paper, $section) {
            $this->lock($paper);

            if ($status = $this->results->frozen($paper->exam, $paper->class_id)) {
                abort(409, "The class's results are {$status->label()}. Return them to draft before reopening marks.");
            }

            $submitted = $this->marks->marksOn($paper, $section)->where('status', MarkStatus::Submitted->value);
            $before = (clone $submitted)->first(['submitted_at', 'submitted_by']);
            $count = $submitted->update([
                'status' => MarkStatus::Draft->value,
                'submitted_at' => null,
                'submitted_by' => null,
            ]);
            if ($count === 0) {
                return;
            }

            // Draft results were calculated from the marks now reopened.
            $cleared = Result::query()->where('exam_id', $paper->exam_id)->where('class_id', $paper->class_id)
                ->where('status', ResultStatus::Draft->value)->delete();

            Audit::record($user, 'marks.reopened', 'marks', $paper,
                old: ['section_id' => $section->id, 'submitted_at' => $before?->submitted_at?->toIso8601String(), 'submitted_by' => $before?->submitted_by],
                new: ['section_id' => $section->id, 'marks' => $count, 'draft_results_cleared' => $cleared],
            );
        });
    }

    /**
     * The exam row first, then the paper — the order calculation, the results
     * workflow and paper edits use — so a reopen can't interleave with a
     * results step and leave results past draft over reopened marks.
     */
    private function lock(ExamSubject $paper): void
    {
        Exam::query()->whereKey($paper->exam_id)->lockForUpdate()->first();
        ExamSubject::query()->whereKey($paper->id)->lockForUpdate()->first();
    }
}
