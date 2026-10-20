<?php

namespace Tests\Feature\Exam;

use App\Models\ClassSubject;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Tests\Feature\Enrollment\EnrollmentTestCase;

class MarksTest extends EnrollmentTestCase
{
    private Exam $exam;

    /** Class-wide, one figure out of 100, sat 10 Nov. */
    private ExamSubject $bangla;

    /** Class-wide, CQ 70 + MCQ 30, sat 12 Nov. */
    private ExamSubject $maths;

    /** 6A only, CQ 25 + MCQ 12.5 + practical 12.5 = 50, sat 18 Nov. */
    private ExamSubject $physics;

    private Student $rahim;

    private Student $karim;

    private Student $sumi;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 09:00');

        $scale = GradeScale::create(['name' => 'GPA 5']);
        $scale->grades()->create(['name' => 'F', 'grade_point' => 0, 'min_percent' => 0]);
        $this->exam = Exam::forceCreate([
            'academic_year_id' => $this->y2026->id, 'grade_scale_id' => $scale->id, 'name' => 'Half Yearly',
            'type' => 'half_yearly', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20',
        ]);

        $cs = [];
        foreach (['Bangla', 'Mathematics', 'Physics'] as $name) {
            $this->six->subjects()->attach(Subject::create(['name' => $name])->id);
            $cs[$name] = ClassSubject::query()->where('class_id', $this->six->id)->whereHas('subject', fn ($q) => $q->where('name', $name))->sole();
        }
        $paper = fn (string $subject, array $values) => ExamSubject::create([
            'exam_id' => $this->exam->id, 'class_id' => $this->six->id, 'class_subject_id' => $cs[$subject]->id,
            'full_marks' => 100, 'pass_marks' => 33, ...$values,
        ]);
        $this->bangla = $paper('Bangla', ['exam_date' => '2026-11-10']);
        $this->maths = $paper('Mathematics', ['cq_marks' => 70, 'mcq_marks' => 30, 'exam_date' => '2026-11-12']);
        $this->physics = $paper('Physics', [
            'section_id' => $this->sixA->id, 'full_marks' => 50, 'pass_marks' => 16.5,
            'cq_marks' => 25, 'mcq_marks' => 12.5, 'practical_marks' => 12.5, 'exam_date' => '2026-11-18',
        ]);

        $this->rahim = $this->student('Rahim');
        $this->karim = $this->student('Karim');
        $this->sumi = $this->student('Sumi');
        $this->enroll($this->rahim, $this->sixA)->assertCreated();
        $this->enroll($this->karim, $this->sixA)->assertCreated();
        $this->enroll($this->sumi, $this->sixB)->assertCreated();

        // Kamal teaches 6A Mathematics and is class teacher of 6B (not enough for marks).
        $kamal = Teacher::create(['name' => 'Kamal', 'employee_id' => 'T-1', 'joining_date' => '2020-01-01', 'phone' => '01700000001']);
        TeachingAssignment::create(['teacher_id' => $kamal->id, 'section_id' => $this->sixA->id, 'class_subject_id' => $cs['Mathematics']->id]);
        $this->sixB->forceFill(['class_teacher_id' => $kamal->id])->save();
        $this->teacher = User::factory()->create(['name' => 'Kamal', 'personable_type' => 'teacher', 'personable_id' => $kamal->id]);
        $this->teacher->assignRole('Teacher');
    }

    private function sheetUrl(ExamSubject $paper, Section $section): string
    {
        return "/api/exams/{$this->exam->id}/marks/sheet?exam_subject_id={$paper->id}&section_id={$section->id}";
    }

    private function save(User $as, ExamSubject $paper, Section $section, array $records)
    {
        return $this->asUser($as)->postJson("/api/exams/{$this->exam->id}/marks/bulk", [
            'exam_subject_id' => $paper->id, 'section_id' => $section->id, 'records' => $records,
        ]);
    }

    private function sheetAction(User $as, string $action, ExamSubject $paper, Section $section)
    {
        return $this->asUser($as)->postJson("/api/exams/{$this->exam->id}/marks/{$action}", [
            'exam_subject_id' => $paper->id, 'section_id' => $section->id,
        ]);
    }

    private function markOf(Student $student, ExamSubject $paper): Mark
    {
        return Mark::query()->where('student_id', $student->id)->where('exam_subject_id', $paper->id)->sole();
    }

    public function test_a_teacher_works_only_on_the_subjects_they_teach(): void
    {
        // Their sheets: just 6A Mathematics, with progress.
        $sheets = $this->asUser($this->teacher)->getJson("/api/exams/{$this->exam->id}/marks")->assertOk()->json();
        $this->assertCount(1, $sheets['data']);
        $this->assertSame(['Mathematics', 'A', 2, 0, 'not_started'], [
            $sheets['data'][0]['subject']['name'], $sheets['data'][0]['section']['name'],
            $sheets['data'][0]['students'], $sheets['data'][0]['entered'], $sheets['data'][0]['status'],
        ]);
        $this->assertSame(['Class 6'], array_column($sheets['classes'], 'name'));

        $sheet = $this->asUser($this->teacher)->getJson($this->sheetUrl($this->maths, $this->sixA))->assertOk()->json('data');
        $this->assertSame(['Rahim', 'Karim'], array_column($sheet['students'], 'name'));
        $this->assertSame(['cq', 'mcq'], $sheet['parts']);
        $this->assertTrue($sheet['can_edit']);

        // Another subject in their section, or their subject in a section they're only class teacher of: 403.
        $this->asUser($this->teacher)->getJson($this->sheetUrl($this->bangla, $this->sixA))->assertForbidden();
        $this->asUser($this->teacher)->getJson($this->sheetUrl($this->maths, $this->sixB))->assertForbidden();
        $this->save($this->teacher, $this->bangla, $this->sixA, [['student_id' => $this->rahim->id, 'marks' => 50]])->assertForbidden();
        $this->sheetAction($this->teacher, 'submit', $this->bangla, $this->sixA)->assertForbidden();

        // ...and one mark of another subject.
        $this->save($this->admin, $this->bangla, $this->sixA, [['student_id' => $this->rahim->id, 'marks' => 50]])->assertOk();
        $this->asUser($this->teacher)->putJson('/api/marks/'.$this->markOf($this->rahim, $this->bangla)->id, ['marks' => 90])->assertForbidden();
        $this->assertSame('50.00', $this->markOf($this->rahim, $this->bangla)->total);

        // Admins aren't scoped: every paper × section that sits it (Physics is 6A only).
        $all = $this->asUser($this->admin)->getJson("/api/exams/{$this->exam->id}/marks")->assertOk()->json('data');
        $this->assertSame(
            ['A Bangla', 'A Mathematics', 'A Physics', 'B Bangla', 'B Mathematics'],
            array_map(fn ($s) => $s['section']['name'].' '.$s['subject']['name'], $all),
        );

        // A section that doesn't sit the paper; a paper of another exam.
        $this->asUser($this->admin)->getJson($this->sheetUrl($this->physics, $this->sixB))->assertUnprocessable()->assertJsonValidationErrors(['section_id']);
        $other = Exam::forceCreate([...$this->exam->only(['academic_year_id', 'grade_scale_id', 'type', 'start_date']), 'name' => 'Other']);
        $this->asUser($this->admin)->getJson("/api/exams/{$other->id}/marks/sheet?exam_subject_id={$this->maths->id}&section_id={$this->sixA->id}")->assertNotFound();

        // No exam.marks_entry: no marks at all (Principal, Student).
        $this->asUser($this->userWithRole('Principal'))->getJson("/api/exams/{$this->exam->id}/marks")->assertForbidden();
        $this->asUser($this->userWithRole('Student'))->getJson($this->sheetUrl($this->maths, $this->sixA))->assertForbidden();
    }

    public function test_the_total_is_computed_by_the_server_within_the_papers_caps(): void
    {
        $sheet = $this->save($this->teacher, $this->maths, $this->sixA, [
            ['student_id' => $this->rahim->id, 'cq' => 55, 'mcq' => 25.5, 'total' => 999],
            ['student_id' => $this->karim->id, 'absent' => true],
        ])->assertOk()->json('data');

        $this->assertEquals([55, 25.5, 80.5, false, 'draft'], array_values(array_intersect_key(
            $sheet['students'][0]['mark'], array_flip(['cq', 'mcq', 'total', 'absent', 'status'])
        )));
        $this->assertTrue($sheet['students'][1]['mark']['absent']);
        $this->assertNull($sheet['students'][1]['mark']['total']);
        $this->assertSame(['students' => 2, 'entered' => 2, 'absent' => 1], $sheet['summary']);
        $this->assertSame('draft', $sheet['status']);
        $this->assertDatabaseHas('marks', ['student_id' => $this->rahim->id, 'total' => '80.50', 'entered_by' => $this->teacher->id]);

        $row = fn (array $values) => [['student_id' => $this->rahim->id, ...$values]];
        // A part over its cap; a part missing; one figure on a split paper; too many decimals.
        $this->save($this->teacher, $this->maths, $this->sixA, $row(['cq' => 71, 'mcq' => 10]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.cq']);
        $this->save($this->teacher, $this->maths, $this->sixA, $row(['cq' => 50]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.mcq']);
        $this->save($this->teacher, $this->maths, $this->sixA, $row(['marks' => 80]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.marks']);
        $this->save($this->teacher, $this->maths, $this->sixA, $row(['cq' => 10.555, 'mcq' => 1]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.cq']);
        // Absent with marks; a student who isn't on 6A's roll.
        $this->save($this->teacher, $this->maths, $this->sixA, $row(['absent' => true, 'cq' => 5, 'mcq' => 5]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.absent']);
        $this->save($this->teacher, $this->maths, $this->sixA, [['student_id' => $this->sumi->id, 'cq' => 5, 'mcq' => 5]])
            ->assertUnprocessable()->assertJsonValidationErrors(['records.0.student_id']);
        $this->assertSame('80.50', $this->markOf($this->rahim, $this->maths)->total); // nothing saved

        // A paper without a split takes one figure, up to the full marks.
        $this->save($this->admin, $this->bangla, $this->sixA, $row(['marks' => 100.5]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.marks']);
        $this->save($this->admin, $this->bangla, $this->sixA, $row(['cq' => 50]))->assertUnprocessable()->assertJsonValidationErrors(['records.0.marks']);
        $this->save($this->admin, $this->bangla, $this->sixA, $row(['marks' => 72.5]))->assertOk();
        $this->assertSame('72.50', $this->markOf($this->rahim, $this->bangla)->total);

        // One mark at a time: the client total is ignored here too.
        $mark = $this->markOf($this->rahim, $this->maths);
        $this->asUser($this->teacher)->putJson("/api/marks/{$mark->id}", ['cq' => 60, 'mcq' => 25.5, 'total' => 1])
            ->assertOk()->assertJsonPath('data.total', 85.5);
        $this->asUser($this->teacher)->putJson("/api/marks/{$mark->id}", ['cq' => 60])->assertUnprocessable()->assertJsonValidationErrors(['mcq']);
        $this->asUser($this->teacher)->putJson("/api/marks/{$mark->id}", [])->assertUnprocessable()->assertJsonValidationErrors(['marks']);

        // A blank row clears a draft mark.
        $this->save($this->teacher, $this->maths, $this->sixA, [['student_id' => $this->karim->id]])->assertOk();
        $this->assertDatabaseMissing('marks', ['student_id' => $this->karim->id, 'exam_subject_id' => $this->maths->id]);
    }

    public function test_a_submitted_sheet_is_locked_until_an_exam_admin_reopens_it(): void
    {
        $this->save($this->teacher, $this->maths, $this->sixA, [['student_id' => $this->rahim->id, 'cq' => 60, 'mcq' => 20]])->assertOk();
        // Everyone needs marks (or absent) first.
        $this->sheetAction($this->teacher, 'submit', $this->maths, $this->sixA)
            ->assertUnprocessable()->assertJsonValidationErrors(['records'])->assertJsonFragment(['Enter marks (or mark absent) for every student before submitting. Missing: Karim.']);

        $this->save($this->teacher, $this->maths, $this->sixA, [['student_id' => $this->karim->id, 'absent' => true]])->assertOk();
        $sheet = $this->sheetAction($this->teacher, 'submit', $this->maths, $this->sixA)->assertOk()->json('data');
        $this->assertSame(['submitted', 'Kamal', false, false], [$sheet['status'], $sheet['submitted']['by'], $sheet['can_edit'], $sheet['can_reopen']]);
        $this->assertSame(['submitted', 'submitted'], Mark::query()->orderBy('id')->pluck('status')->map->value->all());
        $this->sheetAction($this->teacher, 'submit', $this->maths, $this->sixA)->assertOk(); // idempotent

        // Locked: no saves or single edits; the teacher can't reopen it.
        $this->save($this->teacher, $this->maths, $this->sixA, [['student_id' => $this->rahim->id, 'cq' => 70, 'mcq' => 30]])->assertConflict();
        $this->asUser($this->teacher)->putJson('/api/marks/'.$this->markOf($this->rahim, $this->maths)->id, ['cq' => 70, 'mcq' => 30])->assertConflict();
        $this->sheetAction($this->teacher, 'reopen', $this->maths, $this->sixA)->assertForbidden();

        // An exam admin reopens it; the teacher can correct and resubmit.
        $this->asUser($this->admin)->getJson($this->sheetUrl($this->maths, $this->sixA))->assertJsonPath('data.can_reopen', true);
        $this->sheetAction($this->admin, 'reopen', $this->maths, $this->sixA)->assertOk()->assertJsonPath('data.status', 'draft');
        $this->save($this->teacher, $this->maths, $this->sixA, [['student_id' => $this->rahim->id, 'cq' => 70, 'mcq' => 30]])->assertOk()
            ->assertJsonPath('data.students.0.mark.total', 100);
        $this->assertSame($this->teacher->id, $this->markOf($this->rahim, $this->maths)->updated_by);
    }

    public function test_a_paper_with_marks_keeps_its_full_marks_and_split(): void
    {
        $this->save($this->admin, $this->maths, $this->sixA, [['student_id' => $this->rahim->id, 'cq' => 60, 'mcq' => 20]])->assertOk();
        $papers = fn (array $maths) => $this->asUser($this->admin)->putJson("/api/exams/{$this->exam->id}/classes/{$this->six->id}/subjects", ['subjects' => [
            ['class_subject_id' => $this->bangla->class_subject_id, 'full_marks' => 100, 'pass_marks' => 33, 'exam_date' => '2026-11-10'],
            ['class_subject_id' => $this->maths->class_subject_id, 'full_marks' => 100, 'pass_marks' => 33, 'cq_marks' => 70, 'mcq_marks' => 30, 'exam_date' => '2026-11-12', ...$maths],
            ['class_subject_id' => $this->physics->class_subject_id, 'section_id' => $this->sixA->id, 'full_marks' => 50, 'pass_marks' => 16.5, 'cq_marks' => 25, 'mcq_marks' => 12.5, 'practical_marks' => 12.5, 'exam_date' => '2026-11-18'],
        ]]);

        $papers(['full_marks' => 80, 'cq_marks' => 50])->assertUnprocessable()->assertJsonValidationErrors(['subjects.1.full_marks']);
        $papers(['cq_marks' => null, 'mcq_marks' => null])->assertUnprocessable()->assertJsonValidationErrors(['subjects.1.full_marks']);
        $papers(['pass_marks' => 40, 'exam_date' => '2026-11-13'])->assertOk(); // pass marks and date can change

        // A paper with marks can't be dropped from the exam.
        $this->asUser($this->admin)->putJson("/api/exams/{$this->exam->id}/classes/{$this->six->id}/subjects", ['subjects' => []])->assertConflict();
    }

    public function test_marks_open_on_the_schools_day_the_paper_is_sat(): void
    {
        // Physics is on 18 Nov; it's the 15th.
        $this->asUser($this->admin)->getJson($this->sheetUrl($this->physics, $this->sixA))->assertOk()
            ->assertJsonPath('data.open', false)->assertJsonPath('data.can_edit', false)->assertJsonPath('data.opens_on', '2026-11-18');
        $row = [['student_id' => $this->rahim->id, 'cq' => 20, 'mcq' => 10, 'practical' => 10]];
        $this->save($this->admin, $this->physics, $this->sixA, $row)
            ->assertUnprocessable()->assertJsonFragment(['Marks can be entered from 18 Nov 2026, once the paper is sat.']);

        // 19:00 UTC on the 17th is already 01:00 on the 18th in Dhaka (the school's timezone).
        $this->travelTo('2026-11-17 19:00');
        $this->save($this->admin, $this->physics, $this->sixA, $row)->assertOk()->assertJsonPath('data.students.0.mark.total', 40);
    }

    public function test_the_teacher_dashboard_lists_sheets_still_to_finish(): void
    {
        $pending = fn () => collect($this->asUser($this->teacher)->getJson('/api/dashboard')->assertOk()->json('data.widgets'))
            ->firstWhere('key', 'pending_marks');

        $widget = $pending();
        $this->assertSame('ready', $widget['status']);
        $this->assertSame(1, $widget['data']['total']);
        $this->assertSame(['Half Yearly', 'Mathematics', 'A', 2], [
            $widget['data']['sheets'][0]['exam']['name'], $widget['data']['sheets'][0]['subject']['name'],
            $widget['data']['sheets'][0]['section']['name'], $widget['data']['sheets'][0]['students'],
        ]);

        $this->save($this->teacher, $this->maths, $this->sixA, [
            ['student_id' => $this->rahim->id, 'cq' => 60, 'mcq' => 20],
            ['student_id' => $this->karim->id, 'absent' => true],
        ])->assertOk();
        $this->sheetAction($this->teacher, 'submit', $this->maths, $this->sixA)->assertOk();
        $this->assertSame([], $pending()['data']['sheets']);

        // A Teacher login not linked to a teacher record.
        $unlinked = $this->userWithRole('Teacher');
        $this->assertFalse(collect($this->asUser($unlinked)->getJson('/api/dashboard')->json('data.widgets'))->firstWhere('key', 'pending_marks')['data']['linked']);
        $this->asUser($unlinked)->getJson("/api/exams/{$this->exam->id}/marks")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_marks_never_leave_the_marks_screens(): void
    {
        $this->save($this->admin, $this->bangla, $this->sixA, [['student_id' => $this->rahim->id, 'marks' => 88]])->assertOk();

        // The student's and parent's views of the exam carry the schedule only.
        $studentLogin = User::factory()->create(['personable_type' => 'student', 'personable_id' => $this->rahim->id]);
        $studentLogin->assignRole('Student');
        $me = $this->asUser($studentLogin)->getJson('/api/exams/me')->assertOk()->getContent();
        $this->assertStringNotContainsString('88', $me);
        $this->assertStringNotContainsString('"total"', $me);
        $this->asUser($studentLogin)->getJson("/api/exams/{$this->exam->id}/marks")->assertForbidden();
    }
}
