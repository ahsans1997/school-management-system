<?php

namespace Tests\Feature\Exam;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\Result;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

class ResultsTest extends ExamMarksTestCase
{
    private Student $nadia;

    private Student $tania;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nadia = $this->student('Nadia');
        $this->tania = $this->student('Tania');
        $this->enroll($this->nadia, $this->sixA)->assertCreated();
        $this->enroll($this->tania, $this->sixB)->assertCreated();
        $this->travelTo('2026-11-19 09:00'); // every paper has been sat
    }

    /**
     * Rahim and Nadia: A+ everywhere (tie). Karim fails Bangla and misses
     * Physics. 6B: Tania ahead of Sumi. Every sheet submitted unless held back.
     */
    private function enterMarks(array $holdBack = []): void
    {
        $sheets = [
            'bangla-A' => [$this->bangla, $this->sixA, [
                ['student_id' => $this->rahim->id, 'marks' => 85],
                ['student_id' => $this->karim->id, 'marks' => 30],
                ['student_id' => $this->nadia->id, 'marks' => 85],
            ]],
            'maths-A' => [$this->maths, $this->sixA, [
                ['student_id' => $this->rahim->id, 'cq' => 60, 'mcq' => 25],
                ['student_id' => $this->karim->id, 'cq' => 50, 'mcq' => 20],
                ['student_id' => $this->nadia->id, 'cq' => 60, 'mcq' => 25],
            ]],
            'physics-A' => [$this->physics, $this->sixA, [
                ['student_id' => $this->rahim->id, 'cq' => 20, 'mcq' => 10, 'practical' => 10],
                ['student_id' => $this->karim->id, 'absent' => true],
                ['student_id' => $this->nadia->id, 'cq' => 20, 'mcq' => 10, 'practical' => 10],
            ]],
            'bangla-B' => [$this->bangla, $this->sixB, [
                ['student_id' => $this->sumi->id, 'marks' => 72],
                ['student_id' => $this->tania->id, 'marks' => 81],
            ]],
            'maths-B' => [$this->maths, $this->sixB, [
                ['student_id' => $this->sumi->id, 'cq' => 45, 'mcq' => 20],
                ['student_id' => $this->tania->id, 'cq' => 46, 'mcq' => 20],
            ]],
        ];
        foreach ($sheets as $key => [$paper, $section, $records]) {
            $this->save($this->admin, $paper, $section, $records)->assertOk();
            if (! in_array($key, $holdBack, true)) {
                $this->sheetAction($this->admin, 'submit', $paper, $section)->assertOk();
            }
        }
    }

    private function step(User $as, string $action): TestResponse
    {
        return $this->asUser($as)->postJson("/api/exams/{$this->exam->id}/results/{$action}", ['class_id' => $this->six->id]);
    }

    private function resultOf(Student $student): Result
    {
        return Result::query()->where('student_id', $student->id)->sole();
    }

    public function test_results_are_calculated_from_submitted_marks(): void
    {
        $this->enterMarks(holdBack: ['maths-B']);
        $this->step($this->admin, 'calculate')->assertUnprocessable()
            ->assertJsonFragment(["Submit all of Class 6's marks first. Not submitted yet: B Mathematics."]);
        $this->sheetAction($this->admin, 'submit', $this->maths, $this->sixB)->assertOk();
        $this->step($this->teacher, 'calculate')->assertForbidden(); // result.view only

        $classes = $this->step($this->admin, 'calculate')->assertOk()->json('data');
        $this->assertSame(['draft', 5, 4, 5, 5], [
            $classes[0]['status'], $classes[0]['students'], $classes[0]['passed'], $classes[0]['sheets']['total'], $classes[0]['sheets']['submitted'],
        ]);

        // Merit list for the class: the tie shares 1st, failures last without a position.
        $list = $this->asUser($this->admin)->getJson("/api/exams/{$this->exam->id}/results?class_id={$this->six->id}")->assertOk()->json('data');
        $this->assertSame(['Rahim', 'Nadia', 'Tania', 'Sumi', 'Karim'], array_column(array_column($list, 'student'), 'name'));
        $this->assertSame([1, 1, 3, 4, null], array_column($list, 'class_position'));
        $this->assertSame([1, 1, 1, 2, null], array_column($list, 'position'));
        $this->assertEquals([5, 5, 4.25, 3.75, 0], array_column($list, 'gpa'));
        $this->assertSame(['A+', 'A+', 'A', 'A-', 'F'], array_column($list, 'grade'));
        $this->assertEquals([210, 250, 84], [$list[0]['total_marks'], $list[0]['full_marks'], $list[0]['percentage']]);
        $this->assertSame([false, 2], [$list[4]['passed'], $list[4]['failed_subjects']]);

        // A section's list is in section merit order.
        $sixB = $this->asUser($this->admin)->getJson("/api/exams/{$this->exam->id}/results?class_id={$this->six->id}&section_id={$this->sixB->id}")->json('data');
        $this->assertSame(['Tania', 'Sumi'], array_column(array_column($sixB, 'student'), 'name'));

        // Report cards: the split per subject; absent and below pass marks fail with F.
        $card = $this->asUser($this->admin)->getJson('/api/results/'.$this->resultOf($this->rahim)->id.'/report-card')->assertOk()->json('data');
        $this->assertSame(['Bangla', 'Mathematics', 'Physics'], array_column($card['subjects'], 'subject'));
        $this->assertEquals([20, 10, 10, 40, 80, 'A+', 5], array_values(array_intersect_key($card['subjects'][2], array_flip(['cq', 'mcq', 'practical', 'obtained', 'percentage', 'grade', 'grade_point']))));
        $this->assertSame([3, 5], [$card['section_students'], $card['class_students']]);
        $karim = $this->asUser($this->admin)->getJson('/api/results/'.$this->resultOf($this->karim)->id.'/report-card')->json('data.subjects');
        $this->assertEquals([[30, false, 'F'], [70, true, 'A'], [null, false, 'F']], array_map(fn ($s) => [$s['obtained'], $s['passed'], $s['grade']], $karim));
        $this->assertTrue($karim[2]['absent']);

        // Tabulation and statistics.
        $tab = $this->asUser($this->admin)->getJson("/api/exams/{$this->exam->id}/results/tabulation?class_id={$this->six->id}")->assertOk()->json('data');
        $this->assertSame(['Bangla', 'Mathematics', 'Physics'], array_column($tab['subjects'], 'subject'));
        $this->assertCount(5, $tab['rows']);
        $this->assertTrue($tab['rows'][4]['subjects'][$this->physics->id]['absent']);
        $this->assertArrayNotHasKey($this->physics->id, $tab['rows'][2]['subjects']); // 6B doesn't sit Physics
        $stats = $this->asUser($this->admin)->getJson("/api/exams/{$this->exam->id}/results/statistics?class_id={$this->six->id}")->assertOk()->json('data');
        $this->assertEquals([5, 4, 1, 80, 3.6], [$stats['students'], $stats['passed'], $stats['failed'], $stats['pass_rate'], $stats['average_gpa']]);
        $this->assertSame(['A', 'B'], array_column($stats['sections'], 'name')); // the class's sections, for the filter
        $this->assertEquals([[3, 2], [2, 2]], array_map(fn ($s) => [$s['students'], $s['passed']], $stats['by_section']));
        $this->assertEquals(['subject' => 'Physics', 'students' => 3, 'absent' => 1, 'passed' => 2, 'highest' => 40], array_intersect_key($stats['subjects'][2], array_flip(['subject', 'students', 'absent', 'passed', 'highest'])));

        // Teachers see only their sections: Lina teaches 6B Bangla.
        $lina = Teacher::create(['name' => 'Lina', 'employee_id' => 'T-2', 'joining_date' => '2021-01-01', 'phone' => '01700000002']);
        TeachingAssignment::create(['teacher_id' => $lina->id, 'section_id' => $this->sixB->id, 'class_subject_id' => $this->cs['Bangla']->id]);
        $linaLogin = $this->loginFor('teacher', $lina->id, 'Teacher', 'Lina');
        $this->asUser($linaLogin)->getJson("/api/exams/{$this->exam->id}/results?class_id={$this->six->id}")->assertOk()->assertJsonCount(2, 'data');
        $this->asUser($linaLogin)->getJson('/api/results/'.$this->resultOf($this->rahim)->id.'/report-card')->assertNotFound();
        $this->asUser($this->teacher)->getJson("/api/exams/{$this->exam->id}/results?class_id={$this->six->id}")->assertJsonCount(5, 'data');
    }

    public function test_results_reach_students_only_once_reviewed_approved_and_published(): void
    {
        $this->enterMarks();
        $this->step($this->admin, 'calculate')->assertOk();

        $studentLogin = $this->loginFor('student', $this->rahim->id, 'Student', 'Rahim');
        $guardian = Guardian::create(['name' => 'Rahim\'s father', 'phone' => '01800000000']);
        $guardian->students()->attach($this->rahim->id, ['relationship' => 'father', 'is_primary' => true]);
        $parent = $this->loginFor('guardian', $guardian->id, 'Parent', 'Father');
        $mine = fn (User $as) => $this->asUser($as)->getJson('/api/results/me')->assertOk()->json('data.students.0.results');
        $this->assertSame([], $mine($studentLogin)); // drafts never reach a portal
        $this->asUser($studentLogin)->getJson('/api/results/'.$this->resultOf($this->rahim)->id.'/report-card')->assertForbidden();

        // Wrong order and wrong people are refused.
        $principal = $this->userWithRole('Principal');
        $this->step($principal, 'approve')->assertConflict();
        $this->step($principal, 'review')->assertForbidden(); // no result.process
        $this->step($this->teacher, 'approve')->assertForbidden();

        $this->step($this->admin, 'review')->assertOk()->assertJsonPath('data.0.status', 'review');
        // In review: no recalculation, no reopening marks, no paper changes.
        $this->step($this->admin, 'calculate')->assertConflict();
        $this->sheetAction($this->admin, 'reopen', $this->bangla, $this->sixA)->assertConflict();
        $this->asUser($this->admin)->putJson("/api/exams/{$this->exam->id}/classes/{$this->six->id}/subjects", ['subjects' => []])->assertConflict();

        $this->step($principal, 'approve')->assertOk();
        $this->step($principal, 'publish')->assertOk()->assertJsonPath('data.0.status', 'published');
        $this->assertNotNull($this->resultOf($this->rahim)->published_at);

        $published = $mine($studentLogin);
        $this->assertEquals(['Half Yearly', 5, 'A+', 1], [$published[0]['exam']['name'], $published[0]['gpa'], $published[0]['grade'], $published[0]['position']]);
        $this->assertSame('Half Yearly', $mine($parent)[0]['exam']['name']);
        $widget = collect($this->asUser($parent)->getJson('/api/dashboard')->json('data.widgets'))->firstWhere('key', 'child_results');
        $this->assertSame(['ready', 'A+'], [$widget['status'], $widget['data']['students'][0]['results'][0]['grade']]);

        // Grades are snapshotted: editing the scale later doesn't change a published card.
        Grade::query()->where('name', 'A+')->update(['name' => 'A*']);
        $this->assertSame('A+', $mine($studentLogin)[0]['subjects'][0]['grade']);

        // Every step is audited.
        $this->assertSame(
            ['results.calculated', 'results.sent_for_review', 'results.approved', 'results.published'],
            AuditLog::query()->where('module', 'results')->orderBy('id')->pluck('action')->all(),
        );
        $this->assertSame($principal->id, AuditLog::query()->where('action', 'results.published')->sole()->user_id);

        // Unpublish to correct: off the portals, then back to draft, then marks can reopen.
        $this->step($principal, 'unpublish')->assertOk()->assertJsonPath('data.0.status', 'approved');
        $this->assertSame([], $mine($studentLogin));
        $this->step($principal, 'return')->assertOk()->assertJsonPath('data.0.status', 'draft');
        $this->sheetAction($this->admin, 'reopen', $this->bangla, $this->sixA)->assertOk();
        $this->assertSame(0, Result::query()->count()); // stale drafts cleared
        $this->asUser($this->admin)->getJson("/api/exams/{$this->exam->id}/results/classes")->assertJsonPath('data.0.status', null)
            ->assertJsonPath('data.0.sheets.submitted', 4);
    }

    public function test_a_mark_in_the_fail_band_fails_even_at_or_above_the_pass_marks(): void
    {
        // Bangla's pass marks below the scale's D cut-off (33 %): Karim's 30 is "above pass" but graded F.
        $this->bangla->update(['pass_marks' => 25]);
        $this->enterMarks();
        $this->step($this->admin, 'calculate')->assertOk();

        $bangla = $this->asUser($this->admin)->getJson('/api/results/'.$this->resultOf($this->karim)->id.'/report-card')->json('data.subjects.0');
        $this->assertSame([false, 'F'], [$bangla['passed'], $bangla['grade']]);
    }

    public function test_results_fail_closed_and_stay_in_step_with_their_marks(): void
    {
        $this->enterMarks();
        $exams = "/api/exams/{$this->exam->id}";

        // Processing is for exam admins: a teacher given result.process still can't.
        Role::findByName('Teacher', 'api')->givePermissionTo('result.process');
        $this->step($this->teacher, 'calculate')->assertForbidden();
        $this->step($this->admin, 'calculate')->assertOk();

        // A teacher's overview counts only their own sections (Lina: 6B).
        $lina = Teacher::create(['name' => 'Lina', 'employee_id' => 'T-2', 'joining_date' => '2021-01-01', 'phone' => '01700000002']);
        TeachingAssignment::create(['teacher_id' => $lina->id, 'section_id' => $this->sixB->id, 'class_subject_id' => $this->cs['Bangla']->id]);
        $linaLogin = $this->loginFor('teacher', $lina->id, 'Teacher', 'Lina');
        $this->asUser($linaLogin)->getJson("{$exams}/results/classes")->assertJsonPath('data.0.students', 2)->assertJsonPath('data.0.sheets.total', 2);

        $this->step($this->admin, 'review')->assertOk();
        // In review: the exam's grade scale can't be switched.
        $other = GradeScale::create(['name' => 'Pass/fail']);
        $other->grades()->create(['name' => 'F', 'grade_point' => 0, 'min_percent' => 0]);
        $this->asUser($this->admin)->putJson($exams, [
            'name' => 'Half Yearly', 'type' => 'half_yearly', 'grade_scale_id' => $other->id, 'start_date' => '2026-11-10', 'end_date' => '2026-11-20',
        ])->assertUnprocessable()->assertJsonValidationErrors(['grade_scale_id']);

        // Marks that somehow went back to draft (a lost race) can't be edited, and the results can't move on.
        $mark = $this->markOf($this->rahim, $this->bangla);
        $mark->forceFill(['status' => 'draft'])->save();
        $this->save($this->admin, $this->bangla, $this->sixA, [['student_id' => $this->rahim->id, 'marks' => 99]])->assertConflict();
        $this->step($this->admin, 'approve')->assertConflict()->assertJsonFragment(["Some of Class 6's marks are no longer submitted. Return the results to draft, then recalculate."]);
        $mark->forceFill(['status' => 'submitted'])->save();

        // The grade scale changed after calculation: recalculate before approving.
        $this->travel(5)->minutes();
        $this->scale->touch();
        $this->asUser($this->admin)->getJson("{$exams}/results/classes")->assertJsonPath('data.0.stale', true);
        $this->step($this->admin, 'approve')->assertConflict();
        $this->step($this->admin, 'return')->assertOk();
        $this->step($this->admin, 'calculate')->assertOk()->assertJsonPath('data.0.stale', false);
        foreach (['review', 'approve', 'publish'] as $step) {
            $this->step($this->admin, $step)->assertOk();
        }

        // A parent sees only their own child's results.
        $guardian = Guardian::create(['name' => 'Sumi\'s mother', 'phone' => '01800000001']);
        $guardian->students()->attach($this->sumi->id, ['relationship' => 'mother', 'is_primary' => true]);
        $parent = $this->loginFor('guardian', $guardian->id, 'Parent', 'Mother');
        $mine = $this->asUser($parent)->getJson('/api/results/me')->assertOk()->json('data.students');
        $this->assertSame(['Sumi'], array_column(array_column($mine, 'student'), 'name'));
        $this->assertSame([$this->resultOf($this->sumi)->id], array_column($mine[0]['results'], 'id'));

        // Next year, this year's teachers no longer see these results (assignments carry no year).
        $this->y2026->update(['is_current' => false]);
        $this->y2027->update(['is_current' => true]);
        AcademicYear::forgetCurrentCache();
        $this->asUser($this->teacher)->getJson("{$exams}/results?class_id={$this->six->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->asUser($this->teacher)->getJson('/api/results/'.$this->resultOf($this->rahim)->id.'/report-card')->assertNotFound();
        $this->asUser($this->teacher)->getJson("{$exams}/results/classes")->assertJsonCount(0, 'data');
        $this->asUser($this->admin)->getJson("{$exams}/results?class_id={$this->six->id}")->assertJsonCount(5, 'data');
    }
}
