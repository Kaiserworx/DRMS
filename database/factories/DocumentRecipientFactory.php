<?php

namespace Database\Factories;

use App\Enums\RecipientStatus;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRecipient>
 */
class DocumentRecipientFactory extends Factory
{
    protected $model = DocumentRecipient::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'recipient_unit_id' => OrganizationalUnit::factory(),
            'receiving_box_id' => null,
            'recipient_status' => RecipientStatus::Assigned,
            'date_assigned' => now(),
            'date_placed' => null,
            'date_claimed' => null,
            'claimed_by_user_id' => null,
            'received_by_name' => null,
            'received_by_position' => null,
            'remarks' => null,
        ];
    }
}
