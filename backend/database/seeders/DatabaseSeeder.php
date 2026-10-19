<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Roles/permissions always; demo logins (known password) only in the
     * local environment. Idempotent.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
