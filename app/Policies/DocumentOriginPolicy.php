<?php

namespace App\Policies;

use App\Enums\OperationalStatus;
use App\Models\DocumentOrigin;
use App\Models\User;

class DocumentOriginPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function view(User $user, DocumentOrigin $documentOrigin): bool
    {
        return $user->isLevelTwo();
    }

    public function viewActive(User $user): bool
    {
        return $user->status === OperationalStatus::Active;
    }

    public function create(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function update(User $user, DocumentOrigin $documentOrigin): bool
    {
        return $user->isLevelTwo();
    }

    public function delete(User $user, DocumentOrigin $documentOrigin): bool
    {
        return false;
    }
}
