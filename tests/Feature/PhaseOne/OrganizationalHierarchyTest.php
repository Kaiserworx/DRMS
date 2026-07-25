<?php

namespace Tests\Feature\PhaseOne;

use App\Enums\DeploymentProfile;
use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Services\OrganizationalUnitHierarchy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizationalHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_district_profile_accepts_school_units_and_rejects_other_types(): void
    {
        DeploymentSetting::factory()->create([
            'deployment_profile' => DeploymentProfile::District,
        ]);

        $school = OrganizationalUnit::factory()->create();

        $this->assertSame(OrganizationalUnitType::School, $school->unit_type);

        $this->expectException(ValidationException::class);

        OrganizationalUnit::factory()->create([
            'unit_type' => OrganizationalUnitType::District,
        ]);
    }

    public function test_division_profile_accepts_a_school_beneath_an_active_district(): void
    {
        DeploymentSetting::factory()->create([
            'deployment_profile' => DeploymentProfile::Division,
            'managing_office_level' => DeploymentProfile::Division,
        ]);

        $district = OrganizationalUnit::factory()->create([
            'unit_type' => OrganizationalUnitType::District,
        ]);

        $school = OrganizationalUnit::factory()->create([
            'parent_id' => $district,
            'unit_type' => OrganizationalUnitType::School,
        ]);

        $this->assertTrue($school->parent->is($district));
    }

    public function test_invalid_parent_child_combination_is_rejected(): void
    {
        DeploymentSetting::factory()->create([
            'deployment_profile' => DeploymentProfile::Division,
            'managing_office_level' => DeploymentProfile::Division,
        ]);

        $district = OrganizationalUnit::factory()->create([
            'unit_type' => OrganizationalUnitType::District,
        ]);

        $this->expectException(ValidationException::class);

        OrganizationalUnit::factory()->create([
            'parent_id' => $district,
            'unit_type' => OrganizationalUnitType::FunctionalUnit,
        ]);
    }

    public function test_inactive_parent_is_rejected(): void
    {
        DeploymentSetting::factory()->create([
            'deployment_profile' => DeploymentProfile::Division,
            'managing_office_level' => DeploymentProfile::Division,
        ]);

        $district = OrganizationalUnit::factory()->create([
            'unit_type' => OrganizationalUnitType::District,
            'status' => OperationalStatus::Inactive,
        ]);

        $this->expectException(ValidationException::class);

        OrganizationalUnit::factory()->create([
            'parent_id' => $district,
            'unit_type' => OrganizationalUnitType::School,
        ]);
    }

    public function test_self_parenting_and_hierarchy_cycles_are_rejected(): void
    {
        DeploymentSetting::factory()->create([
            'deployment_profile' => DeploymentProfile::Division,
            'managing_office_level' => DeploymentProfile::Division,
        ]);

        $district = OrganizationalUnit::factory()->create([
            'unit_type' => OrganizationalUnitType::District,
        ]);
        $school = OrganizationalUnit::factory()->create([
            'parent_id' => $district,
            'unit_type' => OrganizationalUnitType::School,
        ]);

        $this->expectException(ValidationException::class);

        $district->update([
            'parent_id' => $school->getKey(),
        ]);
    }

    public function test_profile_change_is_rejected_when_existing_units_are_incompatible(): void
    {
        DeploymentSetting::factory()->create([
            'deployment_profile' => DeploymentProfile::District,
        ]);
        OrganizationalUnit::factory()->create();

        $this->expectException(ValidationException::class);

        app(OrganizationalUnitHierarchy::class)
            ->validateExistingUnitsForProfile(DeploymentProfile::Regional);
    }
}
