<?php

namespace App\Policies;

use App\Models\DocumentTransaction;
use App\Models\User;

class DocumentTransactionPolicy
{
    public function view(User $user, DocumentTransaction $transaction): bool
    {
        return $user->can('view', $transaction->document);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DocumentTransaction $transaction): bool
    {
        return false;
    }

    public function delete(User $user, DocumentTransaction $transaction): bool
    {
        return false;
    }
}
