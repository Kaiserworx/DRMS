<?php

namespace Tests\Feature\PhaseOne;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_only_the_active_level_two_administrator(): void
    {
        config(['drms.demo_password' => 'LocalOnlyStrong!1234']);

        $this->seed(AdminUserSeeder::class);

        $user = User::query()->sole();

        $this->assertSame('admin', $user->username);
        $this->assertSame('admin@drms.local', $user->email);
        $this->assertSame(UserRole::LevelTwo, $user->role);
        $this->assertSame(OperationalStatus::Active, $user->status);
        $this->assertNull($user->organizational_unit_id);
        $this->assertTrue(Hash::check('LocalOnlyStrong!1234', $user->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizational_units', 0);
    }

    public function test_local_refresh_command_refuses_to_run_in_the_testing_environment(): void
    {
        $this->artisan('drms:refresh-local', ['--force' => true])
            ->expectsOutput('This command may run only in the local environment.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
