<?php

namespace Tests\Feature\Student;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
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
            'name' => 'Nusrat Jahan',
            'dob' => '2014-03-15',
            'gender' => 'female',
            'admission_date' => '2026-01-10',
            ...$overrides,
        ];
    }

    private function student(string $name, array $extra = []): Student
    {
        static $n = 0;
        $n++;

        return Student::create([
            'name' => $name,
            'dob' => '2014-01-01',
            'gender' => 'male',
            'admission_date' => '2026-01-01',
            'student_id' => (string) (10_000_000 + $n),
            'admission_no' => sprintf('2026-9%03d', $n),
            ...$extra,
        ]);
    }

    public function test_creating_a_student_generates_both_identifiers(): void
    {
        $admin = $this->admin();

        $first = $this->asUser($admin)->postJson('/api/students', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.admission_no', '2026-0001')
            ->assertJsonPath('data.admission_date', '2026-01-10')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.photo_url', null)
            ->json('data');

        $this->assertMatchesRegularExpression('/^[1-9]\d{7}$/', $first['student_id']);

        // The counter runs per admission year.
        $this->asUser($admin)->postJson('/api/students', $this->payload(['name' => 'Second']))
            ->assertJsonPath('data.admission_no', '2026-0002');
        $this->asUser($admin)->postJson('/api/students', $this->payload(['name' => 'Last year', 'admission_date' => '2025-12-20']))
            ->assertJsonPath('data.admission_no', '2025-0001');
    }

    public function test_admission_date_defaults_to_today(): void
    {
        $this->travelTo('2027-02-03');

        $this->asUser($this->admin())
            ->postJson('/api/students', $this->payload(['admission_date' => null]))
            ->assertCreated()
            ->assertJsonPath('data.admission_date', '2027-02-03')
            ->assertJsonPath('data.admission_no', '2027-0001');
    }

    public function test_identifiers_can_be_typed_in_and_generation_skips_taken_numbers(): void
    {
        $admin = $this->admin();

        // E.g. migrating an existing student, whose number collides with the next generated one.
        $this->asUser($admin)
            ->postJson('/api/students', $this->payload(['student_id' => 'S-1001', 'admission_no' => '2026-0001']))
            ->assertCreated()
            ->assertJsonPath('data.student_id', 'S-1001');

        $this->asUser($admin)->postJson('/api/students', $this->payload(['name' => 'Next']))
            ->assertCreated()
            ->assertJsonPath('data.admission_no', '2026-0002');
    }

    public function test_identifiers_stay_unique_even_against_archived_students(): void
    {
        $archived = $this->student('Old Student', ['student_id' => 'S-77']);
        $archived->delete();

        $this->asUser($this->admin())
            ->postJson('/api/students', $this->payload(['student_id' => 'S-77', 'admission_no' => 'bad id!']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id', 'admission_no'])
            ->assertJsonPath('errors.admission_no.0', 'Use letters, digits, dashes or slashes.');
    }

    public function test_required_fields_and_enums_are_validated(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/students', [
                'dob' => now()->addDay()->toDateString(),
                'gender' => 'unknown',
                'blood_group' => 'C+',
                'phone' => 'n/a',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'dob', 'gender', 'blood_group', 'phone'])
            ->assertJsonPath('errors.dob.0', 'The date of birth must be in the past.');
    }

    public function test_list_is_paginated_searchable_and_shows_the_primary_guardian(): void
    {
        $karim = Guardian::create(['name' => 'Abdul Karim', 'phone' => '01712-345678']);
        $this->student('Zubair Hossain', ['phone' => '01999-000111'])
            ->guardians()->attach($karim->id, ['relationship' => 'father', 'is_primary' => true]);
        $this->student('Anika Rahman', ['student_id' => '55667788']);
        $this->student('Mehedi Hasan', ['admission_no' => '2024-0042']);
        $admin = $this->admin();

        $this->asUser($admin)
            ->getJson('/api/students')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Anika Rahman')
            ->assertJsonPath('data.2.name', 'Zubair Hossain')
            ->assertJsonPath('data.2.primary_guardian.name', 'Abdul Karim')
            ->assertJsonPath('data.2.primary_guardian.relationship', 'father')
            ->assertJsonPath('data.0.primary_guardian', null)
            ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        foreach (['anika' => 'Anika Rahman', '5566' => 'Anika Rahman', '2024-0042' => 'Mehedi Hasan', '01999' => 'Zubair Hossain'] as $search => $name) {
            $this->asUser($admin)
                ->getJson('/api/students?search='.urlencode($search))
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', $name);
        }
    }

    public function test_a_student_can_be_updated(): void
    {
        $student = $this->student('Rafi Ahmed');

        $this->asUser($this->admin())
            ->putJson("/api/students/{$student->id}", [
                ...$this->payload(['name' => 'Rafiul Ahmed', 'blood_group' => 'O+', 'father_name' => 'Kamal Ahmed']),
                'student_id' => $student->student_id,
                'admission_no' => $student->admission_no,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rafiul Ahmed')
            ->assertJsonPath('data.blood_group', 'O+')
            ->assertJsonPath('data.father_name', 'Kamal Ahmed');
    }

    public function test_archiving_hides_the_student_but_keeps_the_record_readable_and_restorable(): void
    {
        $student = $this->student('Tanvir Islam');
        $this->student('Still Here');
        $admin = $this->admin();

        $this->asUser($admin)->deleteJson("/api/students/{$student->id}")->assertNoContent();

        $this->assertSoftDeleted($student);
        $this->asUser($admin)->getJson('/api/students')->assertJsonCount(1, 'data');
        $this->asUser($admin)->getJson('/api/students?archived=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tanvir Islam');

        // Readable but read-only while archived.
        $this->asUser($admin)->getJson("/api/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.archived_at', fn ($value) => $value !== null);
        $this->asUser($admin)->putJson("/api/students/{$student->id}", $this->payload())->assertNotFound();

        $this->asUser($admin)->postJson("/api/students/{$student->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.archived_at', null);
        $this->asUser($admin)->postJson("/api/students/{$student->id}/restore")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This student is not archived.');
    }

    public function test_dashboard_counts_students_for_staff(): void
    {
        $this->travelTo('2026-10-15');
        $this->student('Old Boy', ['admission_date' => '2025-01-01']);
        $this->student('New Girl', ['gender' => 'female', 'admission_date' => '2026-10-02']);
        $this->student('Gone', ['admission_date' => '2026-10-03'])->delete();

        $stats = collect($this->asUser($this->admin())->getJson('/api/dashboard')->json('data.widgets'))
            ->firstWhere('key', 'student_stats');

        $this->assertSame('ready', $stats['status']);
        $this->assertSame(2, $stats['data']['total']);
        $this->assertSame(1, $stats['data']['admitted_this_month']);
        $this->assertSame(1, $stats['data']['archived']);
        $this->assertSame(
            ['male' => 1, 'female' => 1, 'other' => 0],
            array_column($stats['data']['by_gender'], 'count', 'gender'),
        );
    }

    public function test_permissions_gate_each_action(): void
    {
        $student = $this->student('Sadia Akter');

        // Teacher: view only (scoping to assigned classes arrives with Modules 12–13).
        $teacher = $this->userWithRole('Teacher');
        $this->asUser($teacher)->getJson('/api/students')->assertOk();
        $this->asUser($teacher)->postJson('/api/students', $this->payload())->assertForbidden();

        // Receptionist: may admit (create) but not edit or archive.
        $receptionist = $this->userWithRole('Receptionist');
        $this->asUser($receptionist)->postJson('/api/students', $this->payload())->assertCreated();
        $this->asUser($receptionist)->putJson("/api/students/{$student->id}", $this->payload())->assertForbidden();
        $this->asUser($receptionist)->deleteJson("/api/students/{$student->id}")->assertForbidden();

        // Parents never see the student register; their children come via the dashboard.
        $this->asUser($this->userWithRole('Parent'))->getJson('/api/students')->assertForbidden();

        $this->assertNotSoftDeleted($student);
    }
}
