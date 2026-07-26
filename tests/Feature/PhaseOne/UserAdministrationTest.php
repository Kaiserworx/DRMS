<?php

namespace Tests\Feature\PhaseOne;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Pages\ManageDeploymentSettings;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Widgets\OperationalOverview;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected DeploymentSetting $settings;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = DeploymentSetting::factory()->create();
        $this->administrator = User::factory()->levelTwo()->create();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_one_requires_an_active_organizational_unit(): void
    {
        $this->expectException(ValidationException::class);

        User::factory()->create([
            'organizational_unit_id' => null,
            'role' => UserRole::LevelOne,
        ]);
    }

    public function test_level_one_cannot_be_assigned_to_an_inactive_unit(): void
    {
        $unit = OrganizationalUnit::factory()->inactive()->create();

        $this->expectException(ValidationException::class);

        User::factory()->levelOne($unit)->create();
    }

    public function test_level_two_may_exist_without_an_organizational_unit(): void
    {
        $user = User::factory()->levelTwo()->create();

        $this->assertNull($user->organizational_unit_id);
    }

    public function test_level_two_can_create_a_level_one_user_through_the_admin_form(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $this->actingAs($this->administrator);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'full_name' => 'New Unit Encoder',
                'position' => 'Records Encoder',
                'email' => 'new.encoder@example.test',
                'username' => 'new.encoder',
                'password' => 'StrongPass!1234',
                'role' => UserRole::LevelOne->value,
                'organizational_unit_id' => $unit->getKey(),
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('users', [
            'email' => 'new.encoder@example.test',
            'role' => UserRole::LevelOne->value,
            'organizational_unit_id' => $unit->getKey(),
        ]);
    }

    public function test_user_admin_form_rejects_a_weak_password(): void
    {
        $this->actingAs($this->administrator);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'full_name' => 'Weak Password User',
                'email' => 'weak@example.test',
                'username' => 'weak.user',
                'password' => 'weak',
                'role' => UserRole::LevelTwo->value,
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_user_admin_form_rejects_duplicate_login_identifiers(): void
    {
        User::factory()->levelTwo()->create([
            'email' => 'existing@example.test',
            'username' => 'existing.user',
        ]);
        $this->actingAs($this->administrator);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'full_name' => 'Duplicate Login User',
                'email' => 'existing@example.test',
                'username' => 'existing.user',
                'password' => 'StrongPass!1234',
                'role' => UserRole::LevelTwo->value,
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'email' => 'unique',
                'username' => 'unique',
            ]);
    }

    public function test_profile_editing_cannot_change_role_or_unit(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create();
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('data.role', UserRole::LevelTwo->value)
            ->set('data.organizational_unit_id', $otherUnit->getKey())
            ->fillForm([
                'full_name' => 'Updated Encoder Name',
                'position' => 'Senior Records Encoder',
                'email' => $user->email,
                'username' => $user->username,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $user->refresh();

        $this->assertSame(UserRole::LevelOne, $user->role);
        $this->assertTrue($user->organizationalUnit->is($unit));
        $this->assertSame('Updated Encoder Name', $user->full_name);
    }

    public function test_deployment_labels_update_without_domain_code_changes(): void
    {
        $this->actingAs($this->administrator);
        $data = $this->settings->attributesToArray();
        $data['level_1_unit_label'] = 'Campus';
        $data['receiving_box_label'] = 'Campus Box';

        Livewire::test(ManageDeploymentSettings::class)
            ->fillForm($data)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->settings->refresh();

        $this->assertSame('Campus', $this->settings->level_1_unit_label);

        $unit = OrganizationalUnit::factory()->create();
        $encoder = User::factory()->levelOne($unit)->create();
        $this->actingAs($encoder);

        Livewire::test(OperationalOverview::class)
            ->assertSee('Campus Encoder')
            ->assertSee($unit->unit_name);
    }
}
