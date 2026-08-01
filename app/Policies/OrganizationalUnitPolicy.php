<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;

class OrganizationalUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function view(User $user, OrganizationalUnit $organizationalUnit): bool
    {
        return $user->isLevelTwo();
    }

    public function create(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function update(User $user, OrganizationalUnit $organizationalUnit): bool
    {
        return $user->isLevelTwo();
    }

    public function delete(User $user, OrganizationalUnit $organizationalUnit): bool
    {
        if (! $user->isLevelTwo()
            || $organizationalUnit->children()->exists()
            || $organizationalUnit->users()->exists()) {
            return false;
        }

        return ! Document::query()
            ->where('submitting_unit_id', $organizationalUnit->getKey())
            ->exists()
            && ! DocumentRecipient::query()
                ->where('recipient_unit_id', $organizationalUnit->getKey())
                ->exists()
            && ! ReceivingBox::query()
                ->where('organizational_unit_id', $organizationalUnit->getKey())
                ->exists();
    }
}
