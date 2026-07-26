<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReceivingBox>
 */
class ReceivingBoxFactory extends Factory
{
    protected $model = ReceivingBox::class;

    public function definition(): array
    {
        return [
            'organizational_unit_id' => OrganizationalUnit::factory(),
            'qr_token' => Str::random(64),
            'box_location' => fake()->optional()->words(3, true),
            'status' => OperationalStatus::Active,
            'last_qr_generated_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationalStatus::Inactive,
        ]);
    }
}
