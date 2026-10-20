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
use Illuminate\Testing\TestResponse;
use Tests\Feature\Enrollment\EnrollmentTestCase;

/**
 * Shared fixtures for marks and results: the "Half Yearly" exam (10–20 Nov
 * 2026, GPA 5 scale) with three Class 6 papers, Rahim + Karim in 6A, Sumi
 * in 6B, and Kamal (teaches 6A Mathematics, class teacher of 6B). It's
 * 15 Nov.
 */
abstract class ExamMarksTestCase extends EnrollmentTestCase
{
    protected Exam $exam;

    protected GradeScale $scale;

    /** @var array<string, ClassSubject> by subject name */
    protected array $cs = [];

    /** Class-wide, one figure out of 100 (pass 33), sat 10 Nov. */
    protected ExamSubject $bangla;

    /** Class-wide, CQ 70 + MCQ 30 (pass 33), sat 12 Nov. */
    protected ExamSubject $maths;

    /** 6A only, CQ 25 + MCQ 12.5 + practical 12.5 = 50 (pass 16.5), sat 18 Nov. */
    protected ExamSubject $physics;

    protected Student $rahim;

    protected Student $karim;

    protected Student $sumi;

    protected User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-15 09:00');

        $this->scale = GradeScale::create(['name' => 'GPA 5']);
        foreach ([['A+', 5, 80], ['A', 4, 70], ['A-', 3.5, 60], ['B', 3, 50], ['C', 2, 40], ['D', 1, 33], ['F', 0, 0]] as [$name, $point, $min]) {
            $this->scale->grades()->create(['name' => $name, 'grade_point' => $point, 'min_percent' => $min]);
        }
        $this->exam = Exam::forceCreate([
            'academic_year_id' => $this->y2026->id, 'grade_scale_id' => $this->scale->id, 'name' => 'Half Yearly',
            'type' => 'half_yearly', 'start_date' => '2026-11-10', 'end_date' => '2026-11-20',
        ]);

        foreach (['Bangla', 'Mathematics', 'Physics'] as $name) {
            $this->six->subjects()->attach(Subject::create(['name' => $name])->id);
            $this->cs[$name] = ClassSubject::query()->where('class_id', $this->six->id)->whereHas('subject', fn ($q) => $q->where('name', $name))->sole();
        }
        $paper = fn (string $subject, array $values) => ExamSubject::create([
            'exam_id' => $this->exam->id, 'class_id' => $this->six->id, 'class_subject_id' => $this->cs[$subject]->id,
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
        TeachingAssignment::create(['teacher_id' => $kamal->id, 'section_id' => $this->sixA->id, 'class_subject_id' => $this->cs['Mathematics']->id]);
        $this->sixB->forceFill(['class_teacher_id' => $kamal->id])->save();
        $this->teacher = $this->loginFor('teacher', $kamal->id, 'Teacher', 'Kamal');
    }

    protected function loginFor(string $type, int $id, string $role, string $name = 'Someone'): User
    {
        $user = User::factory()->create(['name' => $name, 'personable_type' => $type, 'personable_id' => $id]);
        $user->assignRole($role);

        return $user;
    }

    protected function sheetUrl(ExamSubject $paper, Section $section, ?Exam $exam = null): string
    {
        return '/api/exams/'.($exam ?? $this->exam)->id."/marks/sheet?exam_subject_id={$paper->id}&section_id={$section->id}";
    }

    protected function save(User $as, ExamSubject $paper, Section $section, array $records): TestResponse
    {
        return $this->asUser($as)->postJson("/api/exams/{$this->exam->id}/marks/bulk", [
            'exam_subject_id' => $paper->id, 'section_id' => $section->id, 'records' => $records,
        ]);
    }

    protected function sheetAction(User $as, string $action, ExamSubject $paper, Section $section): TestResponse
    {
        return $this->asUser($as)->postJson("/api/exams/{$this->exam->id}/marks/{$action}", [
            'exam_subject_id' => $paper->id, 'section_id' => $section->id,
        ]);
    }

    protected function markOf(Student $student, ExamSubject $paper): Mark
    {
        return Mark::query()->where('student_id', $student->id)->where('exam_subject_id', $paper->id)->sole();
    }
}
