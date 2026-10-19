<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_gets_one_working_demo_login(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
        $this->seed(DemoUserSeeder::class); // idempotent

        $roles = array_keys(config('permissions.roles'));
        $this->assertSame(count($roles), User::count());
        foreach (DemoUserSeeder::USERS as [$email, , $role]) {
            $this->assertSame([$role], User::query()->where('email', $email)->sole()->getRoleNames()->all());
        }
        $this->assertEqualsCanonicalizing($roles, array_column(DemoUserSeeder::USERS, 2));

        $this->postJson('/api/auth/login', ['email' => 'principal@school.test', 'password' => DemoUserSeeder::PASSWORD])
            ->assertOk()
            ->assertJsonPath('user.audience', 'admin');
    }

    public function test_portal_logins_belong_to_person_records(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $types = User::query()->whereNotNull('personable_type')->pluck('personable_type', 'email')->all();
        $this->assertSame(['parent@school.test' => 'guardian', 'student@school.test' => 'student', 'teacher@school.test' => 'teacher'], collect($types)->sortKeys()->all());

        // The demo parent is the demo student's one primary guardian.
        $student = Student::query()->where('student_id', 'DEMO-STUDENT')->sole();
        $this->assertSame(['Demo Parent'], $student->guardians()->pluck('name')->all());
        $this->assertTrue($student->guardians()->first()->pivot->is_primary);
    }
}
