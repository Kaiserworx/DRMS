<?php

namespace App\Services;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserAssignmentValidator
{
    public function validate(User $user): void
    {
        $role = UserRole::tryFrom((string) ($user->getAttributes()['role'] ?? ''));

        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'The selected authorization level is invalid.',
            ]);
        }

        if ($role !== UserRole::LevelOne) {
            return;
        }

        if (blank($user->organizational_unit_id)) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'An active organizational unit is required for a Level 1 user.',
            ]);
        }

        $unit = OrganizationalUnit::query()->find($user->organizational_unit_id);

        if (! $unit || $unit->status !== OperationalStatus::Active) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => 'A Level 1 user must belong to an active organizational unit.',
            ]);
        }
    }
}
