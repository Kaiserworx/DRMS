<?php

namespace Tests\Feature\PhaseOne;

use App\Enums\DeploymentProfile;
use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Filament\Resources\OrganizationalUnits\OrganizationalUnitResource;
use App\Filament\Resources\OrganizationalUnits\Pages\CreateOrganizationalUnit;
use App\Filament\Resources\OrganizationalUnits\Pages\EditOrganizationalUnit;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationalUnitAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        DeploymentSetting::factory()->create();
        $this->administrator = User::factory()->levelTwo()->create();
        $this->actingAs($this->administrator);
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
            ->assertNotified()
            ->assertRedirect(OrganizationalUnitResource::getUrl('index'));

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

    public function test_level_two_can_delete_an_unreferenced_organizational_unit(): void
    {
        $unit = OrganizationalUnit::factory()->create();

        Livewire::test(EditOrganizationalUnit::class, ['record' => $unit->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect(OrganizationalUnitResource::getUrl('index'));

        $this->assertSoftDeleted($unit);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'organizational_unit.deleted',
            'actor_id' => $this->administrator->id,
            'auditable_type' => $unit->getMorphClass(),
            'auditable_id' => $unit->id,
        ]);
    }

    public function test_referenced_organizational_units_cannot_be_deleted(): void
    {
        DeploymentSetting::query()->firstOrFail()->update([
            'deployment_profile' => DeploymentProfile::Division,
        ]);
        $withChild = OrganizationalUnit::factory()->create([
            'unit_type' => OrganizationalUnitType::District,
        ]);
        OrganizationalUnit::factory()->create([
            'parent_id' => $withChild->id,
            'unit_type' => OrganizationalUnitType::School,
        ]);

        $withUser = OrganizationalUnit::factory()->create();
        User::factory()->levelOne($withUser)->create();

        $withDocument = OrganizationalUnit::factory()->create();
        Document::factory()->create(['submitting_unit_id' => $withDocument->id]);

        $withRecipient = OrganizationalUnit::factory()->create();
        DocumentRecipient::factory()->create(['recipient_unit_id' => $withRecipient->id]);

        $withBox = OrganizationalUnit::factory()->create();
        ReceivingBox::factory()->create(['organizational_unit_id' => $withBox->id]);

        foreach ([$withChild, $withUser, $withDocument, $withRecipient, $withBox] as $unit) {
            $this->assertFalse($this->administrator->can('delete', $unit));
            $this->assertFalse(User::factory()->levelOne(OrganizationalUnit::factory()->create())->create()->can('delete', $unit));
        }
    }
}
