<?php

namespace Database\Seeders;

use App\Models\Guardian;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Demo accounts, one per dashboard audience. Known passwords, so they are
     * seeded in the local environment ONLY. Password for all: "password".
     *
     * @var list<array{0: string, 1: string, 2: string}> [email, name, role]
     */
    private const DEMO_USERS = [
        ['admin@school.test', 'School Admin', 'Admin'],
        ['teacher@school.test', 'Demo Teacher', 'Teacher'],
        ['parent@school.test', 'Demo Parent', 'Parent'],
        ['student@school.test', 'Demo Student', 'Student'],
        ['accountant@school.test', 'Demo Accountant', 'Accountant'],
    ];

    /**
     * Roles/permissions always; demo accounts only locally. Idempotent.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        foreach (self::DEMO_USERS as [$email, $name, $role]) {
            User::query()
                ->updateOrCreate(
                    ['email' => $email],
                    ['name' => $name, 'password' => 'password', 'is_active' => true],
                )
                ->syncRoles([$role]);
        }

        // The demo parent login belongs to a guardian record, as real ones do.
        $guardian = Guardian::query()->firstOrCreate(
            ['phone' => '01700-000000', 'name' => 'Demo Parent'],
            ['email' => 'parent@school.test'],
        );
        User::query()->where('email', 'parent@school.test')->update([
            'personable_type' => $guardian->getMorphClass(),
            'personable_id' => $guardian->getKey(),
        ]);

        // Likewise the demo teacher login belongs to a teacher record.
        $teacher = Teacher::query()->firstOrCreate(
            ['employee_id' => 'T-DEMO'],
            ['name' => 'Demo Teacher', 'phone' => '01700-000001', 'joining_date' => '2020-01-01', 'email' => 'teacher@school.test'],
        );
        User::query()->where('email', 'teacher@school.test')->update([
            'personable_type' => $teacher->getMorphClass(),
            'personable_id' => $teacher->getKey(),
        ]);
    }
}
