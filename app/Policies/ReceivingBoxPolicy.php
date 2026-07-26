<?php

namespace App\Policies;

use App\Enums\OperationalStatus;
use App\Models\ReceivingBox;
use App\Models\User;

class ReceivingBoxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function view(User $user, ReceivingBox $receivingBox): bool
    {
        return $user->isLevelTwo();
    }

    public function viewInventory(User $user, ReceivingBox $receivingBox): bool
    {
        return $user->status === OperationalStatus::Active
            && ($user->isLevelTwo()
                || $user->organizational_unit_id === $receivingBox->organizational_unit_id);
    }

    public function claimInventory(User $user, ReceivingBox $receivingBox): bool
    {
        return $user->status === OperationalStatus::Active
            && ! $user->isLevelTwo()
            && $user->organizational_unit_id === $receivingBox->organizational_unit_id;
    }

    public function create(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function update(User $user, ReceivingBox $receivingBox): bool
    {
        return $user->isLevelTwo();
    }

    public function regenerateToken(User $user, ReceivingBox $receivingBox): bool
    {
        return $user->isLevelTwo();
    }

    public function delete(User $user, ReceivingBox $receivingBox): bool
    {
        return false;
    }
}
