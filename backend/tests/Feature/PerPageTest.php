<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_endpoints_clamp_the_requested_page_size(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->asUser($admin);

        $cases = ['' => 25, '?per_page=10' => 10, '?per_page=0' => 25, '?per_page=-1' => 25, '?per_page=abc' => 25, '?per_page=100000' => 100];

        foreach ($cases as $query => $expected) {
            $this->getJson("/api/classes{$query}")
                ->assertOk()
                ->assertJsonPath('meta.per_page', $expected);
        }
    }
}
