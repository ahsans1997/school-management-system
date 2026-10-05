<?php

namespace Tests\Feature\Teacher;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeachingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SchoolClass $six;

    private Section $sixA;

    private Section $sixB;

    private Subject $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->six = SchoolClass::create(['name' => 'Class 6', 'numeric_level' => 6]);
        $this->sixA = $this->six->sections()->create(['name' => 'A', 'capacity' => 40]);
        $this->sixB = $this->six->sections()->create(['name' => 'B', 'capacity' => 40]);
        $this->math = Subject::create(['name' => 'Mathematics']);
        $this->six->subjects()->attach($this->math->id);
    }

    private function teacher(string $name): Teacher
    {
        static $n = 0;
        $n++;

        return Teacher::create(['name' => $name, 'employee_id' => "E-{$n}", 'joining_date' => '2019-06-01', 'phone' => '01700000000']);
    }

    private function assign(Teacher $teacher, Section $section, Subject $subject)
    {
        return $this->asUser($this->admin)->postJson("/api/teachers/{$teacher->id}/subjects", [
            'section_id' => $section->id, 'subject_id' => $subject->id,
        ]);
    }

    public function test_a_teacher_is_assigned_a_subject_per_section(): void
    {
        $kamal = $this->teacher('Kamal Hossain');

        $this->assign($kamal, $this->sixA, $this->math)
            ->assertCreated()
            ->assertJsonPath('data.assignments.0.class.name', 'Class 6')
            ->assertJsonPath('data.assignments.0.section.name', 'A')
            ->assertJsonPath('data.assignments.0.subject.name', 'Mathematics');

        // Another section of the same class can have a different teacher.
        $this->assign($this->teacher('Farhana Akter'), $this->sixB, $this->math)->assertCreated();

        $this->asUser($this->admin)->getJson("/api/teachers/{$kamal->id}")
            ->assertJsonCount(1, 'data.assignments');
    }

    public function test_the_subject_must_be_taught_in_the_sections_class(): void
    {
        $art = Subject::create(['name' => 'Art']);

        $this->assign($this->teacher('Kamal Hossain'), $this->sixA, $art)
            ->assertStatus(422)
            ->assertJsonPath('errors.subject_id.0', "Art isn't taught in Class 6. Assign it to the class first.");
    }

    public function test_one_teacher_per_subject_per_section(): void
    {
        $kamal = $this->teacher('Kamal Hossain');
        $this->assign($kamal, $this->sixA, $this->math)->assertCreated();

        $this->assign($this->teacher('Farhana Akter'), $this->sixA, $this->math)
            ->assertStatus(422)
            ->assertJsonPath('errors.subject_id.0', 'Mathematics in Class 6, section A is taught by Kamal Hossain. Remove that assignment first.');

        $this->assign($kamal, $this->sixA, $this->math)
            ->assertStatus(422)
            ->assertJsonPath('errors.subject_id.0', 'This teacher already teaches Mathematics in Class 6, section A.');
    }

    public function test_an_assignment_is_removed_only_through_its_own_teacher(): void
    {
        $kamal = $this->teacher('Kamal Hossain');
        $farhana = $this->teacher('Farhana Akter');
        $assignmentId = $this->assign($kamal, $this->sixA, $this->math)->json('data.assignments.0.id');

        $this->asUser($this->admin)->deleteJson("/api/teachers/{$farhana->id}/subjects/{$assignmentId}")->assertNotFound();
        $this->asUser($this->admin)->deleteJson("/api/teachers/{$kamal->id}/subjects/{$assignmentId}")
            ->assertOk()
            ->assertJsonCount(0, 'data.assignments');
    }

    public function test_taught_subjects_and_sections_are_protected_from_deletion(): void
    {
        $this->assign($this->teacher('Kamal Hossain'), $this->sixA, $this->math)->assertCreated();

        $this->asUser($this->admin)->deleteJson("/api/classes/{$this->six->id}/subjects/{$this->math->id}")->assertStatus(409);
        $this->asUser($this->admin)->deleteJson("/api/sections/{$this->sixA->id}")->assertStatus(409);
    }

    public function test_class_teachers_are_one_per_section(): void
    {
        $kamal = $this->teacher('Kamal Hossain');
        $farhana = $this->teacher('Farhana Akter');

        $this->asUser($this->admin)->postJson("/api/teachers/{$kamal->id}/classes", ['section_id' => $this->sixA->id])
            ->assertCreated()
            ->assertJsonPath('data.class_teacher_of.0.section.name', 'A');

        $this->asUser($this->admin)->postJson("/api/teachers/{$farhana->id}/classes", ['section_id' => $this->sixA->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.section_id.0', 'The class teacher of Class 6, section A is Kamal Hossain. Remove them first.');

        // The sections screen shows it.
        $this->asUser($this->admin)->getJson("/api/classes/{$this->six->id}/sections")
            ->assertJsonPath('data.0.class_teacher.name', 'Kamal Hossain')
            ->assertJsonPath('data.1.class_teacher', null);

        $this->asUser($this->admin)->deleteJson("/api/teachers/{$farhana->id}/classes/{$this->sixA->id}")->assertNotFound();
        $this->asUser($this->admin)->deleteJson("/api/teachers/{$kamal->id}/classes/{$this->sixA->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.class_teacher_of');
    }

    public function test_a_teacher_sees_only_their_own_assignments_on_the_dashboard(): void
    {
        $kamal = $this->teacher('Kamal Hossain');
        $this->assign($kamal, $this->sixA, $this->math);
        $this->assign($this->teacher('Farhana Akter'), $this->sixB, $this->math);
        $this->sixA->forceFill(['class_teacher_id' => $kamal->id])->save();

        $login = User::factory()->create(['personable_type' => 'teacher', 'personable_id' => $kamal->id]);
        $login->assignRole('Teacher');

        $widget = collect($this->asUser($login)->getJson('/api/dashboard')->json('data.widgets'))
            ->firstWhere('key', 'assigned_subjects');

        $this->assertSame('ready', $widget['status']);
        $this->assertSame([['class' => 'Class 6', 'section' => 'A', 'subject' => 'Mathematics']], $widget['data']['subjects']);
        $this->assertSame([['class' => 'Class 6', 'section' => 'A']], $widget['data']['class_teacher_of']);
    }

    public function test_assignments_need_teacher_edit(): void
    {
        $kamal = $this->teacher('Kamal Hossain');
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->asUser($principal)
            ->postJson("/api/teachers/{$kamal->id}/subjects", ['section_id' => $this->sixA->id, 'subject_id' => $this->math->id])
            ->assertForbidden();
        $this->asUser($principal)
            ->postJson("/api/teachers/{$kamal->id}/classes", ['section_id' => $this->sixA->id])
            ->assertForbidden();

        $this->assertSame(0, $kamal->assignments()->count());
    }
}
