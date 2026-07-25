<?php

namespace Tests\Feature\PhaseOne;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_active_level_two_user_can_authenticate_with_email(): void
    {
        $user = User::factory()->levelTwo()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('StrongPass!1234'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'admin@example.test',
                'password' => 'StrongPass!1234',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_active_level_one_user_can_authenticate_with_username(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create([
            'username' => 'unit.encoder',
            'password' => Hash::make('StrongPass!1234'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'UNIT.ENCODER',
                'password' => 'StrongPass!1234',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        User::factory()->levelTwo()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('StrongPass!1234'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'inactive@example.test',
                'password' => 'StrongPass!1234',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::LevelTwo,
            'status' => OperationalStatus::Active,
        ]);

        $this->actingAs($user)
            ->post(route('filament.admin.auth.logout'))
            ->assertRedirect('/admin/login');

        $this->assertGuest();
    }
}
