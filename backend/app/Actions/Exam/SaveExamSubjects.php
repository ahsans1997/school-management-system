<?php

namespace App\Actions\Exam;

use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Set every paper one class sits in an exam. Rows are matched to existing
 * papers by subject + sections and updated in place (marks will point at
 * them), new ones are created, and missing ones removed. An empty list takes
 * the class out of the exam. Errors are keyed `subjects.N.field`.
 */
class SaveExamSubjects
{
    /**
     * @param  array<array-key, array<string, mixed>>  $input  from the request; keys may not be 0..n
     */
    public function execute(Exam $exam, SchoolClass $class, array $input): void
    {
        $papers = array_map(fn (array $p) => [
            'class_subject_id' => (int) $p['class_subject_id'],
            'section_id' => isset($p['section_id']) ? (int) $p['section_id'] : null,
            'full_marks' => $this->cents($p['full_marks']),
            'pass_marks' => $this->cents($p['pass_marks']),
            'cq_marks' => $this->cents($p['cq_marks'] ?? null),
            'mcq_marks' => $this->cents($p['mcq_marks'] ?? null),
            'practical_marks' => $this->cents($p['practical_marks'] ?? null),
            'exam_date' => $p['exam_date'] ?? null,
            'start_time' => $p['start_time'] ?? null,
            'end_time' => $p['end_time'] ?? null,
        ], array_values($input));

        $this->validate($exam, $class, $papers);

        DB::transaction(function () use ($exam, $class, $papers) {
            // One configuration save per exam at a time.
            Exam::query()->whereKey($exam->id)->lockForUpdate()->first();

            $existing = ExamSubject::query()->where('exam_id', $exam->id)->where('class_id', $class->id)->get()
                ->keyBy(fn (ExamSubject $s) => $this->key($s->class_subject_id, $s->section_id));
            $wanted = collect($papers)->keyBy(fn (array $p) => $this->key($p['class_subject_id'], $p['section_id']));

            // Marks were entered against the full marks and split, so those
            // are fixed once a paper has marks (pass marks and dates aren't).
            $fixed = [];
            foreach ($papers as $i => $paper) {
                $current = $existing->get($this->key($paper['class_subject_id'], $paper['section_id']));
                if ($current !== null && $this->splitChanged($current, $paper) && $current->marks()->exists()) {
                    $fixed["subjects.{$i}.full_marks"] = 'Marks are already entered for this paper, so its full marks and CQ / MCQ / practical split can\'t change.';
                }
            }
            if ($fixed !== []) {
                throw ValidationException::withMessages($fixed);
            }

            foreach ($existing->diffKeys($wanted) as $removed) {
                try {
                    $removed->delete();
                } catch (QueryException $e) {
                    abort_if($e->getCode() === '23000', 409, 'A paper that already has marks can\'t be removed from the exam.');
                    throw $e;
                }
            }

            foreach ($wanted as $key => $paper) {
                $values = [
                    ...$paper,
                    'full_marks' => $this->decimal($paper['full_marks']),
                    'pass_marks' => $this->decimal($paper['pass_marks']),
                    'cq_marks' => $this->decimal($paper['cq_marks']),
                    'mcq_marks' => $this->decimal($paper['mcq_marks']),
                    'practical_marks' => $this->decimal($paper['practical_marks']),
                ];
                $current = $existing->get($key);
                $current
                    ? $current->update($values)
                    : ExamSubject::create([...$values, 'exam_id' => $exam->id, 'class_id' => $class->id]);
            }

            $exam->touch();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $papers
     */
    private function validate(Exam $exam, SchoolClass $class, array $papers): void
    {
        $subjects = ClassSubject::query()->with('subject')->where('class_id', $class->id)->get()->keyBy('id');
        $sections = Section::query()->where('class_id', $class->id)->get()->keyBy('id');
        $first = $exam->start_date->toDateString();
        $last = $exam->lastDay()->toDateString();
        $errors = [];
        $scopes = [];   // class_subject_id => list of section ids (0 = all) seen so far

        foreach ($papers as $i => $p) {
            $at = "subjects.{$i}";
            $subject = $subjects->get($p['class_subject_id']);
            if ($subject === null) {
                $errors["{$at}.class_subject_id"] = "That subject isn't taught in {$class->name}.";

                continue;
            }
            $name = $subject->subject->name;
            if ($p['section_id'] !== null && ! $sections->has($p['section_id'])) {
                $errors["{$at}.section_id"] = "That section isn't part of {$class->name}.";

                continue;
            }

            $scope = $p['section_id'] ?? 0;
            $seen = $scopes[$p['class_subject_id']] ?? [];
            if (in_array($scope, $seen, true)) {
                $errors["{$at}.class_subject_id"] = "{$name} is listed twice for the same sections.";
            } elseif ($seen !== [] && ($scope === 0 || in_array(0, $seen, true))) {
                $errors["{$at}.section_id"] = "{$name} is set for all sections and for one section. Choose one or the other.";
            }
            $scopes[$p['class_subject_id']][] = $scope;

            if ($p['pass_marks'] > $p['full_marks']) {
                $errors["{$at}.pass_marks"] = 'Pass marks can\'t be more than the full marks.';
            }
            $parts = array_filter([$p['cq_marks'], $p['mcq_marks'], $p['practical_marks']], fn ($v) => $v !== null);
            if ($parts !== [] && array_sum($parts) !== $p['full_marks']) {
                $errors["{$at}.full_marks"] = 'CQ + MCQ + practical must add up to the full marks ('.($p['full_marks'] / 100).').';
            }

            if ($p['exam_date'] !== null && ($p['exam_date'] < $first || $p['exam_date'] > $last)) {
                $errors["{$at}.exam_date"] = $first === $last
                    ? "This exam is on {$first}."
                    : "The date must be within the exam ({$first} to {$last}).";
            }
            if ($p['start_time'] !== null && $p['end_time'] !== null && $p['start_time'] >= $p['end_time']) {
                $errors["{$at}.end_time"] = 'The paper must end after it starts.';
            }
        }

        // Two timed papers on the same day can't overlap for anyone sitting both.
        foreach ($papers as $i => $a) {
            foreach ($papers as $j => $b) {
                if ($j >= $i || isset($errors["subjects.{$i}.end_time"]) || $a['exam_date'] === null || $a['start_time'] === null
                    || $a['exam_date'] !== $b['exam_date'] || $b['start_time'] === null) {
                    continue;
                }
                $sameStudents = $a['section_id'] === null || $b['section_id'] === null || $a['section_id'] === $b['section_id'];
                if ($sameStudents && $a['start_time'] < $b['end_time'] && $b['start_time'] < $a['end_time']) {
                    $other = $subjects->get($b['class_subject_id'])?->subject->name ?? 'another paper';
                    $day = Carbon::parse($a['exam_date'])->format('j M');
                    $errors["subjects.{$i}.start_time"] ??= "Overlaps {$other} ({$b['start_time']}–{$b['end_time']}) on {$day}.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $paper  in hundredths
     */
    private function splitChanged(ExamSubject $current, array $paper): bool
    {
        foreach (['full_marks', 'cq_marks', 'mcq_marks', 'practical_marks'] as $column) {
            if ($this->cents($current->{$column}) !== $paper[$column]) {
                return true;
            }
        }

        return false;
    }

    private function key(int $classSubjectId, ?int $sectionId): string
    {
        return $classSubjectId.':'.($sectionId ?? 0);
    }

    /** Marks as integer hundredths, so sums and comparisons are exact. */
    private function cents(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) round((float) $value * 100);
    }

    private function decimal(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }
}
