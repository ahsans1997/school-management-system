<?php

namespace Tests\Feature\Exam;

use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class ExamTest extends EnrollmentTestCase
{
    private GradeScale $scale;

    /** @var array<string, ClassSubject> e.g. "6-Mathematics" */
    private array $cs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-05 09:00');

        $this->scale = GradeScale::create(['name' => 'GPA 5']);
        $this->scale->grades()->create(['name' => 'A+', 'grade_point' => 5, 'min_percent' => 80]);
        $this->scale->grades()->create(['name' => 'F', 'grade_point' => 0, 'min_percent' => 0]);

        foreach (['Bangla', 'Mathematics', 'Physics'] as $name) {
            $subject = Subject::create(['name' => $name]);
            foreach ([$this->six, $this->seven] as $class) {
                $class->subjects()->attach($subject->id);
                $this->cs[$class->numeric_level.'-'.$name] = ClassSubject::query()
                    ->where('class_id', $class->id)->where('subject_id', $subject->id)->sole();
            }
        }
    }

    private function createExam(array $overrides = [], ?User $as = null)
    {
        return $this->asUser($as ?? $this->admin)->postJson('/api/exams', [
            'name' => 'Half Yearly 2026',
            'type' => 'half_yearly',
            'academic_year_id' => $this->y2026->id,
            'grade_scale_id' => $this->scale->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-20',
            ...$overrides,
        ]);
    }

    private function exam(array $overrides = []): Exam
    {
        return Exam::findOrFail($this->createExam($overrides)->assertCreated()->json('data.id'));
    }

    private function paper(string $subject, array $overrides = [], int $level = 6): array
    {
        return [
            'class_subject_id' => $this->cs["{$level}-{$subject}"]->id,
            'section_id' => null,
            'full_marks' => 100,
            'pass_marks' => 33,
            ...$overrides,
        ];
    }

    private function savePapers(Exam $exam, SchoolClass $class, array $papers, ?User $as = null)
    {
        return $this->asUser($as ?? $this->admin)->putJson("/api/exams/{$exam->id}/classes/{$class->id}/subjects", ['subjects' => $papers]);
    }

    private function loginFor(string $type, int $id, string $role): User
    {
        $user = User::factory()->create(['personable_type' => $type, 'personable_id' => $id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_an_exam_is_created_inside_its_academic_year(): void
    {
        $this->createExam()->assertCreated()
            ->assertJsonPath('data.name', 'Half Yearly 2026')
            ->assertJsonPath('data.academic_year.name', '2026')
            ->assertJsonPath('data.grade_scale.name', 'GPA 5')
            ->assertJsonPath('data.end_date', '2026-11-20');

        $this->createExam(['name' => 'Early', 'start_date' => '2025-12-30'])->assertUnprocessable()->assertJsonValidationErrors(['start_date']);
        $this->createExam(['name' => 'Late', 'end_date' => '2027-01-05'])->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $this->createExam(['name' => 'Backwards', 'end_date' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors(['end_date']);
        $this->createExam(['type' => 'quiz'])->assertUnprocessable()->assertJsonValidationErrors(['type']);

        // Names are unique per year, archived exams included; another year is fine.
        $this->createExam()->assertUnprocessable()->assertJsonValidationErrors(['name']);
        $this->createExam(['academic_year_id' => $this->y2027->id, 'start_date' => '2027-06-01', 'end_date' => null])->assertCreated();

        // The year is fixed once created.
        $exam = Exam::query()->where('academic_year_id', $this->y2026->id)->sole();
        $this->asUser($this->admin)->putJson("/api/exams/{$exam->id}", [
            'name' => 'Half Yearly', 'type' => 'half_yearly', 'grade_scale_id' => $this->scale->id,
            'start_date' => '2026-11-10', 'academic_year_id' => $this->y2027->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['academic_year_id']);
    }

    public function test_a_class_is_configured_with_each_subjects_full_and_pass_marks(): void
    {
        $exam = $this->exam();

        $data = $this->savePapers($exam, $this->six, [
            $this->paper('Bangla', ['exam_date' => '2026-11-10', 'start_time' => '10:00', 'end_time' => '13:00']),
            $this->paper('Mathematics', ['cq_marks' => 70, 'mcq_marks' => 30, 'exam_date' => '2026-11-12', 'start_time' => '10:00', 'end_time' => '13:00']),
            $this->paper('Physics', ['full_marks' => 50, 'pass_marks' => 16.5, 'cq_marks' => 25, 'mcq_marks' => 12.5, 'practical_marks' => 12.5]),
        ])->assertOk()->json('data');

        $this->assertSame(['Bangla', 'Mathematics', 'Physics'], array_column(array_column($data['papers'], 'subject'), 'name'));
        $this->assertEquals([70, 30, null], [$data['papers'][1]['cq_marks'], $data['papers'][1]['mcq_marks'], $data['papers'][1]['practical_marks']]);
        $this->assertEquals([50, 16.5], [$data['papers'][2]['full_marks'], $data['papers'][2]['pass_marks']]);
        $this->assertSame(['10:00', '13:00', '2026-11-10'], [$data['papers'][0]['start_time'], $data['papers'][0]['end_time'], $data['papers'][0]['exam_date']]);
        $this->assertNull($data['papers'][2]['exam_date']); // not scheduled yet: listed last
        $this->assertSame(['A', 'B'], array_column($data['sections'], 'name'));
        $this->assertCount(3, $data['subjects']);

        // Saving again updates papers in place (marks will point at them) and drops missing ones.
        $ids = array_column($data['papers'], 'id');
        $again = $this->savePapers($exam, $this->six, [
            $this->paper('Mathematics', ['full_marks' => 80, 'pass_marks' => 26, 'exam_date' => '2026-11-12']),
            $this->paper('Bangla', ['exam_date' => '2026-11-10']),
        ])->assertOk()->json('data.papers');
        $this->assertSame([$ids[0], $ids[1]], array_column($again, 'id'));
        $this->assertEquals(80, $again[1]['full_marks']);
        $this->assertNull($again[1]['cq_marks']);
        $this->assertDatabaseMissing('exam_subjects', ['id' => $ids[2]]);

        $this->asUser($this->admin)->getJson("/api/exams/{$exam->id}")->assertOk()
            ->assertJsonPath('data.papers_count', 2)
            ->assertJsonPath('data.classes_count', 1)
            ->assertJsonPath('classes.0.name', 'Class 6');

        // An empty list takes the class out of the exam.
        $this->savePapers($exam, $this->six, [])->assertOk()->assertJsonCount(0, 'data.papers');
    }

    public function test_paper_rules_are_enforced_row_by_row(): void
    {
        $exam = $this->exam();
        $expect = function (array $papers, string $key) use ($exam) {
            $this->savePapers($exam, $this->six, $papers)->assertUnprocessable()->assertJsonValidationErrors([$key]);
        };

        $expect([$this->paper('Bangla', ['pass_marks' => 101])], 'subjects.0.pass_marks');
        $expect([$this->paper('Bangla', ['cq_marks' => 70, 'mcq_marks' => 20])], 'subjects.0.full_marks');
        $expect([$this->paper('Bangla', ['full_marks' => 0])], 'subjects.0.full_marks');
        $expect([$this->paper('Bangla', ['full_marks' => 100.555])], 'subjects.0.full_marks');
        $expect([$this->paper('Bangla'), $this->paper('Mathematics', [], 7)], 'subjects.1.class_subject_id');
        $expect([$this->paper('Bangla', ['section_id' => $this->sevenA->id])], 'subjects.0.section_id');
        $expect([$this->paper('Bangla', ['exam_date' => '2026-11-21'])], 'subjects.0.exam_date');
        $expect([$this->paper('Bangla', ['start_time' => '10:00'])], 'subjects.0.end_time');
        $expect([$this->paper('Bangla', ['exam_date' => '2026-11-10', 'start_time' => '13:00', 'end_time' => '10:00'])], 'subjects.0.end_time');
        $expect([$this->paper('Bangla'), $this->paper('Bangla')], 'subjects.1.class_subject_id');
        $expect([$this->paper('Physics'), $this->paper('Physics', ['section_id' => $this->sixA->id])], 'subjects.1.section_id');

        // Overlapping papers for the same students are refused…
        $at = fn (string $start, string $end, ?int $section = null) => ['exam_date' => '2026-11-10', 'start_time' => $start, 'end_time' => $end, 'section_id' => $section];
        $this->savePapers($exam, $this->six, [
            $this->paper('Bangla', $at('10:00', '13:00')),
            $this->paper('Mathematics', $at('12:00', '14:00', $this->sixA->id)),
        ])->assertUnprocessable()->assertJsonPath('errors', ['subjects.1.start_time' => ['Overlaps Bangla (10:00–13:00) on 10 Nov.']]);

        // …but back-to-back papers, and different sections at the same time, are fine.
        $this->savePapers($exam, $this->six, [
            $this->paper('Bangla', $at('10:00', '13:00')),
            $this->paper('Mathematics', $at('13:00', '14:00')),
            $this->paper('Physics', $at('14:00', '15:00', $this->sixA->id)),
            $this->paper('Physics', $at('14:00', '15:00', $this->sixB->id)),
        ])->assertOk();

        $this->assertSame(4, ExamSubject::count());
    }

    public function test_the_database_blocks_a_duplicate_paper(): void
    {
        $exam = $this->exam();
        $this->savePapers($exam, $this->six, [$this->paper('Bangla')])->assertOk();

        // Two "all sections" rows for one subject: NULL sections would never
        // collide in a unique index, so the generated section_key catches it.
        $this->expectException(QueryException::class);
        ExamSubject::create([
            'exam_id' => $exam->id, 'class_id' => $this->six->id, 'class_subject_id' => $this->cs['6-Bangla']->id,
            'full_marks' => 50, 'pass_marks' => 17,
        ]);
    }

    public function test_the_schedule_is_listable_per_class_and_section(): void
    {
        $exam = $this->exam();
        $this->savePapers($exam, $this->six, [
            $this->paper('Mathematics', ['exam_date' => '2026-11-12']),
            $this->paper('Bangla', ['exam_date' => '2026-11-10']),
            $this->paper('Physics', ['exam_date' => '2026-11-11', 'section_id' => $this->sixA->id]),
        ])->assertOk();
        $this->savePapers($exam, $this->seven, [$this->paper('Bangla', ['exam_date' => '2026-11-10'], 7)])->assertOk();

        $subjects = fn (string $query) => array_map(
            fn ($p) => $p['subject']['name'].' '.$p['class']['name'].($p['section'] ? ' '.$p['section']['name'] : ''),
            $this->asUser($this->admin)->getJson("/api/exams/{$exam->id}/schedule{$query}")->assertOk()->json('data.papers'),
        );

        $this->assertSame(['Bangla Class 6', 'Bangla Class 7', 'Physics Class 6 A', 'Mathematics Class 6'], $subjects(''));
        $this->assertSame(['Bangla Class 6', 'Physics Class 6 A', 'Mathematics Class 6'], $subjects("?class_id={$this->six->id}"));
        $this->assertSame(['Bangla Class 6', 'Physics Class 6 A', 'Mathematics Class 6'], $subjects("?section_id={$this->sixA->id}"));
        $this->assertSame(['Bangla Class 6', 'Mathematics Class 6'], $subjects("?section_id={$this->sixB->id}"));
    }

    public function test_moving_the_exam_cannot_strand_scheduled_papers(): void
    {
        $exam = $this->exam();
        $this->savePapers($exam, $this->six, [$this->paper('Bangla', ['exam_date' => '2026-11-18'])])->assertOk();
        $update = fn (array $dates) => $this->asUser($this->admin)->putJson("/api/exams/{$exam->id}", [
            'name' => 'Half Yearly 2026', 'type' => 'half_yearly', 'grade_scale_id' => $this->scale->id, ...$dates,
        ]);

        // The error points at the date that strands the paper.
        $update(['start_date' => '2026-11-10', 'end_date' => '2026-11-15'])->assertUnprocessable()
            ->assertJsonPath('errors', ['end_date' => ['A paper is scheduled outside these dates. Move it first.']]);
        $update(['start_date' => '2026-11-19', 'end_date' => '2026-11-25'])->assertUnprocessable()
            ->assertJsonPath('errors', ['start_date' => ['A paper is scheduled outside these dates. Move it first.']]);
        $update(['start_date' => '2026-11-12', 'end_date' => '2026-11-25'])->assertOk()->assertJsonPath('data.end_date', '2026-11-25');

        // A one-day exam (no end date) only takes papers on that day.
        $oneDay = $this->exam(['name' => 'Class test', 'type' => 'monthly', 'start_date' => '2026-10-20', 'end_date' => null]);
        $this->savePapers($oneDay, $this->six, [$this->paper('Bangla', ['exam_date' => '2026-10-21'])])->assertUnprocessable()
            ->assertJsonPath('errors', ['subjects.0.exam_date' => ['This exam is on 2026-10-20.']]);
    }

    public function test_archived_exams_are_hidden_read_only_and_restorable(): void
    {
        $exam = $this->exam();
        $this->savePapers($exam, $this->six, [$this->paper('Bangla')])->assertOk();

        $this->asUser($this->admin)->deleteJson("/api/exams/{$exam->id}")->assertNoContent();
        $this->assertSoftDeleted($exam);
        $this->assertDatabaseCount('exam_subjects', 1); // papers kept for marks/results

        $this->asUser($this->admin)->getJson('/api/exams')->assertJsonCount(0, 'data');
        $this->asUser($this->admin)->getJson('/api/exams?archived=1')->assertJsonPath('data.0.archived', true);
        $this->asUser($this->admin)->getJson("/api/exams/{$exam->id}")->assertOk()->assertJsonPath('data.archived', true);
        $this->savePapers($exam, $this->six, [])->assertNotFound();
        $this->asUser($this->admin)->getJson("/api/exams/{$exam->id}/schedule")->assertNotFound();

        $this->asUser($this->admin)->postJson("/api/exams/{$exam->id}/restore")->assertOk()->assertJsonPath('data.archived', false);
        $this->asUser($this->admin)->getJson('/api/exams')->assertJsonPath('data.0.papers_count', 1);
    }

    public function test_teachers_can_view_but_not_set_up_exams(): void
    {
        $exam = $this->exam();
        $teacher = $this->userWithRole('Teacher');
        $principal = $this->userWithRole('Principal');

        foreach ([$teacher, $principal] as $user) {
            $this->asUser($user)->getJson('/api/exams')->assertOk()->assertJsonCount(1, 'data');
            $this->asUser($user)->getJson("/api/exams/{$exam->id}/schedule")->assertOk();
            $this->asUser($user)->getJson("/api/exams/{$exam->id}/classes/{$this->six->id}/subjects")->assertOk();
            $this->createExam(['name' => 'Mine'], $user)->assertForbidden();
            $this->savePapers($exam, $this->six, [$this->paper('Bangla')], $user)->assertForbidden();
            $this->asUser($user)->deleteJson("/api/exams/{$exam->id}")->assertForbidden();
        }

        $parent = $this->userWithRole('Parent');
        $this->asUser($parent)->getJson('/api/exams')->assertForbidden();
        $this->asUser($parent)->getJson("/api/exams/{$exam->id}/schedule")->assertForbidden();
    }

    public function test_a_subject_on_an_exam_cannot_be_removed_from_the_class(): void
    {
        $exam = $this->exam();
        $this->savePapers($exam, $this->six, [$this->paper('Bangla')])->assertOk();

        $bangla = $this->cs['6-Bangla']->subject_id;
        $this->asUser($this->admin)->deleteJson("/api/classes/{$this->six->id}/subjects/{$bangla}")->assertStatus(409);
        $this->asUser($this->admin)->deleteJson("/api/classes/{$this->seven->id}/subjects/{$bangla}")->assertNoContent();
    }

    public function test_me_and_the_dashboard_show_each_portal_its_own_upcoming_exams(): void
    {
        $halfYearly = $this->exam();
        $this->savePapers($halfYearly, $this->six, [
            $this->paper('Bangla', ['exam_date' => '2026-11-10']),
            $this->paper('Physics', ['exam_date' => '2026-11-11', 'section_id' => $this->sixA->id]),
        ])->assertOk();
        $sevenOnly = $this->exam(['name' => 'Class 7 test', 'type' => 'test', 'start_date' => '2026-10-25', 'end_date' => null]);
        $this->savePapers($sevenOnly, $this->seven, [$this->paper('Bangla', [], 7)])->assertOk();
        $past = $this->exam(['name' => 'Monthly — Sept', 'type' => 'monthly', 'start_date' => '2026-09-20', 'end_date' => null]);
        $this->savePapers($past, $this->six, [$this->paper('Bangla')])->assertOk();

        // Student in 6B: their section's papers only (no 6A Physics, no Class 7, nothing past).
        $nusrat = $this->student('Nusrat');
        $this->enroll($nusrat, $this->sixB)->assertCreated();
        $studentLogin = $this->loginFor('student', $nusrat->id, 'Student');
        $me = $this->asUser($studentLogin)->getJson('/api/exams/me')->assertOk()->json('data');
        $this->assertSame('student', $me['type']);
        $this->assertSame(['Half Yearly 2026'], array_column($me['groups'][0]['exams'], 'name'));
        $this->assertSame(['Bangla'], array_column(array_column($me['groups'][0]['exams'][0]['papers'], 'subject'), 'name'));
        $this->asUser($studentLogin)->getJson('/api/dashboard')
            ->assertJsonPath('data.widgets.'.$this->widgetIndex($studentLogin, 'upcoming_exams').'.data.exams.0.name', 'Half Yearly 2026');

        // Parent of a 6A child and a Class 7 child: one group per child.
        $rafi = $this->student('Rafi');
        $this->enroll($rafi, $this->sevenA)->assertCreated();
        $anika = $this->student('Anika');
        $this->enroll($anika, $this->sixA)->assertCreated();
        $guardian = Guardian::create(['name' => 'Parent', 'phone' => '01800000000']);
        $guardian->students()->attach([$anika->id => ['relationship' => 'mother', 'is_primary' => true], $rafi->id => ['relationship' => 'mother', 'is_primary' => false]]);
        $parent = $this->loginFor('guardian', $guardian->id, 'Parent');
        $groups = $this->asUser($parent)->getJson('/api/exams/me')->assertOk()->json('data.groups');
        $this->assertSame(['Anika', 'Rafi'], array_column(array_column($groups, 'student'), 'name'));
        $this->assertSame(['Bangla', 'Physics'], array_column(array_column($groups[0]['exams'][0]['papers'], 'subject'), 'name'));
        $this->assertSame(['Class 7 test'], array_column($groups[1]['exams'], 'name'));

        // Teacher of 7A: Class 7's exam only.
        $kamal = Teacher::create(['name' => 'Kamal', 'employee_id' => 'T-1', 'joining_date' => '2020-01-01', 'phone' => '01700000001']);
        TeachingAssignment::create(['teacher_id' => $kamal->id, 'section_id' => $this->sevenA->id, 'class_subject_id' => $this->cs['7-Mathematics']->id]);
        $teacherLogin = $this->loginFor('teacher', $kamal->id, 'Teacher');
        $teacherMe = $this->asUser($teacherLogin)->getJson('/api/exams/me')->assertOk()->json('data');
        $this->assertSame(['teacher', 'A', ['Class 7 test']], [$teacherMe['type'], $teacherMe['groups'][0]['section']['name'], array_column($teacherMe['groups'][0]['exams'], 'name')]);

        // Admin dashboard: every upcoming exam, soonest first, with counts.
        $exams = $this->asUser($this->admin)->getJson('/api/dashboard')->json('data.widgets.'.$this->widgetIndex($this->admin, 'upcoming_exams').'.data.exams');
        $this->assertSame(['Class 7 test', 'Half Yearly 2026'], array_column($exams, 'name'));
        $this->assertSame([2, 1], [$exams[1]['papers'], $exams[1]['classes']]);

        // Accountants don't hold exam.view and aren't a portal: no widget.
        $accountant = $this->userWithRole('Accountant');
        $this->assertNotContains('upcoming_exams', array_column($this->asUser($accountant)->getJson('/api/dashboard')->json('data.widgets'), 'key'));
    }

    private function widgetIndex(User $user, string $key): int
    {
        $keys = array_column($this->asUser($user)->getJson('/api/dashboard')->json('data.widgets'), 'key');
        $index = array_search($key, $keys, true);
        $this->assertNotFalse($index, "Widget {$key} missing.");

        return $index;
    }
}
