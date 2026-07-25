<?php

namespace Database\Factories;

use App\Models\DeploymentSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeploymentSetting>
 */
class DeploymentSettingFactory extends Factory
{
    protected $model = DeploymentSetting::class;

    public function definition(): array
    {
        return DeploymentSetting::defaults();
    }
}
