<?php

namespace App\Policies;

use App\Models\DeploymentSetting;
use App\Models\User;

class DeploymentSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isLevelTwo();
    }

    public function view(User $user, DeploymentSetting $deploymentSetting): bool
    {
        return $user->isLevelTwo();
    }

    public function update(User $user, DeploymentSetting $deploymentSetting): bool
    {
        return $user->isLevelTwo();
    }
}
