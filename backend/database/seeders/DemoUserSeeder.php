<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One demo login per role, so every dashboard and permission set can be
 * tried. Known password ("password" for all), so DatabaseSeeder runs this in
 * the local environment ONLY. Idempotent: re-running updates in place.
 *
 * The parent, teacher and student logins belong to person records, as real
 * ones do (users.personable), and the demo parent is the demo student's
 * primary guardian, so the portals have something to show.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /**
     * @var list<array{0: string, 1: string, 2: string}> [email, name, role]
     */
    public const USERS = [
        ['admin@school.test', 'School Admin', 'Admin'],
        ['principal@school.test', 'Demo Principal', 'Principal'],
        ['teacher@school.test', 'Demo Teacher', 'Teacher'],
        ['accountant@school.test', 'Demo Accountant', 'Accountant'],
        ['receptionist@school.test', 'Demo Receptionist', 'Receptionist'],
        ['librarian@school.test', 'Demo Librarian', 'Librarian'],
        ['transport@school.test', 'Demo Transport Manager', 'Transport Manager'],
        ['hr@school.test', 'Demo HR Manager', 'HR Manager'],
        ['staff@school.test', 'Demo Staff', 'Staff'],
        ['parent@school.test', 'Demo Parent', 'Parent'],
        ['student@school.test', 'Demo Student', 'Student'],
    ];

    public function run(): void
    {
        foreach (self::USERS as [$email, $name, $role]) {
            User::query()
                ->updateOrCreate(
                    ['email' => $email],
                    ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true],
                )
                ->syncRoles([$role]);
        }

        $guardian = Guardian::query()->firstOrCreate(
            ['phone' => '01700-000000', 'name' => 'Demo Parent'],
            ['email' => 'parent@school.test'],
        );
        $teacher = Teacher::query()->firstOrCreate(
            ['employee_id' => 'T-DEMO'],
            ['name' => 'Demo Teacher', 'phone' => '01700-000001', 'joining_date' => '2020-01-01', 'email' => 'teacher@school.test'],
        );
        $student = Student::query()->withTrashed()->firstOrCreate(
            ['student_id' => 'DEMO-STUDENT'],
            [
                'admission_no' => 'DEMO-0001',
                'name' => 'Demo Student',
                'dob' => '2014-01-01',
                'gender' => 'female',
                'admission_date' => '2026-01-01',
            ],
        );

        $this->link('parent@school.test', $guardian);
        $this->link('teacher@school.test', $teacher);
        $this->link('student@school.test', $student);

        // The demo parent is the demo student's primary guardian.
        if (! $student->guardians()->whereKey($guardian->id)->exists()) {
            $student->guardians()->attach($guardian->id, [
                'relationship' => 'mother',
                'is_primary' => ! $student->primaryGuardian()->exists(),
            ]);
        }
    }

    /** Point a login at its person record. */
    private function link(string $email, Guardian|Teacher|Student $person): void
    {
        User::query()->where('email', $email)->update([
            'personable_type' => $person->getMorphClass(),
            'personable_id' => $person->getKey(),
        ]);
    }
}
