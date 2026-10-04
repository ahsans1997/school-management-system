<?php

namespace Tests\Feature\Subject;

use App\Enums\SubjectType;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
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

    private function subject(string $name, ?string $code = null): Subject
    {
        return Subject::create(['name' => $name, 'code' => $code]);
    }

    public function test_list_is_paginated_ordered_by_name_and_counts_classes(): void
    {
        $this->subject('Science');
        $math = $this->subject('Mathematics');
        $this->subject('English');
        $math->classes()->attach([
            SchoolClass::create(['name' => 'Class 6', 'numeric_level' => 6])->id,
            SchoolClass::create(['name' => 'Class 7', 'numeric_level' => 7])->id,
        ]);

        $this->asUser($this->admin())
            ->getJson('/api/subjects')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'English')
            ->assertJsonPath('data.1.name', 'Mathematics')
            ->assertJsonPath('data.1.classes_count', 2)
            ->assertJsonPath('data.2.classes_count', 0)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'code', 'type', 'classes_count']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_a_subject_is_created_with_optional_code_and_type(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/subjects', ['name' => 'Physics', 'code' => '136', 'type' => 'theory_practical'])
            ->assertCreated()
            ->assertJsonPath('data.code', '136')
            ->assertJsonPath('data.type', 'theory_practical');

        $this->assertSame(SubjectType::TheoryPractical, Subject::query()->where('name', 'Physics')->sole()->type);
    }

    public function test_code_and_type_may_be_omitted_and_blank_codes_never_collide(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson('/api/subjects', ['name' => 'Art', 'code' => ''])
            ->assertCreated()
            ->assertJsonPath('data.code', null)
            ->assertJsonPath('data.type', null);

        $this->asUser($admin)
            ->postJson('/api/subjects', ['name' => 'Music', 'code' => ''])
            ->assertCreated();
    }

    public function test_duplicate_name_or_code_is_rejected(): void
    {
        $this->subject('Bangla', '101');
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson('/api/subjects', ['name' => 'Bangla'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->asUser($admin)
            ->postJson('/api/subjects', ['name' => 'Bangla 1st Paper', 'code' => '101'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_unknown_type_is_rejected(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/subjects', ['name' => 'Chemistry', 'type' => 'lab'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_a_subject_can_be_updated_without_clashing_with_itself(): void
    {
        $subject = $this->subject('Biology', '138');

        $this->asUser($this->admin())
            ->putJson("/api/subjects/{$subject->id}", ['name' => 'Biology', 'code' => '138', 'type' => 'practical'])
            ->assertOk()
            ->assertJsonPath('data.type', 'practical');
    }

    public function test_show_counts_the_classes_that_teach_it(): void
    {
        $subject = $this->subject('ICT');
        $subject->classes()->attach(SchoolClass::create(['name' => 'Class 9', 'numeric_level' => 9])->id);

        $this->asUser($this->admin())
            ->getJson("/api/subjects/{$subject->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'ICT')
            ->assertJsonPath('data.classes_count', 1);
    }

    public function test_an_unassigned_subject_can_be_deleted(): void
    {
        $subject = $this->subject('Drawing');

        $this->asUser($this->admin())
            ->deleteJson("/api/subjects/{$subject->id}")
            ->assertNoContent();

        $this->assertModelMissing($subject);
    }

    public function test_an_assigned_subject_cannot_be_deleted(): void
    {
        $subject = $this->subject('Mathematics');
        $subject->classes()->attach(SchoolClass::create(['name' => 'Class 6', 'numeric_level' => 6])->id);

        $this->asUser($this->admin())
            ->deleteJson("/api/subjects/{$subject->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This subject is assigned to a class (or otherwise in use) and cannot be deleted. Unassign it first.');

        $this->assertModelExists($subject);
    }

    public function test_permissions_gate_each_action(): void
    {
        $subject = $this->subject('Geography');

        // Teacher and Principal may view the catalogue but not change it.
        foreach (['Teacher', 'Principal'] as $role) {
            $user = $this->userWithRole($role);
            $this->asUser($user)->getJson('/api/subjects')->assertOk();
            $this->asUser($user)->postJson('/api/subjects', ['name' => 'History'])->assertForbidden();
            $this->asUser($user)->putJson("/api/subjects/{$subject->id}", ['name' => 'Geo'])->assertForbidden();
            $this->asUser($user)->deleteJson("/api/subjects/{$subject->id}")->assertForbidden();
        }

        // Accountant has no subject.* permission at all.
        $this->asUser($this->userWithRole('Accountant'))->getJson('/api/subjects')->assertForbidden();

        $this->assertSame('Geography', $subject->fresh()->name);
    }
}
