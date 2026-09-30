<?php

namespace Tests\Feature\RolePermission;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    public function test_seeder_creates_all_roles_and_permissions_and_admin_holds_all(): void
    {
        $expectedPermissions = collect(config('permissions.groups'))->flatten()->unique()->count();
        $expectedRoles = count(config('permissions.roles'));

        $this->assertSame($expectedRoles, Role::count());
        $this->assertSame(
            $expectedPermissions,
            Role::findByName('Admin', 'api')->permissions()->count(),
        );
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
    }

    public function test_permission_middleware_blocks_a_user_without_the_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Student'); // has only dashboard.view

        $this->asUser($user)->getJson('/api/roles')->assertForbidden();
    }

    public function test_user_with_permission_can_list_roles_paginated(): void
    {
        $this->asUser($this->adminUser())
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'is_system', 'permissions']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_can_create_a_role_with_permissions(): void
    {
        $this->asUser($this->adminUser())
            ->postJson('/api/roles', [
                'name' => 'Content Editor',
                'permissions' => ['website.pages', 'website.notices'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Content Editor')
            ->assertJsonPath('data.is_system', false);

        $this->assertEqualsCanonicalizing(
            ['website.pages', 'website.notices'],
            Role::findByName('Content Editor', 'api')->permissions->pluck('name')->all(),
        );
    }

    public function test_can_sync_permissions_to_a_role(): void
    {
        $role = Role::create(['name' => 'Exam Officer', 'guard_name' => 'api']);

        $this->asUser($this->adminUser())
            ->putJson("/api/roles/{$role->id}/permissions", [
                'permissions' => ['exam.view', 'exam.create', 'exam.edit'],
            ])
            ->assertOk();

        $this->assertEqualsCanonicalizing(
            ['exam.view', 'exam.create', 'exam.edit'],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_sync_rejects_an_unknown_permission(): void
    {
        $role = Role::create(['name' => 'Bogus', 'guard_name' => 'api']);

        $this->asUser($this->adminUser())
            ->putJson("/api/roles/{$role->id}/permissions", [
                'permissions' => ['does.not.exist'],
            ])
            ->assertStatus(422);
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $adminRole = Role::findByName('Admin', 'api');

        $this->asUser($this->adminUser())
            ->deleteJson("/api/roles/{$adminRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
    }

    public function test_admin_role_permissions_cannot_be_modified(): void
    {
        $adminRole = Role::findByName('Admin', 'api');

        $this->asUser($this->adminUser())
            ->putJson("/api/roles/{$adminRole->id}/permissions", [
                'permissions' => ['student.view'],
            ])
            ->assertForbidden();
    }

    public function test_a_custom_role_can_be_deleted(): void
    {
        $role = Role::create(['name' => 'Temporary', 'guard_name' => 'api']);

        $this->asUser($this->adminUser())
            ->deleteJson("/api/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['name' => 'Temporary']);
    }
}
