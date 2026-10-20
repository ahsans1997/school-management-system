<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Exam\MarksService;
use App\Support\SchoolClock;

/**
 * A teacher's mark sheets still to finish: papers of this year's exams
 * that have been sat, for their own subjects + sections, not yet submitted.
 */
final class PendingMarksWidget extends Widget
{
    private const LIMIT = 8;

    public function key(): string
    {
        return 'pending_marks';
    }

    public function title(): string
    {
        return 'Marks to enter';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Teacher];
    }

    public function permission(): string
    {
        return 'exam.marks_entry';
    }

    protected function data(User $user): array
    {
        if (Teacher::forUser($user) === null) {
            return ['linked' => false, 'total' => 0, 'sheets' => []];
        }
        $year = AcademicYear::current();
        if ($year === null) {
            return ['linked' => true, 'total' => 0, 'sheets' => []];
        }
        $marks = app(MarksService::class);

        $exams = Exam::query()->with('academicYear')
            ->where('academic_year_id', $year->id)
            ->whereDate('start_date', '<=', SchoolClock::date())
            ->orderBy('start_date')
            ->get();

        $pending = $exams->flatMap(function (Exam $exam) use ($marks, $user) {
            $papers = $marks->sheets($user, $exam)->get();
            $sections = Section::query()->whereKey($papers->map(fn (ExamSubject $p) => $p->getAttribute('sheet_section_id')))->get()->keyBy('id');

            return $papers
                ->map(fn (ExamSubject $paper) => [
                    'exam' => ['id' => $exam->id, 'name' => $exam->name],
                    ...$marks->sheetRow($paper->setRelation('exam', $exam), $sections[$paper->getAttribute('sheet_section_id')]),
                ])
                ->filter(fn (array $row) => $row['open'] && $row['status'] !== 'submitted');
        })->values();

        return [
            'linked' => true,
            'total' => $pending->count(),
            'sheets' => $pending->take(self::LIMIT)->all(),
        ];
    }
}
