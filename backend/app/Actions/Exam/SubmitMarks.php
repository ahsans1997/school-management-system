<?php

namespace App\Actions\Exam;

use App\Enums\MarkStatus;
use App\Models\ExamSubject;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\Exam\MarksService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move a sheet (one paper, one section) through the marks workflow (spec
 * §56): submit locks the drafts for results (Module 18) once every student
 * on the roll has marks or is marked absent; reopen (exam.edit) turns them
 * back into drafts for corrections. Both are idempotent.
 */
class SubmitMarks
{
    public function __construct(private readonly MarksService $marks) {}

    public function submit(User $user, ExamSubject $paper, Section $section): void
    {
        DB::transaction(function () use ($user, $paper, $section) {
            ExamSubject::query()->whereKey($paper->id)->lockForUpdate()->first();

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

            $this->marks->marksOn($paper, $section)
                ->where('status', MarkStatus::Draft->value)
                ->update([
                    'status' => MarkStatus::Submitted->value,
                    'submitted_at' => now(),
                    'submitted_by' => $user->id,
                ]);
        });
    }

    public function reopen(ExamSubject $paper, Section $section): void
    {
        DB::transaction(function () use ($paper, $section) {
            ExamSubject::query()->whereKey($paper->id)->lockForUpdate()->first();

            $this->marks->marksOn($paper, $section)
                ->where('status', MarkStatus::Submitted->value)
                ->update([
                    'status' => MarkStatus::Draft->value,
                    'submitted_at' => null,
                    'submitted_by' => null,
                ]);
        });
    }
}
