<?php

namespace Database\Seeders;

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
     * @var list<array{0: string, 1: string, 2: string}>  [email, name, role]
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
    }
}
