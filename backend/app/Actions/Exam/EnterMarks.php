<?php

namespace App\Actions\Exam;

use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\Section;
use App\Models\User;
use App\Services\Exam\MarksService;
use App\Support\SchoolClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enter marks as drafts (spec §16–17): a whole sheet (one paper, one
 * section) in one request, or one student's marks. The total is always
 * computed here from what was entered — a total sent by the client is never
 * read (spec §51). Each part is checked against its cap and the total
 * against the full marks. Submitted marks are locked (409) until reopened.
 *
 * Callers authorize first (MarksService::resolve or TeacherScope).
 */
class EnterMarks
{
    private const LABELS = ['cq' => 'CQ', 'mcq' => 'MCQ', 'practical' => 'Practical'];

    public function __construct(private readonly MarksService $marks) {}

    /**
     * Save a sheet. A blank row clears that student's draft mark; not every
     * student needs marks yet (SubmitMarks checks that). Errors are keyed
     * `records.N.field`.
     *
     * @param  array<array-key, array<string, mixed>>  $records
     */
    public function sheet(User $user, ExamSubject $paper, Section $section, array $records): void
    {
        $this->ensureOpen($paper, 'records');
        $records = array_values($records);

        DB::transaction(function () use ($user, $paper, $section, $records) {
            // Saves and submits of the same paper queue up instead of racing.
            ExamSubject::query()->whereKey($paper->id)->lockForUpdate()->first();

            $existing = $this->marks->marksOn($paper, $section)->get()->keyBy('student_id');
            if ($existing->contains(fn (Mark $m) => $m->isLocked())) {
                $this->locked();
            }
            $roster = $this->marks->roster($paper, $section)->keyBy('student_id');

            $errors = [];
            $values = [];
            foreach ($records as $i => $record) {
                $studentId = (int) $record['student_id'];
                // Marks already on the sheet stay editable (e.g. a student archived since).
                if (! $roster->has($studentId) && ! $existing->has($studentId)) {
                    $errors["records.{$i}.student_id"] = "Student #{$studentId} isn't on this section's roll for the paper.";

                    continue;
                }
                $values[$studentId] = $this->evaluate($paper, $record, "records.{$i}.", $errors);
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            foreach ($values as $studentId => $value) {
                $mark = $existing->get($studentId);
                if ($value === null) {
                    $mark?->delete();

                    continue;
                }
                $mark ??= new Mark([
                    'exam_subject_id' => $paper->id,
                    'student_id' => $studentId,
                    'student_enrollment_id' => $roster[$studentId]->id,
                    'entered_by' => $user->id,
                ]);
                $this->write($user, $mark, $value);
            }
        });
    }

    /** Change one student's draft marks (same checks as a sheet row). */
    public function one(User $user, Mark $mark, array $input): Mark
    {
        $paper = $mark->examSubject;
        $this->ensureOpen($paper, 'marks');

        return DB::transaction(function () use ($user, $mark, $paper, $input) {
            ExamSubject::query()->whereKey($paper->id)->lockForUpdate()->first();
            $mark->refresh();
            if ($mark->isLocked()) {
                $this->locked();
            }

            $errors = [];
            $value = $this->evaluate($paper, $input, '', $errors);
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            if ($value === null) {
                throw ValidationException::withMessages(['marks' => 'Enter the marks, or mark the student absent.']);
            }

            $this->write($user, $mark, $value);

            return $mark;
        });
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function write(User $user, Mark $mark, array $value): void
    {
        $mark->fill($value);
        if ($mark->isDirty()) {
            $mark->updated_by = $user->id;
            $mark->save();
        }
    }

    /**
     * What a row stores — the parts, the computed total, absent — or null for
     * a blank row. Problems are added to $errors as "{$at}field".
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, string>  $errors
     * @return array<string, mixed>|null
     */
    private function evaluate(ExamSubject $paper, array $record, string $at, array &$errors): ?array
    {
        $given = [];
        foreach ([...array_keys(ExamSubject::PARTS), 'marks'] as $field) {
            $value = $this->cents($record[$field] ?? null);
            if ($value !== null) {
                $given[$field] = $value;
            }
        }
        $empty = ['cq' => null, 'mcq' => null, 'practical' => null, 'total' => null, 'absent' => false];

        if (! empty($record['absent'])) {
            if ($given !== []) {
                $errors["{$at}absent"] = 'An absent student can\'t have marks. Clear them or untick absent.';

                return null;
            }

            return [...$empty, 'absent' => true];
        }
        if ($given === []) {
            return null;
        }

        $full = (int) $this->cents($paper->full_marks);
        $caps = array_map(fn (string $cap) => (int) $this->cents($cap), $paper->parts());

        // A paper without a split is marked as one figure.
        if ($caps === []) {
            if (array_keys($given) !== ['marks']) {
                $errors["{$at}marks"] = 'This paper isn\'t split into parts; enter the marks as one figure.';

                return null;
            }
            if ($given['marks'] > $full) {
                $errors["{$at}marks"] = "Marks can't be more than the full marks ({$this->figure($full)}).";

                return null;
            }

            return [...$empty, 'total' => $this->decimal($given['marks'])];
        }

        if (isset($given['marks'])) {
            $errors["{$at}marks"] = 'This paper is marked in parts ('.implode(', ', array_map(fn ($p) => self::LABELS[$p], array_keys($caps))).'); enter each part.';

            return null;
        }

        $before = count($errors);
        $values = $empty;
        $total = 0;
        foreach (array_keys(ExamSubject::PARTS) as $part) {
            $label = self::LABELS[$part];
            if (! isset($caps[$part])) {
                if (isset($given[$part])) {
                    $errors["{$at}{$part}"] = "This paper has no {$label} part.";
                }

                continue;
            }
            if (! isset($given[$part])) {
                $errors["{$at}{$part}"] = "Enter {$label} too, or leave the whole row blank.";

                continue;
            }
            if ($given[$part] > $caps[$part]) {
                $errors["{$at}{$part}"] = "{$label} can't be more than {$this->figure($caps[$part])}.";

                continue;
            }
            $values[$part] = $this->decimal($given[$part]);
            $total += $given[$part];
        }
        if (count($errors) > $before) {
            return null;
        }
        // The parts add up to the full marks (SaveExamSubjects), so this is a backstop.
        if ($total > $full) {
            $errors["{$at}marks"] = "The total can't be more than the full marks ({$this->figure($full)}).";

            return null;
        }

        return [...$values, 'total' => $this->decimal($total)];
    }

    /** 422 before the paper is sat. */
    private function ensureOpen(ExamSubject $paper, string $field): void
    {
        $opens = $this->marks->opensOn($paper);
        if ($opens > SchoolClock::date()) {
            throw ValidationException::withMessages([
                $field => 'Marks can be entered from '.Carbon::parse($opens)->format('j M Y').', once the paper is sat.',
            ]);
        }
    }

    private function locked(): never
    {
        abort(409, 'These marks are submitted and locked. An exam admin can reopen them for changes.');
    }

    /** Marks as integer hundredths, so sums and comparisons are exact. */
    private function cents(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) round((float) $value * 100);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** "100", "33.5" — for messages. */
    private function figure(int $cents): string
    {
        return rtrim(rtrim($this->decimal($cents), '0'), '.');
    }
}
