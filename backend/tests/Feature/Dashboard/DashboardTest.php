<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DashboardAudience;
use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();
        if ($roles !== []) {
            $user->assignRole($roles);
        }

        return $user;
    }

    private function dashboardFor(User $user): TestResponse
    {
        return $this->asUser($user)->getJson('/api/dashboard')->assertOk();
    }

    /** @return Collection<string, array<string, mixed>> */
    private function widgets(TestResponse $response): Collection
    {
        return collect($response->json('data.widgets'))->keyBy('key');
    }

    private function assertHasWidgets(Collection $widgets, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertTrue($widgets->has($key), "Expected widget [{$key}] to be present.");
        }
    }

    private function assertLacksWidgets(Collection $widgets, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertFalse($widgets->has($key), "Widget [{$key}] must not be present.");
        }
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_admin_gets_the_admin_dashboard(): void
    {
        $response = $this->dashboardFor($this->userWithRoles('Admin'));
        $widgets = $this->widgets($response);

        $response->assertJsonPath('data.audience', 'admin');
        $this->assertHasWidgets($widgets, [
            'profile', 'school', 'user_stats', 'recent_users',
            'student_stats', 'attendance_today', 'pending_fees', 'recent_notices',
        ]);
        $this->assertLacksWidgets($widgets, ['todays_classes', 'children', 'my_results', 'homework']);
    }

    public function test_user_stats_match_the_source_tables(): void
    {
        $admin = $this->userWithRoles('Admin');
        User::factory()->count(3)->create()->each(fn (User $u) => $u->assignRole('Teacher'));
        User::factory()->count(2)->create(['is_active' => false]);
        User::factory()->create()->delete(); // soft-deleted: must not be counted

        $stats = $this->widgets($this->dashboardFor($admin))['user_stats']['data'];
        $byRole = collect($stats['by_role'])->pluck('count', 'role');

        $this->assertSame(6, $stats['total']);
        $this->assertSame(4, $stats['active']);
        $this->assertSame(2, $stats['inactive']);
        $this->assertSame(1, $byRole['Admin']);
        $this->assertSame(3, $byRole['Teacher']);
        $this->assertSame(0, $byRole['Student']);
    }

    public function test_recent_users_lists_the_newest_accounts_first(): void
    {
        $admin = $this->userWithRoles('Admin');
        $newest = User::factory()->create(['email' => 'newest@school.test']);

        $items = $this->widgets($this->dashboardFor($admin))['recent_users']['data']['items'];

        $this->assertSame($newest->id, $items[0]['id']);
        $this->assertLessThanOrEqual(5, count($items));
    }

    public function test_teacher_gets_the_teacher_dashboard_without_admin_widgets(): void
    {
        $response = $this->dashboardFor($this->userWithRoles('Teacher'));
        $widgets = $this->widgets($response);

        $response->assertJsonPath('data.audience', 'teacher');
        $this->assertHasWidgets($widgets, ['profile', 'school', 'todays_classes', 'pending_marks', 'homework', 'recent_notices']);
        $this->assertLacksWidgets($widgets, ['user_stats', 'recent_users', 'student_stats', 'pending_fees', 'children']);
    }

    public function test_parent_gets_the_parent_dashboard(): void
    {
        $response = $this->dashboardFor($this->userWithRoles('Parent'));
        $widgets = $this->widgets($response);

        $response->assertJsonPath('data.audience', 'parent');
        $this->assertHasWidgets($widgets, ['children', 'child_results', 'child_fees', 'homework']);
        $this->assertLacksWidgets($widgets, ['user_stats', 'recent_users', 'todays_classes', 'my_results']);
    }

    public function test_student_dashboard_contains_no_other_users_data(): void
    {
        User::factory()->create(['email' => 'secret.peer@school.test']);
        $this->userWithRoles('Admin')->update(['email' => 'secret.admin@school.test']);
        $student = $this->userWithRoles('Student');

        $response = $this->dashboardFor($student);
        $widgets = $this->widgets($response);

        $response->assertJsonPath('data.audience', 'student');
        $this->assertHasWidgets($widgets, ['my_attendance', 'my_results', 'my_fees']);
        $this->assertLacksWidgets($widgets, ['user_stats', 'recent_users', 'children']);
        $this->assertSame($student->email, $widgets['profile']['data']['email']);

        $body = $response->getContent();
        $this->assertStringNotContainsString('secret.peer@school.test', $body);
        $this->assertStringNotContainsString('secret.admin@school.test', $body);
    }

    public function test_staff_widgets_are_gated_by_permission(): void
    {
        // Accountant: fee.*, accounts.*, student.view — but no user.view / admission.view.
        $accountant = $this->widgets($this->dashboardFor($this->userWithRoles('Accountant')));
        $this->assertHasWidgets($accountant, ['pending_fees', 'monthly_finance', 'student_stats']);
        $this->assertLacksWidgets($accountant, ['user_stats', 'recent_users', 'recent_admissions', 'teacher_stats']);

        // HR Manager: teacher.* and user.view — but no fee.view.
        $hr = $this->widgets($this->dashboardFor($this->userWithRoles('HR Manager')));
        $this->assertHasWidgets($hr, ['user_stats', 'recent_users', 'teacher_stats']);
        $this->assertLacksWidgets($hr, ['pending_fees', 'monthly_finance']);
    }

    public function test_pending_widgets_are_labelled_and_carry_no_data(): void
    {
        $widget = $this->widgets($this->dashboardFor($this->userWithRoles('Teacher')))['pending_marks'];

        $this->assertSame('pending', $widget['status']);
        $this->assertSame('17 — Marks Entry', $widget['module']);
        $this->assertNull($widget['data']);
    }

    public function test_school_widget_reflects_settings(): void
    {
        SchoolSetting::query()->firstOrCreate([])->update(['name' => 'Riverdale School', 'eiin' => '123456']);
        SchoolSetting::forgetCache();

        $school = $this->widgets($this->dashboardFor($this->userWithRoles('Student')))['school']['data'];

        $this->assertSame('Riverdale School', $school['name']);
        $this->assertSame('123456', $school['eiin']);
        $this->assertArrayNotHasKey('email_settings', $school);
    }

    public function test_login_payload_includes_the_audience(): void
    {
        User::factory()->create(['email' => 'teach@school.test', 'password' => 'password123'])
            ->assignRole('Teacher');

        $this->postJson('/api/auth/login', ['email' => 'teach@school.test', 'password' => 'password123'])
            ->assertOk()
            ->assertJsonPath('user.audience', 'teacher');
    }

    public function test_audience_precedence(): void
    {
        $this->assertSame(DashboardAudience::Admin, DashboardAudience::forUser($this->userWithRoles('Principal', 'Parent')));
        $this->assertSame(DashboardAudience::Teacher, DashboardAudience::forUser($this->userWithRoles('Teacher', 'Parent')));
        $this->assertSame(DashboardAudience::Staff, DashboardAudience::forUser($this->userWithRoles('Accountant', 'Parent')));
        $this->assertSame(DashboardAudience::Parent, DashboardAudience::forUser($this->userWithRoles('Parent', 'Student')));
        $this->assertSame(DashboardAudience::Staff, DashboardAudience::forUser($this->userWithRoles()));
    }
}
