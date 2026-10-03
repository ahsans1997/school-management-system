<?php

namespace Tests\Feature\AcademicYear;

use App\Models\AcademicYear;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicYearTest extends TestCase
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

    private function year(string $name, bool $current = false): AcademicYear
    {
        return AcademicYear::create([
            'name' => $name,
            'start_date' => "{$name}-01-01",
            'end_date' => "{$name}-12-31",
            'is_current' => $current,
        ]);
    }

    public function test_list_is_paginated_and_newest_first(): void
    {
        $this->year('2025', current: true);
        $this->year('2026');

        $this->asUser($this->admin())
            ->getJson('/api/academic-years')
            ->assertOk()
            ->assertJsonPath('data.0.name', '2026')
            ->assertJsonPath('data.1.name', '2025')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'start_date', 'end_date', 'is_current']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_first_year_becomes_current_automatically_and_later_ones_do_not(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)
            ->postJson('/api/academic-years', ['name' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31'])
            ->assertCreated()
            ->assertJsonPath('data.is_current', true);

        $this->asUser($admin)
            ->postJson('/api/academic-years', ['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
            ->assertCreated()
            ->assertJsonPath('data.is_current', false);
    }

    public function test_is_current_cannot_be_set_through_create_or_update(): void
    {
        $admin = $this->admin();
        $this->year('2025', current: true);

        $this->asUser($admin)
            ->postJson('/api/academic-years', [
                'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_current', false);

        $this->assertSame('2025', AcademicYear::query()->where('is_current', true)->value('name'));
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/academic-years', ['name' => '2026', 'start_date' => '2026-12-31', 'end_date' => '2026-01-01'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->year('2026', current: true);

        $this->asUser($this->admin())
            ->postJson('/api/academic-years', ['name' => '2026', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_overlapping_years_are_rejected_but_adjacent_years_are_allowed(): void
    {
        $admin = $this->admin();
        $this->year('2026', current: true);

        $this->asUser($admin)
            ->postJson('/api/academic-years', ['name' => '2026-27', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date' => 'overlap the 2026 academic year']);

        $this->asUser($admin)
            ->postJson('/api/academic-years', ['name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'])
            ->assertCreated();
    }

    public function test_a_year_can_be_updated_without_clashing_with_itself(): void
    {
        $year = $this->year('2026', current: true);

        $this->asUser($this->admin())
            ->putJson("/api/academic-years/{$year->id}", [
                'name' => '2026 Session', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', '2026 Session');
    }

    public function test_set_current_switches_the_single_current_year(): void
    {
        $old = $this->year('2025', current: true);
        $new = $this->year('2026');

        $this->asUser($this->admin())
            ->postJson("/api/academic-years/{$new->id}/set-current")
            ->assertOk()
            ->assertJsonPath('data.is_current', true);

        $this->assertSame(1, AcademicYear::query()->where('is_current', true)->count());
        $this->assertTrue($new->fresh()->is_current);
        $this->assertFalse($old->fresh()->is_current);
    }

    public function test_database_rejects_a_second_current_year(): void
    {
        $this->year('2025', current: true);
        $other = $this->year('2026');

        // Bypass the app entirely: the unique index must still refuse.
        $this->expectException(QueryException::class);
        AcademicYear::query()->whereKey($other->id)->update(['is_current' => true]);
    }

    public function test_the_current_year_cannot_be_deleted(): void
    {
        $current = $this->year('2026', current: true);

        $this->asUser($this->admin())
            ->deleteJson("/api/academic-years/{$current->id}")
            ->assertStatus(409);

        $this->assertModelExists($current);
    }

    public function test_a_year_that_is_in_use_cannot_be_deleted(): void
    {
        $this->year('2026', current: true);
        $past = $this->year('2025');

        // Stand-in for a future referencing table (classes, enrollments…),
        // declared the way every such table must be: ON DELETE RESTRICT.
        Schema::create('year_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
        });
        DB::table('year_dependents')->insert(['academic_year_id' => $past->id]);

        $this->asUser($this->admin())
            ->deleteJson("/api/academic-years/{$past->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This academic year is in use (for example by classes or enrollments) and cannot be deleted.');

        $this->assertModelExists($past);
    }

    public function test_an_unused_past_year_can_be_deleted(): void
    {
        $this->year('2026', current: true);
        $past = $this->year('2025');

        $this->asUser($this->admin())
            ->deleteJson("/api/academic-years/{$past->id}")
            ->assertNoContent();

        $this->assertModelMissing($past);
    }

    public function test_permissions_gate_each_action(): void
    {
        $year = $this->year('2026', current: true);

        // Teacher holds no academic_year.* permission.
        $this->asUser($this->userWithRole('Teacher'))->getJson('/api/academic-years')->assertForbidden();

        // Principal may view but not change.
        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson('/api/academic-years')->assertOk();
        $this->asUser($principal)
            ->postJson('/api/academic-years', ['name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31'])
            ->assertForbidden();
        $this->asUser($principal)->postJson("/api/academic-years/{$year->id}/set-current")->assertForbidden();
    }

    public function test_current_year_cache_is_refreshed_on_switch_with_a_serializing_store(): void
    {
        config(['cache.default' => 'database']);
        $admin = $this->admin();
        $this->year('2025', current: true);
        $next = $this->year('2026');

        $this->assertSame('2025', AcademicYear::current()?->name); // cache primed

        $this->asUser($admin)->postJson("/api/academic-years/{$next->id}/set-current")->assertOk();

        $this->assertSame('2026', AcademicYear::current()?->name);
        $this->asUser($admin)
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.widgets.1.key', 'school')
            ->assertJsonPath('data.widgets.1.data.academic_year', '2026');
    }
}
