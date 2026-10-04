<?php

namespace Tests\Feature\Subject;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSubjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        return $this->userWithRole('Admin');
    }

    private function schoolClass(string $name = 'Class 6', int $level = 6): SchoolClass
    {
        return SchoolClass::create(['name' => $name, 'numeric_level' => $level]);
    }

    public function test_a_subject_can_be_assigned_to_many_classes(): void
    {
        $math = Subject::create(['name' => 'Mathematics']);
        $six = $this->schoolClass('Class 6', 6);
        $seven = $this->schoolClass('Class 7', 7);
        $admin = $this->admin();

        foreach ([$six, $seven] as $class) {
            $this->asUser($admin)
                ->postJson("/api/classes/{$class->id}/subjects", ['subject_id' => $math->id])
                ->assertCreated()
                ->assertJsonPath('data.class_id', $class->id)
                ->assertJsonPath('data.subject.name', 'Mathematics');
        }

        $this->assertEqualsCanonicalizing([$six->id, $seven->id], $math->classes()->pluck('classes.id')->all());
    }

    public function test_class_subject_list_is_scoped_paginated_ordered_and_carries_the_class(): void
    {
        $six = $this->schoolClass('Class 6', 6);
        $seven = $this->schoolClass('Class 7', 7);
        $science = Subject::create(['name' => 'Science']);
        $bangla = Subject::create(['name' => 'Bangla']);
        $art = Subject::create(['name' => 'Art']);
        $six->subjects()->attach([$science->id, $bangla->id]);
        $seven->subjects()->attach($art->id);

        $this->asUser($this->admin())
            ->getJson("/api/classes/{$six->id}/subjects?per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.name', 'Bangla')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('class.name', 'Class 6')
            ->assertJsonStructure([
                'data' => [['id', 'class_id', 'subject_id', 'subject' => ['id', 'name', 'code', 'type']]],
                'class' => ['id', 'name'],
            ]);
    }

    public function test_duplicate_assignment_is_rejected(): void
    {
        $class = $this->schoolClass();
        $subject = Subject::create(['name' => 'English']);
        $class->subjects()->attach($subject->id);

        $this->asUser($this->admin())
            ->postJson("/api/classes/{$class->id}/subjects", ['subject_id' => $subject->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.subject_id.0', 'This subject is already assigned to the class.');

        $this->assertSame(1, $class->subjects()->count());
    }

    public function test_assigning_a_missing_or_invalid_subject_is_rejected(): void
    {
        $class = $this->schoolClass();
        $admin = $this->admin();

        foreach ([999, 'abc', null] as $subjectId) {
            $this->asUser($admin)
                ->postJson("/api/classes/{$class->id}/subjects", ['subject_id' => $subjectId])
                ->assertStatus(422)
                ->assertJsonValidationErrors('subject_id');
        }
    }

    public function test_a_subject_can_be_unassigned(): void
    {
        $class = $this->schoolClass();
        $subject = Subject::create(['name' => 'Religion']);
        $class->subjects()->attach($subject->id);

        $this->asUser($this->admin())
            ->deleteJson("/api/classes/{$class->id}/subjects/{$subject->id}")
            ->assertNoContent();

        $this->assertSame(0, $class->subjects()->count());
        $this->assertModelExists($subject); // only the assignment goes
    }

    public function test_unassigning_a_subject_the_class_does_not_have_is_not_found(): void
    {
        $class = $this->schoolClass();
        $other = $this->schoolClass('Class 7', 7);
        $subject = Subject::create(['name' => 'Agriculture']);
        $other->subjects()->attach($subject->id);

        $this->asUser($this->admin())
            ->deleteJson("/api/classes/{$class->id}/subjects/{$subject->id}")
            ->assertNotFound();

        $this->assertSame(1, $other->subjects()->count());
    }

    public function test_a_class_with_subjects_cannot_be_deleted(): void
    {
        $class = $this->schoolClass();
        $class->subjects()->attach(Subject::create(['name' => 'Physical Education'])->id);

        $this->asUser($this->admin())
            ->deleteJson("/api/classes/{$class->id}")
            ->assertStatus(409);

        $this->assertModelExists($class);
    }

    public function test_class_list_counts_assigned_subjects(): void
    {
        $class = $this->schoolClass();
        $class->subjects()->attach([
            Subject::create(['name' => 'Bangla'])->id,
            Subject::create(['name' => 'English'])->id,
        ]);

        $this->asUser($this->admin())
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonPath('data.0.subjects_count', 2);
    }

    public function test_assignment_requires_subject_edit(): void
    {
        $class = $this->schoolClass();
        $assigned = Subject::create(['name' => 'Bangla']);
        $spare = Subject::create(['name' => 'English']);
        $class->subjects()->attach($assigned->id);

        // Principal (and Teacher) hold subject.view only: may list, not change.
        foreach (['Principal', 'Teacher'] as $role) {
            $user = $this->userWithRole($role);
            $this->asUser($user)->getJson("/api/classes/{$class->id}/subjects")->assertOk();
            $this->asUser($user)
                ->postJson("/api/classes/{$class->id}/subjects", ['subject_id' => $spare->id])
                ->assertForbidden();
            $this->asUser($user)
                ->deleteJson("/api/classes/{$class->id}/subjects/{$assigned->id}")
                ->assertForbidden();
        }

        $this->assertSame([$assigned->id], $class->subjects()->pluck('subjects.id')->all());
    }
}
