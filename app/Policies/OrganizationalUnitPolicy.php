<?php

namespace App\Policies;

use App\Models\OrganizationalUnit;
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
        return false;
    }
}
