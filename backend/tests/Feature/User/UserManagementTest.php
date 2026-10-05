<?php

namespace Tests\Feature\User;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    /** A non-admin user holding the user.* management permissions. */
    private function userManager(): User
    {
        $role = Role::firstOrCreate(['name' => 'User Manager', 'guard_name' => 'api']);
        $role->syncPermissions(['user.view', 'user.create', 'user.edit', 'user.delete']);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $this->asUser($this->adminUser())
            ->postJson('/api/users', [
                'name' => 'New Teacher',
                'email' => 'teacher@school.test',
                'password' => 'password123',
                'roles' => ['Teacher'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'teacher@school.test')
            ->assertJsonPath('data.roles', ['Teacher'])
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(
            User::where('email', 'teacher@school.test')->first()->hasRole('Teacher'),
        );
    }

    public function test_user_list_is_paginated(): void
    {
        User::factory()->count(3)->create();

        $this->asUser($this->adminUser())
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'is_active', 'roles']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_permission_middleware_blocks_users_without_user_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Student');

        $this->asUser($user)->getJson('/api/users')->assertForbidden();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create(['email' => 'target@school.test', 'password' => 'password123']);

        $this->asUser($admin)
            ->patchJson("/api/users/{$target->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->postJson('/api/auth/login', [
            'email' => 'target@school.test',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_deactivated_user_token_is_rejected_immediately(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');
        $request = $this->asUser($user);

        $user->update(['is_active' => false]);

        $request->getJson('/api/users')->assertForbidden();
    }

    public function test_user_cannot_assign_a_role_to_themselves(): void
    {
        $manager = $this->userManager();

        $this->asUser($manager)
            ->postJson("/api/users/{$manager->id}/assign-role", ['roles' => ['Admin']])
            ->assertForbidden();
    }

    public function test_user_cannot_toggle_their_own_status(): void
    {
        $manager = $this->userManager();

        $this->asUser($manager)
            ->patchJson("/api/users/{$manager->id}/toggle-status")
            ->assertForbidden();
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $manager = $this->userManager();

        $this->asUser($manager)
            ->deleteJson("/api/users/{$manager->id}")
            ->assertForbidden();
    }

    public function test_admin_can_assign_a_role_to_another_user(): void
    {
        $target = User::factory()->create();

        $this->asUser($this->adminUser())
            ->postJson("/api/users/{$target->id}/assign-role", ['roles' => ['Accountant']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['Accountant']);
    }

    public function test_assign_role_rejects_an_unknown_role(): void
    {
        $target = User::factory()->create();

        $this->asUser($this->adminUser())
            ->postJson("/api/users/{$target->id}/assign-role", ['roles' => ['Nope']])
            ->assertStatus(422);
    }

    public function test_reset_password_generates_a_temporary_password_and_allows_login(): void
    {
        $target = User::factory()->create([
            'email' => 'reset@school.test',
            'password' => 'original123',
        ]);

        $response = $this->asUser($this->adminUser())
            ->postJson("/api/users/{$target->id}/reset-password")
            ->assertOk();

        $newPassword = $response->json('password');
        $this->assertNotEmpty($newPassword);

        // Old password no longer works; new one does.
        $this->postJson('/api/auth/login', [
            'email' => 'reset@school.test',
            'password' => 'original123',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => 'reset@school.test',
            'password' => $newPassword,
        ])->assertOk();
    }

    public function test_can_link_a_user_to_a_person_record(): void
    {
        $this->asUser($this->adminUser())
            ->postJson('/api/users', [
                'name' => 'Linked User',
                'email' => 'linked@school.test',
                'password' => 'password123',
                'personable_type' => 'employee',
                'personable_id' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.personable_type', 'employee')
            ->assertJsonPath('data.personable_id', 5);
    }

    public function test_invalid_person_type_is_rejected(): void
    {
        $this->asUser($this->adminUser())
            ->postJson('/api/users', [
                'name' => 'Bad Link',
                'email' => 'bad@school.test',
                'password' => 'password123',
                'personable_type' => 'alien',
                'personable_id' => 1,
            ])
            ->assertStatus(422);
    }
}
