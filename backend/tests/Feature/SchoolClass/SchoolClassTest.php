<?php

namespace Tests\Feature\SchoolClass;

use App\Enums\Medium;
use App\Enums\Shift;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolClassTest extends TestCase
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

    private function schoolClass(string $name, int $level, array $extra = []): SchoolClass
    {
        return SchoolClass::create(['name' => $name, 'numeric_level' => $level, ...$extra]);
    }

    public function test_list_is_paginated_and_ordered_by_level(): void
    {
        $this->schoolClass('Class 10', 10);
        $this->schoolClass('Class 1', 1)->sections()->create(['name' => 'A', 'capacity' => 40]);
        $this->schoolClass('KG', 0);

        $this->asUser($this->admin())
            ->getJson('/api/classes')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'KG')
            ->assertJsonPath('data.1.name', 'Class 1')
            ->assertJsonPath('data.2.name', 'Class 10')
            ->assertJsonPath('data.0.sections_count', 0)
            ->assertJsonPath('data.1.sections_count', 1)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'numeric_level', 'shift', 'medium', 'sections_count']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_a_class_is_created_with_optional_shift_and_medium(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/classes', [
                'name' => 'Class 6 (Morning)', 'numeric_level' => 6, 'shift' => 'morning', 'medium' => 'english',
            ])
            ->assertCreated()
            ->assertJsonPath('data.numeric_level', 6)
            ->assertJsonPath('data.shift', 'morning')
            ->assertJsonPath('data.medium', 'english');

        $class = SchoolClass::query()->where('name', 'Class 6 (Morning)')->sole();
        $this->assertSame(Shift::Morning, $class->shift);
        $this->assertSame(Medium::English, $class->medium);
    }

    public function test_shift_and_medium_may_be_omitted(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/classes', ['name' => 'Class 7', 'numeric_level' => 7])
            ->assertCreated()
            ->assertJsonPath('data.shift', null)
            ->assertJsonPath('data.medium', null);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->schoolClass('Class 6', 6);

        $this->asUser($this->admin())
            ->postJson('/api/classes', ['name' => 'Class 6', 'numeric_level' => 6])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_unknown_shift_or_medium_is_rejected(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/classes', [
                'name' => 'Class 8', 'numeric_level' => 8, 'shift' => 'midnight', 'medium' => 'klingon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['shift', 'medium']);
    }

    public function test_numeric_level_must_be_an_integer_in_range(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson('/api/classes', ['name' => 'Class X', 'numeric_level' => 'ten'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numeric_level');

        $this->asUser($admin)
            ->postJson('/api/classes', ['name' => 'Class Y', 'numeric_level' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('numeric_level');
    }

    public function test_a_class_can_be_updated_without_clashing_with_itself(): void
    {
        $class = $this->schoolClass('Class 9', 9);

        $this->asUser($this->admin())
            ->putJson("/api/classes/{$class->id}", ['name' => 'Class 9', 'numeric_level' => 9, 'shift' => 'day'])
            ->assertOk()
            ->assertJsonPath('data.shift', 'day');
    }

    public function test_an_unused_class_can_be_deleted(): void
    {
        $class = $this->schoolClass('Class 5', 5);

        $this->asUser($this->admin())
            ->deleteJson("/api/classes/{$class->id}")
            ->assertNoContent();

        $this->assertModelMissing($class);
    }

    public function test_a_class_with_sections_cannot_be_deleted(): void
    {
        $class = $this->schoolClass('Class 4', 4);
        $class->sections()->create(['name' => 'A', 'capacity' => 40]);

        $this->asUser($this->admin())
            ->deleteJson("/api/classes/{$class->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This class is in use (for example by sections or enrollments) and cannot be deleted.');

        $this->assertModelExists($class);
    }

    public function test_permissions_gate_each_action(): void
    {
        $class = $this->schoolClass('Class 3', 3);

        // Teacher holds no class.* permission.
        $this->asUser($this->userWithRole('Teacher'))->getJson('/api/classes')->assertForbidden();

        // Principal may view but not change.
        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson('/api/classes')->assertOk();
        $this->asUser($principal)
            ->postJson('/api/classes', ['name' => 'Class 11', 'numeric_level' => 11])
            ->assertForbidden();
        $this->asUser($principal)->deleteJson("/api/classes/{$class->id}")->assertForbidden();
    }
}
