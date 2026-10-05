<?php

namespace App\Actions\Exam;

use App\Models\AcademicYear;
use App\Models\Exam;
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

            $exam->update($values);

            return $exam;
        });
    }
}
