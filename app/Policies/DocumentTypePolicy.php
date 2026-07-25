<?php

namespace App\Policies;

use App\Enums\OperationalStatus;
use App\Models\DocumentType;
use App\Models\User;

class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function view(User $user, DocumentType $documentType): bool
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

    public function update(User $user, DocumentType $documentType): bool
    {
        return $user->isLevelTwo();
    }

    public function delete(User $user, DocumentType $documentType): bool
    {
        return false;
    }
}
