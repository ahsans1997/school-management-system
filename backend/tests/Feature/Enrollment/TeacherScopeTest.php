<?php

namespace Tests\Feature\Enrollment;

use App\Models\ClassSubject;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;

/** Spec §52: a teacher sees only the students of the sections they teach or class-teach. */
class TeacherScopeTest extends EnrollmentTestCase
{
    private function teacherLogin(string $name): array
    {
        static $n = 0;
        $n++;
        $teacher = Teacher::create(['name' => $name, 'employee_id' => "E-{$n}", 'joining_date' => '2020-01-01', 'phone' => '01700000000']);
        $login = User::factory()->create(['personable_type' => 'teacher', 'personable_id' => $teacher->id]);
        $login->assignRole('Teacher');

        return [$teacher, $login];
    }

    private function names(User $user): array
    {
        return array_column($this->asUser($user)->getJson('/api/students')->assertOk()->json('data'), 'name');
    }

    public function test_a_teacher_sees_only_active_students_of_their_sections_this_year(): void
    {
        $math = Subject::create(['name' => 'Mathematics']);
        $this->six->subjects()->attach($math->id);
        [$kamal, $login] = $this->teacherLogin('Kamal');
        TeachingAssignment::create([
            'teacher_id' => $kamal->id, 'section_id' => $this->sixA->id,
            'class_subject_id' => ClassSubject::query()->sole()->id,
        ]);

        $inA = $this->student('In 6A');
        $this->enroll($inA, $this->sixA);
        $this->enroll($this->student('In 6B'), $this->sixB);
        $left = $this->student('Left 6A');
        $leftId = $this->enroll($left, $this->sixA)->json('data.id');
        $this->asUser($this->admin)->putJson("/api/enrollments/{$leftId}", ['section_id' => $this->sixA->id, 'status' => 'left']);
        $this->enroll($this->student('6A next year only'), $this->sevenA, $this->y2027);

        $this->assertSame(['In 6A'], $this->names($login));

        // Becoming class teacher of 6B adds that section.
        $this->sixB->forceFill(['class_teacher_id' => $kamal->id])->save();
        $this->assertSame(['In 6A', 'In 6B'], $this->names($login));

        // Outside their sections a profile is "missing", not "forbidden".
        $this->asUser($login)->getJson("/api/students/{$inA->id}")->assertOk();
        $this->asUser($login)->getJson("/api/students/{$left->id}")->assertNotFound();
    }

    public function test_a_teacher_login_without_a_teacher_record_sees_nobody_and_staff_see_everyone(): void
    {
        $this->enroll($this->student('Somebody'), $this->sixA);
        $this->student('Not enrolled');

        $unlinked = $this->userWithRole('Teacher');
        $this->assertSame([], $this->names($unlinked));

        $this->assertCount(2, $this->names($this->admin));
        $this->assertCount(2, $this->names($this->userWithRole('Accountant')));
    }
}
