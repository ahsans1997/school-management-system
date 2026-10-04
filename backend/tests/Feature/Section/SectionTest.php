<?php

namespace Tests\Feature\Section;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SectionTest extends TestCase
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

    private function section(SchoolClass $class, string $name, int $capacity = 40): Section
    {
        return $class->sections()->create(['name' => $name, 'capacity' => $capacity]);
    }

    public function test_list_is_scoped_to_the_class_paginated_and_carries_the_class(): void
    {
        $six = $this->schoolClass('Class 6', 6);
        $seven = $this->schoolClass('Class 7', 7);
        $this->section($six, 'B');
        $this->section($six, 'A');
        $this->section($seven, 'A');

        $this->asUser($this->admin())
            ->getJson("/api/classes/{$six->id}/sections")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'A')
            ->assertJsonPath('data.1.name', 'B')
            ->assertJsonPath('data.0.class_id', $six->id)
            ->assertJsonPath('class.name', 'Class 6')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [['id', 'class_id', 'name', 'capacity']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'class' => ['id', 'name', 'numeric_level'],
            ]);
    }

    public function test_listing_sections_of_a_missing_class_is_not_found(): void
    {
        $this->asUser($this->admin())
            ->getJson('/api/classes/999/sections')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']); // no internal model names
    }

    public function test_a_section_is_created_under_the_class_in_the_route(): void
    {
        $six = $this->schoolClass('Class 6', 6);
        $seven = $this->schoolClass('Class 7', 7);

        // A class_id in the body is ignored: the route decides the parent.
        $this->asUser($this->admin())
            ->postJson("/api/classes/{$six->id}/sections", [
                'name' => 'A', 'capacity' => 45, 'class_id' => $seven->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.class_id', $six->id)
            ->assertJsonPath('data.capacity', 45);

        $this->assertSame(1, $six->sections()->count());
        $this->assertSame(0, $seven->sections()->count());
    }

    public function test_name_is_unique_within_a_class_but_reusable_across_classes(): void
    {
        $six = $this->schoolClass('Class 6', 6);
        $seven = $this->schoolClass('Class 7', 7);
        $this->section($six, 'A');
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson("/api/classes/{$six->id}/sections", ['name' => 'A', 'capacity' => 40])
            ->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'This class already has a section with that name.');

        $this->asUser($admin)
            ->postJson("/api/classes/{$seven->id}/sections", ['name' => 'A', 'capacity' => 40])
            ->assertCreated();
    }

    public function test_capacity_must_be_a_positive_integer_in_range(): void
    {
        $class = $this->schoolClass();
        $admin = $this->admin();

        foreach ([null, 0, -5, 'forty', 1001] as $capacity) {
            $this->asUser($admin)
                ->postJson("/api/classes/{$class->id}/sections", ['name' => 'A', 'capacity' => $capacity])
                ->assertStatus(422)
                ->assertJsonValidationErrors('capacity');
        }

        $this->assertSame(0, $class->sections()->count());
    }

    public function test_show_includes_the_parent_class(): void
    {
        $section = $this->section($this->schoolClass('Class 9', 9), 'C', 30);

        $this->asUser($this->admin())
            ->getJson("/api/sections/{$section->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'C')
            ->assertJsonPath('data.capacity', 30)
            ->assertJsonPath('data.class.name', 'Class 9');
    }

    public function test_update_changes_name_and_capacity_but_never_the_class(): void
    {
        $six = $this->schoolClass('Class 6', 6);
        $seven = $this->schoolClass('Class 7', 7);
        $section = $this->section($six, 'A');
        $this->section($six, 'B');
        $admin = $this->admin();

        // Keeping its own name is not a clash.
        $this->asUser($admin)
            ->putJson("/api/sections/{$section->id}", ['name' => 'A', 'capacity' => 50, 'class_id' => $seven->id])
            ->assertOk()
            ->assertJsonPath('data.capacity', 50)
            ->assertJsonPath('data.class_id', $six->id);

        // Taking a sibling's name is.
        $this->asUser($admin)
            ->putJson("/api/sections/{$section->id}", ['name' => 'B', 'capacity' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame($six->id, $section->fresh()->class_id);
    }

    public function test_an_unused_section_can_be_deleted(): void
    {
        $section = $this->section($this->schoolClass(), 'A');

        $this->asUser($this->admin())
            ->deleteJson("/api/sections/{$section->id}")
            ->assertNoContent();

        $this->assertModelMissing($section);
    }

    public function test_a_section_that_is_in_use_cannot_be_deleted(): void
    {
        $section = $this->section($this->schoolClass(), 'A');

        // Stand-in for enrollments (Module 13), declared as it must be: ON DELETE RESTRICT.
        Schema::create('section_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
        });
        DB::table('section_dependents')->insert(['section_id' => $section->id]);

        $this->asUser($this->admin())
            ->deleteJson("/api/sections/{$section->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This section is in use (for example by enrollments) and cannot be deleted.');

        $this->assertModelExists($section);
    }

    public function test_permissions_gate_each_action(): void
    {
        $class = $this->schoolClass();
        $section = $this->section($class, 'A');

        // Teacher holds no section.* permission (teachers get scoped views in their portal).
        $teacher = $this->userWithRole('Teacher');
        $this->asUser($teacher)->getJson("/api/classes/{$class->id}/sections")->assertForbidden();
        $this->asUser($teacher)->getJson("/api/sections/{$section->id}")->assertForbidden();

        // Principal may view but not change.
        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson("/api/classes/{$class->id}/sections")->assertOk();
        $this->asUser($principal)->getJson("/api/sections/{$section->id}")->assertOk();
        $this->asUser($principal)
            ->postJson("/api/classes/{$class->id}/sections", ['name' => 'B', 'capacity' => 40])
            ->assertForbidden();
        $this->asUser($principal)
            ->putJson("/api/sections/{$section->id}", ['name' => 'A', 'capacity' => 10])
            ->assertForbidden();
        $this->asUser($principal)->deleteJson("/api/sections/{$section->id}")->assertForbidden();

        $this->assertSame(40, $section->fresh()->capacity);
    }
}
