<?php

namespace App\Actions\Exam;

use App\Enums\ResultStatus;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Result;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update an exam header. Its dates must fall inside its academic
 * year, and moving them can't leave scheduled papers outside the exam.
 */
class SaveExam
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(?Exam $exam, array $data): Exam
    {
        $year = $exam->academicYear ?? AcademicYear::findOrFail($data['academic_year_id']);
        $start = $data['start_date'];
        $end = $data['end_date'] ?? null;
        $last = $end ?? $start;

        $yearStart = $year->start_date->toDateString();
        $yearEnd = $year->end_date->toDateString();
        if ($start < $yearStart || $start > $yearEnd) {
            throw ValidationException::withMessages(['start_date' => "The exam must start within {$year->name} ({$yearStart} to {$yearEnd})."]);
        }
        if ($last > $yearEnd) {
            throw ValidationException::withMessages(['end_date' => "The exam must end within {$year->name} (by {$yearEnd})."]);
        }

        return DB::transaction(function () use ($exam, $data, $year, $start, $end, $last) {
            $values = [
                'name' => trim($data['name']),
                'type' => $data['type'],
                'grade_scale_id' => $data['grade_scale_id'],
                'start_date' => $start,
                'end_date' => $end,
            ];

            if ($exam === null) {
                $exam = new Exam($values);
                $exam->academic_year_id = $year->id;
                $exam->save();

                return $exam;
            }

            Exam::query()->whereKey($exam->id)->lockForUpdate()->first();
            // Point the error at whichever date strands papers.
            $errors = [];
            foreach (['start_date' => ['<', $start], 'end_date' => ['>', $last]] as $field => [$operator, $bound]) {
                $outside = $exam->subjects()->whereNotNull('exam_date')->where('exam_date', $operator, $bound)->count();
                if ($outside > 0) {
                    $errors[$field] = $outside === 1
                        ? 'A paper is scheduled outside these dates. Move it first.'
                        : "{$outside} papers are scheduled outside these dates. Move them first.";
                }
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            // Results are graded on the exam's scale: switching scales is
            // refused once any class is past draft, and clears draft results.
            if ((int) $values['grade_scale_id'] !== $exam->grade_scale_id) {
                $frozen = Result::query()->where('exam_id', $exam->id)->where('status', '!=', ResultStatus::Draft->value)->exists();
                if ($frozen) {
                    throw ValidationException::withMessages(['grade_scale_id' => 'Results of this exam are in review or published, so its grade scale can\'t change.']);
                }
                Result::query()->where('exam_id', $exam->id)->delete();
            }

            $exam->update($values);

            return $exam;
        });
    }
}
