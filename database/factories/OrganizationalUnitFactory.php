<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationalUnit>
 */
class OrganizationalUnitFactory extends Factory
{
    protected $model = OrganizationalUnit::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'unit_type' => OrganizationalUnitType::School,
            'unit_code' => fake()->unique()->bothify('SCH-####'),
            'unit_name' => fake()->company().' School',
            'short_name' => null,
            'address' => fake()->optional()->address(),
            'contact_person' => fake()->optional()->name(),
            'contact_number' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'status' => OperationalStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationalStatus::Inactive,
        ]);
    }
}
