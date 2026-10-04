<?php

namespace Tests\Feature\Guardian;

use App\Models\Guardian;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuardianTest extends TestCase
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

    private function guardian(string $name = 'Rahima Begum', string $phone = '01711000000', array $extra = []): Guardian
    {
        return Guardian::create(['name' => $name, 'phone' => $phone, ...$extra]);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Abdul Karim',
            'phone' => '+880 1712-345678',
            'email' => 'karim@example.com',
            'occupation' => 'Farmer',
            'address' => 'Village Road, Sylhet',
            ...$overrides,
        ];
    }

    public function test_list_is_paginated_ordered_by_name_and_searchable(): void
    {
        $this->guardian('Zara Ahmed', '01800000001');
        $this->guardian('Abdul Karim', '01900000002', ['email' => 'karim@example.com']);
        $this->guardian('Mina Das', '01700000003');
        $admin = $this->admin();

        $this->asUser($admin)
            ->getJson('/api/guardians')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Abdul Karim')
            ->assertJsonPath('data.2.name', 'Zara Ahmed')
            ->assertJsonPath('data.0.account', null)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'phone', 'email', 'occupation', 'address', 'account']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        // By phone fragment, name fragment, or email.
        foreach (['0170000' => 'Mina Das', 'zara' => 'Zara Ahmed', 'karim@' => 'Abdul Karim'] as $search => $name) {
            $this->asUser($admin)
                ->getJson('/api/guardians?search='.urlencode($search))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', $name);
        }
    }

    public function test_a_guardian_is_created_without_a_login(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/guardians', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Abdul Karim')
            ->assertJsonPath('data.phone', '+880 1712-345678')
            ->assertJsonPath('data.account', null);

        $this->assertSame(0, User::query()->where('personable_type', 'guardian')->count());
    }

    public function test_only_name_and_a_valid_phone_are_required(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson('/api/guardians', ['name' => 'Mina Das', 'phone' => '01700000003'])
            ->assertCreated()
            ->assertJsonPath('data.email', null);

        $this->asUser($admin)
            ->postJson('/api/guardians', ['name' => '', 'phone' => 'call me', 'email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone', 'email'])
            ->assertJsonPath('errors.phone.0', 'Enter a valid phone number (digits, optionally with +, spaces or dashes).');
    }

    public function test_a_guardian_can_be_created_with_a_parent_login_that_signs_in(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/guardians', $this->payload([
                'account' => ['email' => 'karim.parent@school.test', 'password' => 'secret-pass-1'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'karim.parent@school.test')
            ->assertJsonPath('data.account.is_active', true);

        $guardian = Guardian::query()->sole();
        $user = $guardian->account;
        $this->assertNotNull($user);
        $this->assertSame('Abdul Karim', $user->name);
        $this->assertTrue($user->hasRole('Parent'));
        $this->assertSame(['Parent'], $user->getRoleNames()->all());
        $this->assertTrue(Hash::check('secret-pass-1', $user->password));
        $this->assertTrue($user->personable->is($guardian));

        // The login works and lands in the parent area.
        $this->postJson('/api/auth/login', ['email' => 'karim.parent@school.test', 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonPath('user.email', 'karim.parent@school.test')
            ->assertJsonPath('user.audience', 'parent');
    }

    public function test_the_login_role_cannot_be_chosen_by_the_client(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/guardians', $this->payload([
                'account' => ['email' => 'sneaky@school.test', 'password' => 'secret-pass-1', 'roles' => ['Admin']],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('account');

        $this->assertSame(0, Guardian::query()->count());
    }

    public function test_guardian_and_login_are_created_together_or_not_at_all(): void
    {
        User::factory()->create(['email' => 'taken@school.test']);

        $this->asUser($this->admin())
            ->postJson('/api/guardians', $this->payload([
                'account' => ['email' => 'taken@school.test', 'password' => 'short'],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account.email', 'account.password']);

        $this->assertSame(0, Guardian::query()->count());
    }

    public function test_creating_a_login_needs_guardian_edit(): void
    {
        // Receptionist holds guardian.create but not guardian.edit.
        $receptionist = $this->userWithRole('Receptionist');

        $this->asUser($receptionist)
            ->postJson('/api/guardians', $this->payload([
                'account' => ['email' => 'karim.parent@school.test', 'password' => 'secret-pass-1'],
            ]))
            ->assertForbidden();

        $this->asUser($receptionist)
            ->postJson('/api/guardians', $this->payload())
            ->assertCreated();

        $guardian = Guardian::query()->sole();
        $this->asUser($receptionist)
            ->postJson("/api/guardians/{$guardian->id}/account", ['email' => 'karim.parent@school.test', 'password' => 'secret-pass-1'])
            ->assertForbidden();

        $this->assertNull($guardian->account);
    }

    public function test_a_login_can_be_added_later_but_only_once(): void
    {
        $guardian = $this->guardian();
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson("/api/guardians/{$guardian->id}/account", ['email' => 'rahima@school.test', 'password' => 'secret-pass-1'])
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'rahima@school.test');

        $this->asUser($admin)
            ->postJson("/api/guardians/{$guardian->id}/account", ['email' => 'rahima2@school.test', 'password' => 'secret-pass-1'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This guardian already has a login account.');

        $this->assertSame(1, User::query()->where('personable_type', 'guardian')->count());
    }

    public function test_updating_a_guardian_renames_its_login(): void
    {
        $guardian = $this->guardian();
        $admin = $this->admin();
        $this->asUser($admin)
            ->postJson("/api/guardians/{$guardian->id}/account", ['email' => 'rahima@school.test', 'password' => 'secret-pass-1'])
            ->assertCreated();

        $this->asUser($admin)
            ->putJson("/api/guardians/{$guardian->id}", ['name' => 'Rahima Khatun', 'phone' => '01711000000'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rahima Khatun')
            ->assertJsonPath('data.account.email', 'rahima@school.test');

        $this->assertSame('Rahima Khatun', $guardian->fresh()->account->name);
    }

    public function test_a_guardian_without_a_login_can_be_deleted(): void
    {
        $guardian = $this->guardian();

        $this->asUser($this->admin())
            ->deleteJson("/api/guardians/{$guardian->id}")
            ->assertNoContent();

        $this->assertModelMissing($guardian);
    }

    public function test_a_guardian_with_a_login_cannot_be_deleted_until_the_login_is(): void
    {
        $guardian = $this->guardian();
        $admin = $this->admin();
        $this->asUser($admin)
            ->postJson("/api/guardians/{$guardian->id}/account", ['email' => 'rahima@school.test', 'password' => 'secret-pass-1'])
            ->assertCreated();

        $this->asUser($admin)
            ->deleteJson("/api/guardians/{$guardian->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This guardian has a login account. Delete the account under Users first.');

        // Once the account is deleted under Users, the guardian can go.
        $this->asUser($admin)->deleteJson("/api/users/{$guardian->account->id}")->assertNoContent();
        $this->asUser($admin)->deleteJson("/api/guardians/{$guardian->id}")->assertNoContent();
        $this->assertModelMissing($guardian);
    }

    public function test_permissions_gate_each_action(): void
    {
        $guardian = $this->guardian();

        // Principal may view but not change.
        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson('/api/guardians')->assertOk();
        $this->asUser($principal)->getJson("/api/guardians/{$guardian->id}")->assertOk();
        $this->asUser($principal)->postJson('/api/guardians', $this->payload())->assertForbidden();
        $this->asUser($principal)->putJson("/api/guardians/{$guardian->id}", $this->payload())->assertForbidden();
        $this->asUser($principal)->deleteJson("/api/guardians/{$guardian->id}")->assertForbidden();

        // Teachers and parents can't read the guardian register at all.
        foreach (['Teacher', 'Parent'] as $role) {
            $this->asUser($this->userWithRole($role))->getJson('/api/guardians')->assertForbidden();
        }

        $this->assertSame('Rahima Begum', $guardian->fresh()->name);
    }

    public function test_users_api_validates_guardian_links(): void
    {
        $guardian = $this->guardian();
        $admin = $this->admin();
        $link = fn (array $user) => $this->asUser($admin)->postJson('/api/users', [
            'name' => 'Linked', 'password' => 'password123', 'personable_type' => 'guardian', ...$user,
        ]);

        $link(['email' => 'ghost@school.test', 'personable_id' => 999])
            ->assertStatus(422)
            ->assertJsonPath('errors.personable_id.0', 'That person record does not exist.');

        $link(['email' => 'first@school.test', 'personable_id' => $guardian->id])->assertCreated();

        $link(['email' => 'second@school.test', 'personable_id' => $guardian->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.personable_id.0', 'That person already has a login account.');

        // The linked user may keep its own link on update.
        $first = User::query()->where('email', 'first@school.test')->sole();
        $this->asUser($admin)
            ->putJson("/api/users/{$first->id}", [
                'name' => 'Linked', 'email' => 'first@school.test',
                'personable_type' => 'guardian', 'personable_id' => $guardian->id,
            ])
            ->assertOk();
    }
}
