<?php

namespace App\Services;

use App\Enums\DeploymentProfile;
use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use Illuminate\Validation\ValidationException;

class OrganizationalUnitHierarchy
{
    /**
     * @return array<OrganizationalUnitType>
     */
    public function allowedTypes(DeploymentProfile $profile): array
    {
        return match ($profile) {
            DeploymentProfile::District => [
                OrganizationalUnitType::School,
            ],
            DeploymentProfile::Division => [
                OrganizationalUnitType::District,
                OrganizationalUnitType::School,
                OrganizationalUnitType::Section,
                OrganizationalUnitType::FunctionalUnit,
            ],
            DeploymentProfile::Regional => [
                OrganizationalUnitType::Division,
                OrganizationalUnitType::Section,
                OrganizationalUnitType::FunctionalUnit,
            ],
        };
    }

    public function validate(OrganizationalUnit $unit, ?DeploymentProfile $profile = null): void
    {
        $profile ??= DeploymentSetting::current()?->deployment_profile ?? DeploymentProfile::District;
        $unitType = $this->unitType($unit);

        if (! in_array($unitType, $this->allowedTypes($profile), true)) {
            throw ValidationException::withMessages([
                'unit_type' => "The {$unitType->label()} unit type is not valid for the {$profile->label()} deployment profile.",
            ]);
        }

        if (blank($unit->parent_id)) {
            return;
        }

        if ($unit->exists && ((int) $unit->parent_id === (int) $unit->getKey())) {
            throw ValidationException::withMessages([
                'parent_id' => 'An organizational unit cannot be its own parent.',
            ]);
        }

        $parent = OrganizationalUnit::query()->find($unit->parent_id);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent organizational unit does not exist.',
            ]);
        }

        if ($parent->status !== OperationalStatus::Active) {
            throw ValidationException::withMessages([
                'parent_id' => 'The selected parent organizational unit must be active.',
            ]);
        }

        if (! $this->isValidParentChildPair($profile, $parent->unit_type, $unitType)) {
            throw ValidationException::withMessages([
                'parent_id' => "A {$parent->unit_type->label()} cannot be the parent of a {$unitType->label()} in the {$profile->label()} deployment profile.",
            ]);
        }

        $ancestor = $parent;

        while ($ancestor) {
            if ($unit->exists && ((int) $ancestor->getKey() === (int) $unit->getKey())) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent would create an organizational hierarchy cycle.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }
    }

    public function validateExistingUnitsForProfile(DeploymentProfile $profile): void
    {
        OrganizationalUnit::query()
            ->with('parent.parent.parent')
            ->each(fn (OrganizationalUnit $unit) => $this->validate($unit, $profile));
    }

    private function isValidParentChildPair(
        DeploymentProfile $profile,
        OrganizationalUnitType $parentType,
        OrganizationalUnitType $childType,
    ): bool {
        return match ($profile) {
            DeploymentProfile::District,
            DeploymentProfile::Regional => false,
            DeploymentProfile::Division => $parentType === OrganizationalUnitType::District
                && $childType === OrganizationalUnitType::School,
        };
    }

    private function unitType(OrganizationalUnit $unit): OrganizationalUnitType
    {
        $unitType = $unit->unit_type;

        if ($unitType instanceof OrganizationalUnitType) {
            return $unitType;
        }

        $unitType = OrganizationalUnitType::tryFrom((string) $unitType);

        if (! $unitType) {
            throw ValidationException::withMessages([
                'unit_type' => 'The selected organizational unit type is invalid.',
            ]);
        }

        return $unitType;
    }
}
