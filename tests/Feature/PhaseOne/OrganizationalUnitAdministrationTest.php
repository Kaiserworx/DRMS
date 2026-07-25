<?php

namespace Tests\Feature\PhaseOne;

use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Filament\Resources\OrganizationalUnits\Pages\CreateOrganizationalUnit;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationalUnitAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        $this->actingAs(User::factory()->levelTwo()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_level_two_can_create_an_organizational_unit_through_the_admin_form(): void
    {
        Livewire::test(CreateOrganizationalUnit::class)
            ->fillForm([
                'unit_type' => OrganizationalUnitType::School->value,
                'unit_code' => 'SCH-100',
                'unit_name' => 'North Pilot School',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('organizational_units', [
            'unit_code' => 'SCH-100',
            'unit_name' => 'North Pilot School',
        ]);
    }

    public function test_organizational_unit_admin_form_rejects_a_duplicate_unit_code(): void
    {
        OrganizationalUnit::factory()->create([
            'unit_code' => 'SCH-100',
        ]);

        Livewire::test(CreateOrganizationalUnit::class)
            ->fillForm([
                'unit_type' => OrganizationalUnitType::School->value,
                'unit_code' => 'SCH-100',
                'unit_name' => 'Duplicate Code School',
                'status' => OperationalStatus::Active->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['unit_code' => 'unique']);
    }
}
