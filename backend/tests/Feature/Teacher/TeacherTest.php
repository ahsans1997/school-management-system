<?php

namespace Tests\Feature\Teacher;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherTest extends TestCase
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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Farhana Akter',
            'designation' => 'Assistant Teacher',
            'department' => 'Science',
            'qualification' => 'MSc in Physics',
            'joining_date' => '2020-01-05',
            'phone' => '01711-222333',
            'salary' => '32000.50',
            ...$overrides,
        ];
    }

    private function teacher(string $name = 'Kamal Hossain', array $extra = []): Teacher
    {
        static $n = 0;
        $n++;

        return Teacher::create([
            'name' => $name, 'employee_id' => "E-{$n}", 'joining_date' => '2019-06-01', 'phone' => '01700000000',
            ...$extra,
        ]);
    }

    public function test_creating_a_teacher_generates_an_employee_id_and_keeps_salary_exact(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)->postJson('/api/teachers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.employee_id', 'T-0001')
            ->assertJsonPath('data.salary', '32000.50')
            ->assertJsonPath('data.joining_date', '2020-01-05')
            ->assertJsonPath('data.account', null);

        // A hand-typed ID that collides with the next generated one is skipped.
        $this->asUser($admin)->postJson('/api/teachers', $this->payload(['employee_id' => 'T-0002']))->assertCreated();
        $this->asUser($admin)->postJson('/api/teachers', $this->payload(['name' => 'Next']))
            ->assertJsonPath('data.employee_id', 'T-0003');
    }

    public function test_fields_are_validated(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/teachers', ['phone' => 'call me', 'salary' => '12.345', 'employee_id' => 'bad id!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'joining_date', 'phone', 'salary', 'employee_id'])
            ->assertJsonPath('errors.salary.0', 'The salary can have at most 2 decimal places.');

        $this->asUser($this->admin())
            ->postJson('/api/teachers', $this->payload(['salary' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('salary');
    }

    public function test_a_teacher_can_be_created_with_a_login_that_signs_in_as_a_teacher(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/teachers', $this->payload([
                'account' => ['email' => 'farhana@school.test', 'password' => 'secret-pass-1'],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'farhana@school.test');

        $user = Teacher::query()->sole()->account;
        $this->assertSame(['Teacher'], $user->getRoleNames()->all());
        $this->assertSame('teacher', $user->personable_type);

        $this->postJson('/api/auth/login', ['email' => 'farhana@school.test', 'password' => 'secret-pass-1'])
            ->assertOk()
            ->assertJsonPath('user.audience', 'teacher');
    }

    public function test_creating_a_login_needs_teacher_edit_and_happens_once(): void
    {
        Role::create(['name' => 'Clerk', 'guard_name' => 'api'])->givePermissionTo('teacher.create');
        $clerk = $this->userWithRole('Clerk');

        $this->asUser($clerk)
            ->postJson('/api/teachers', $this->payload(['account' => ['email' => 'x@school.test', 'password' => 'secret-pass-1']]))
            ->assertForbidden();
        $this->asUser($clerk)->postJson('/api/teachers', $this->payload())->assertCreated();

        $teacher = Teacher::query()->sole();
        $admin = $this->admin();
        $this->asUser($admin)
            ->postJson("/api/teachers/{$teacher->id}/account", ['email' => 'farhana@school.test', 'password' => 'secret-pass-1'])
            ->assertCreated()
            ->assertJsonPath('data.account.email', 'farhana@school.test');
        $this->asUser($admin)
            ->postJson("/api/teachers/{$teacher->id}/account", ['email' => 'other@school.test', 'password' => 'secret-pass-1'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This teacher already has a login account.');
    }

    public function test_list_is_searchable_and_counts_assignments(): void
    {
        $this->teacher('Zara Rahman', ['department' => 'Arts']);
        $this->teacher('Kamal Hossain', ['employee_id' => 'T-0420', 'department' => 'Science']);
        $admin = $this->admin();

        $this->asUser($admin)->getJson('/api/teachers')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Kamal Hossain')
            ->assertJsonPath('data.0.assignments_count', 0)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        foreach (['zara' => 'Zara Rahman', 'T-0420' => 'Kamal Hossain', 'Arts' => 'Zara Rahman'] as $search => $name) {
            $this->asUser($admin)->getJson('/api/teachers?search='.urlencode($search))
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', $name);
        }
    }

    public function test_updating_renames_the_login(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();
        $this->asUser($admin)->postJson("/api/teachers/{$teacher->id}/account", ['email' => 'kamal@school.test', 'password' => 'secret-pass-1']);

        $this->asUser($admin)
            ->putJson("/api/teachers/{$teacher->id}", $this->payload(['name' => 'Kamal Uddin', 'employee_id' => $teacher->employee_id]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kamal Uddin');

        $this->assertSame('Kamal Uddin', $teacher->fresh()->account->name);
    }

    public function test_archiving_needs_a_handover_and_switches_the_login_off(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();
        $this->asUser($admin)->postJson("/api/teachers/{$teacher->id}/account", ['email' => 'kamal@school.test', 'password' => 'secret-pass-1']);

        $class = SchoolClass::create(['name' => 'Class 6', 'numeric_level' => 6]);
        $section = $class->sections()->create(['name' => 'A', 'capacity' => 40]);
        $section->forceFill(['class_teacher_id' => $teacher->id])->save();

        $this->asUser($admin)->deleteJson("/api/teachers/{$teacher->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This teacher still has subjects or a class assigned. Hand them over first.');

        $section->forceFill(['class_teacher_id' => null])->save();
        $this->asUser($admin)->deleteJson("/api/teachers/{$teacher->id}")->assertNoContent();

        $this->assertSoftDeleted($teacher);
        $this->assertFalse($teacher->account()->first()->is_active);
        $this->asUser($admin)->getJson('/api/teachers')->assertJsonCount(0, 'data');
        $this->asUser($admin)->getJson('/api/teachers?archived=1')->assertJsonCount(1, 'data');
        $this->asUser($admin)->getJson("/api/teachers/{$teacher->id}")->assertOk();
        $this->asUser($admin)->putJson("/api/teachers/{$teacher->id}", $this->payload())->assertNotFound();

        // Restoring brings the record back; the login stays off until reactivated under Users.
        $this->asUser($admin)->postJson("/api/teachers/{$teacher->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.archived_at', null)
            ->assertJsonPath('data.account.is_active', false);
        $this->asUser($admin)->postJson("/api/teachers/{$teacher->id}/restore")->assertStatus(409);
    }

    public function test_photos_and_documents_use_the_shared_private_files(): void
    {
        Storage::fake('local');
        $teacher = $this->teacher();
        $admin = $this->admin();

        $photoUrl = $this->asUser($admin)
            ->postJson("/api/teachers/{$teacher->id}/photo", ['photo' => UploadedFile::fake()->image('kamal.jpg')])
            ->assertOk()
            ->json('data.photo_url');
        $this->assertStringStartsWith("teachers/{$teacher->id}/", $teacher->fresh()->photo_path);

        $doc = $this->asUser($admin)
            ->postJson("/api/teachers/{$teacher->id}/documents", [
                'title' => 'Appointment letter',
                'file' => UploadedFile::fake()->create('appointment.pdf', 20, 'application/pdf'),
            ])
            ->assertCreated()
            ->json('data');

        $this->flushHeaders();
        $this->get($photoUrl)->assertOk();
        $this->get($doc['download_url'])->assertOk()->assertDownload('appointment.pdf');

        // A teacher's document can't be reached through a student's URL.
        $student = Student::create([
            'name' => 'S', 'dob' => '2014-01-01', 'gender' => 'male', 'admission_date' => '2026-01-01',
            'student_id' => '10000001', 'admission_no' => '2026-0001',
        ]);
        $this->asUser($admin)->deleteJson("/api/students/{$student->id}/documents/{$doc['id']}")->assertNotFound();
        $this->asUser($admin)->deleteJson("/api/teachers/{$teacher->id}/documents/{$doc['id']}")->assertNoContent();
    }

    public function test_permissions_gate_each_action(): void
    {
        $teacher = $this->teacher();

        // HR manages teachers.
        $hr = $this->userWithRole('HR Manager');
        $this->asUser($hr)->postJson('/api/teachers', $this->payload())->assertCreated();
        $this->asUser($hr)->putJson("/api/teachers/{$teacher->id}", $this->payload(['employee_id' => $teacher->employee_id]))->assertOk();

        // Principal may view only.
        $principal = $this->userWithRole('Principal');
        $this->asUser($principal)->getJson("/api/teachers/{$teacher->id}")->assertOk();
        $this->asUser($principal)->postJson('/api/teachers', $this->payload())->assertForbidden();
        $this->asUser($principal)->deleteJson("/api/teachers/{$teacher->id}")->assertForbidden();

        // Teachers don't browse the staff register.
        $this->asUser($this->userWithRole('Teacher'))->getJson('/api/teachers')->assertForbidden();
    }

    public function test_dashboard_counts_teachers(): void
    {
        $this->teacher('One');
        $this->teacher('Two')->delete();

        $stats = collect($this->asUser($this->admin())->getJson('/api/dashboard')->json('data.widgets'))
            ->firstWhere('key', 'teacher_stats');

        $this->assertSame('ready', $stats['status']);
        $this->assertSame(['total' => 1, 'with_login' => 0, 'unassigned' => 1, 'archived' => 1], $stats['data']);
    }
}
