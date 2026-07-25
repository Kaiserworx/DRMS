<?php

namespace App\Policies;

use App\Models\DocumentRecipient;
use App\Models\User;

class DocumentRecipientPolicy
{
    public function view(User $user, DocumentRecipient $recipient): bool
    {
        return $user->isLevelTwo()
            || $recipient->recipient_unit_id === $user->organizational_unit_id;
    }

    public function create(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function delete(User $user, DocumentRecipient $recipient): bool
    {
        return $user->isLevelTwo() && ! $recipient->hasDownstreamActivity();
    }
}
