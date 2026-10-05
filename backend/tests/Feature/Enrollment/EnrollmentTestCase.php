<?php

namespace Tests\Feature\Enrollment;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shared fixtures: years 2026 (current) and 2027, Class 6 (A, B) and Class 7 (A). */
abstract class EnrollmentTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected AcademicYear $y2026;

    protected AcademicYear $y2027;

    protected SchoolClass $six;

    protected SchoolClass $seven;

    protected Section $sixA;

    protected Section $sixB;

    protected Section $sevenA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = $this->userWithRole('Admin');

        $this->y2026 = AcademicYear::create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true]);
        $this->y2027 = AcademicYear::create(['name' => '2027', 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_current' => false]);
        AcademicYear::forgetCurrentCache();

        $this->six = SchoolClass::create(['name' => 'Class 6', 'numeric_level' => 6]);
        $this->seven = SchoolClass::create(['name' => 'Class 7', 'numeric_level' => 7]);
        $this->sixA = $this->six->sections()->create(['name' => 'A', 'capacity' => 3]);
        $this->sixB = $this->six->sections()->create(['name' => 'B', 'capacity' => 3]);
        $this->sevenA = $this->seven->sections()->create(['name' => 'A', 'capacity' => 3]);
    }

    protected function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function student(string $name): Student
    {
        static $n = 0;
        $n++;

        return Student::create([
            'name' => $name, 'dob' => '2014-01-01', 'gender' => 'female', 'admission_date' => '2026-01-01',
            'student_id' => (string) (20_000_000 + $n), 'admission_no' => sprintf('2026-8%03d', $n),
        ]);
    }

    protected function enroll(Student $student, Section $section, ?AcademicYear $year = null, ?int $roll = null)
    {
        return $this->asUser($this->admin)->postJson('/api/enrollments', array_filter([
            'student_id' => $student->id,
            'academic_year_id' => ($year ?? $this->y2026)->id,
            'section_id' => $section->id,
            'roll' => $roll,
        ]));
    }
}
