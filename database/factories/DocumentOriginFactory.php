<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Enums\OriginType;
use App\Models\DocumentOrigin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentOrigin>
 */
class DocumentOriginFactory extends Factory
{
    protected $model = DocumentOrigin::class;

    public function definition(): array
    {
        return [
            'origin_type' => fake()->randomElement(OriginType::cases()),
            'origin_name' => fake()->unique()->company(),
            'office_code' => fake()->optional()->bothify('OFF-###'),
            'address' => fake()->optional()->address(),
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
