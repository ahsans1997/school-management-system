<?php

namespace App\Services\Dashboard\Widgets;

use App\Enums\DashboardAudience;
use App\Models\Result;
use App\Models\User;
use App\Services\Dashboard\Widget;
use App\Services\Exam\ResultService;

/**
 * The latest published results of the caller's own student record
 * (`my_results`) or of each of a parent's children (`child_results`).
 * Published only: drafts never reach a portal.
 */
final class PublishedResultsWidget extends Widget
{
    private const LIMIT = 3;

    public function __construct(private readonly DashboardAudience $audience) {}

    public function key(): string
    {
        return $this->audience === DashboardAudience::Parent ? 'child_results' : 'my_results';
    }

    public function title(): string
    {
        return $this->audience === DashboardAudience::Parent ? 'Results' : 'My results';
    }

    public function audiences(): array
    {
        return [$this->audience];
    }

    protected function data(User $user): array
    {
        $mine = app(ResultService::class)->mine($user);

        return [
            'linked' => $mine['students'] !== [],
            'students' => array_map(fn (array $s) => [
                'student' => $s['student'],
                'results' => $s['results']->take(self::LIMIT)->map(fn (Result $r) => [
                    'id' => $r->id,
                    'exam' => ['id' => $r->exam->id, 'name' => $r->exam->name],
                    'class' => $r->schoolClass->name,
                    'section' => $r->section->name,
                    'gpa' => (float) $r->gpa,
                    'grade' => $r->grade,
                    'passed' => $r->passed,
                    'position' => $r->position,
                    'published_at' => $r->published_at?->toIso8601String(),
                ])->values()->all(),
            ], $mine['students']),
        ];
    }
}
