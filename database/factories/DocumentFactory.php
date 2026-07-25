<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'tracking_no' => 'DRMS-TEST-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'document_type_id' => DocumentType::factory(),
            'subject' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'origin_id' => null,
            'origin_reference_no' => null,
            'submitting_unit_id' => null,
            'created_by' => User::factory()->levelTwo(),
            'priority' => Priority::Normal,
            'current_status' => DocumentStatus::Draft,
            'current_location' => PhysicalLocation::ManagingOffice,
            'date_received' => null,
            'due_date' => null,
            'remarks' => null,
        ];
    }
}
