<?php

namespace Database\Seeders;

use App\Enums\OperationalStatus;
use App\Models\DocumentType;
use App\Services\ReferenceNameNormalizer;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const EXAMPLES = [
        'Appointment',
        'Book Delivery',
        'Leave Application',
        'Liquidation',
        'Memorandum',
        'Payroll',
        'Purchase Request',
        'Service Record',
        'Transfer Endorsement',
        'Travel Order',
        'Others',
    ];

    public function run(): void
    {
        foreach (self::EXAMPLES as $name) {
            DocumentType::query()->firstOrCreate(
                ['normalized_name' => ReferenceNameNormalizer::key($name)],
                [
                    'name' => $name,
                    'description' => null,
                    'default_workflow' => null,
                    'status' => OperationalStatus::Active,
                ],
            );
        }
    }
}
