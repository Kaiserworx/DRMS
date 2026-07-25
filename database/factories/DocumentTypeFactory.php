<?php

namespace Database\Factories;

use App\Enums\OperationalStatus;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'default_workflow' => null,
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
