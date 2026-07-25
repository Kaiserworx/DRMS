<?php

namespace Tests\Unit\PhaseTwo;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\OriginType;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Enums\RecipientStatus;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class DomainEnumerationTest extends TestCase
{
    public function test_phase_two_domain_values_are_centralized_and_unique(): void
    {
        $enums = [
            UserRole::class,
            DocumentStatus::class,
            RecipientStatus::class,
            PhysicalLocation::class,
            Priority::class,
            OriginType::class,
            OperationalStatus::class,
        ];

        foreach ($enums as $enum) {
            $values = array_column($enum::cases(), 'value');

            $this->assertNotEmpty($values);
            $this->assertSame($values, array_values(array_unique($values)));
            $this->assertCount(count($values), $enum::options());
        }
    }

    public function test_document_statuses_cover_the_approved_future_workflow_terms(): void
    {
        $this->assertSame([
            'draft',
            'submitted',
            'received_at_managing_office',
            'forwarded_to_upstream_office',
            'received_at_upstream_office',
            'returned_from_upstream_office',
            'for_distribution',
            'ready_for_pickup',
            'partially_claimed',
            'completed',
            'cancelled',
        ], array_column(DocumentStatus::cases(), 'value'));
    }

    public function test_priority_and_origin_type_values_remain_the_minimum_phase_two_set(): void
    {
        $this->assertSame(
            ['normal', 'urgent'],
            array_column(Priority::cases(), 'value'),
        );

        $this->assertSame(
            ['organizational_unit', 'managing_office', 'upstream_office', 'external'],
            array_column(OriginType::cases(), 'value'),
        );
    }
}
