<?php

namespace Tests\Feature\Settings;

use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    /** A user who may read settings but not change them. */
    private function viewer(): User
    {
        $role = Role::firstOrCreate(['name' => 'Settings Viewer', 'guard_name' => 'api']);
        $role->syncPermissions(['settings.view']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_view_settings_with_defaults(): void
    {
        $this->asUser($this->admin())
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.name', 'My School')
            ->assertJsonPath('data.currency', 'BDT')
            ->assertJsonPath('data.timezone', 'Asia/Dhaka');
    }

    public function test_viewing_requires_settings_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Student');

        $this->asUser($user)->getJson('/api/settings')->assertForbidden();
    }

    public function test_viewer_cannot_update_settings(): void
    {
        $viewer = $this->viewer();

        $this->asUser($viewer)->getJson('/api/settings')->assertOk();
        $this->asUser($viewer)
            ->putJson('/api/settings', ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_reads_are_cached_and_updates_invalidate_the_cache(): void
    {
        $admin = $this->admin();

        // Prime the cache.
        $this->asUser($admin)->getJson('/api/settings')->assertJsonPath('data.name', 'My School');

        // A write that bypasses the app is invisible while cached...
        SchoolSetting::query()->update(['name' => 'Direct DB Write']);
        $this->asUser($admin)->getJson('/api/settings')->assertJsonPath('data.name', 'My School');

        // ...and an update through the API busts it.
        $this->asUser($admin)
            ->putJson('/api/settings', ['name' => 'Greenfield High', 'established_year' => 1995])
            ->assertOk()
            ->assertJsonPath('data.name', 'Greenfield High');

        $this->asUser($admin)
            ->getJson('/api/settings')
            ->assertJsonPath('data.name', 'Greenfield High')
            ->assertJsonPath('data.established_year', 1995);
    }

    public function test_cache_hits_work_on_a_serializing_store(): void
    {
        // The array store used in tests keeps PHP objects in memory; real
        // stores (database/file/redis) serialize, and the cache refuses to
        // unserialize classes. Exercise a serializing store end-to-end.
        config(['cache.default' => 'database']);
        $admin = $this->admin();

        $this->asUser($admin)->getJson('/api/settings')->assertOk(); // miss → store
        $this->asUser($admin)
            ->getJson('/api/settings') // hit → unserialize
            ->assertOk()
            ->assertJsonPath('data.name', 'My School');

        $this->assertIsArray(Cache::get(SchoolSetting::CACHE_KEY));
    }

    public function test_secrets_are_encrypted_at_rest_and_never_returned(): void
    {
        $response = $this->asUser($this->admin())
            ->putJson('/api/settings', [
                'email_settings' => ['host' => 'smtp.school.test', 'username' => 'mailer', 'password' => 's3cret-pass'],
            ])
            ->assertOk()
            ->assertJsonPath('data.email_settings.host', 'smtp.school.test')
            ->assertJsonPath('data.email_settings.password_set', true)
            ->assertJsonMissingPath('data.email_settings.password');

        $this->assertStringNotContainsString('s3cret-pass', $response->getContent());
        $this->assertStringNotContainsString(
            's3cret-pass',
            (string) DB::table('school_settings')->value('email_settings'),
        );
    }

    public function test_omitting_a_secret_keeps_the_stored_value(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)->putJson('/api/settings', [
            'email_settings' => ['host' => 'old.host', 'password' => 'keep-me'],
        ])->assertOk();

        $this->asUser($admin)->putJson('/api/settings', [
            'email_settings' => ['host' => 'new.host', 'password' => ''],
        ])->assertOk();

        $stored = SchoolSetting::query()->first()->email_settings;
        $this->assertSame('new.host', $stored['host']);
        $this->assertSame('keep-me', $stored['password']);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->asUser($this->admin())
            ->putJson('/api/settings', ['timezone' => 'Mars/Olympus_Mons'])
            ->assertStatus(422);
    }

    public function test_logo_upload_is_stored(): void
    {
        $response = $this->asUser($this->admin())
            ->postJson('/api/settings/logo', [
                'file' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            ])
            ->assertOk();

        $this->assertNotNull($response->json('data.logo_url'));
        Storage::disk('public')->assertExists(SchoolSetting::query()->first()->logo_path);
    }

    public function test_replacing_the_logo_deletes_the_old_file(): void
    {
        $admin = $this->admin();

        $this->asUser($admin)->postJson('/api/settings/logo', [
            'file' => UploadedFile::fake()->create('first.png', 50, 'image/png'),
        ])->assertOk();
        $first = SchoolSetting::query()->first()->logo_path;

        $this->asUser($admin)->postJson('/api/settings/logo', [
            'file' => UploadedFile::fake()->create('second.png', 50, 'image/png'),
        ])->assertOk();

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists(SchoolSetting::query()->first()->logo_path);
    }

    public function test_non_image_logo_is_rejected(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/settings/logo', [
                'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
            ])
            ->assertStatus(422);
    }

    public function test_oversized_logo_is_rejected(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/settings/logo', [
                'file' => UploadedFile::fake()->create('big.png', 3000, 'image/png'),
            ])
            ->assertStatus(422);
    }

    public function test_favicon_upload_is_stored(): void
    {
        $this->asUser($this->admin())
            ->postJson('/api/settings/favicon', [
                'file' => UploadedFile::fake()->create('favicon.png', 20, 'image/png'),
            ])
            ->assertOk();

        Storage::disk('public')->assertExists(SchoolSetting::query()->first()->favicon_path);
    }
}
