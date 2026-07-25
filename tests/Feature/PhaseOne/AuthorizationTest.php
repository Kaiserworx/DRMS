<?php

namespace Tests\Feature\PhaseOne;

use App\Filament\Pages\ManageDeploymentSettings;
use App\Filament\Resources\OrganizationalUnits\Pages\ListOrganizationalUnits;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_one_user_cannot_access_administration_routes(): void
    {
        $user = User::factory()->levelOne(OrganizationalUnit::factory()->create())->create();

        $this->actingAs($user);

        $this->get(ManageDeploymentSettings::getUrl())->assertForbidden();
        $this->get(ListOrganizationalUnits::getUrl())->assertForbidden();
        $this->get(ListUsers::getUrl())->assertForbidden();
    }

    public function test_level_two_user_can_access_administration_routes(): void
    {
        $user = User::factory()->levelTwo()->create();

        $this->actingAs($user);

        $this->get(ManageDeploymentSettings::getUrl())->assertOk();
        $this->get(ListOrganizationalUnits::getUrl())->assertOk();
        $this->get(ListUsers::getUrl())->assertOk();
    }

    public function test_level_one_user_cannot_create_or_edit_another_user(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $levelOne = User::factory()->levelOne($unit)->create();
        $other = User::factory()->levelOne($unit)->create();

        $this->assertFalse($levelOne->can('create', User::class));
        $this->assertFalse($levelOne->can('update', $other));
    }

    public function test_level_one_user_can_access_dashboard_and_profile(): void
    {
        $user = User::factory()->levelOne(OrganizationalUnit::factory()->create())->create();

        $this->actingAs($user);

        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')->assertOk();
    }
}
