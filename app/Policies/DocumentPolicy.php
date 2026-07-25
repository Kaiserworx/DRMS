<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): bool
    {
        return $user->isLevelTwo()
            || $document->submitting_unit_id === $user->organizational_unit_id
            || $document->recipients()
                ->where('recipient_unit_id', $user->organizational_unit_id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Document $document): bool
    {
        return false;
    }

    public function delete(User $user, Document $document): bool
    {
        return false;
    }
}
