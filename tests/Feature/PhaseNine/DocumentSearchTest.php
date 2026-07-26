<?php

namespace Tests\Feature\PhaseNine;

use App\Enums\DocumentStatus;
use App\Enums\OriginType;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_tracking_and_partial_subject_search_are_accurate(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $exact = Document::factory()->create([
            'created_by' => $actor->id,
            'tracking_no' => 'DRMS-DISTRICT-2026-004321',
            'subject' => 'Regional science equipment delivery',
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'tracking_no' => 'DRMS-DISTRICT-2026-004322',
            'subject' => 'Unrelated payroll record',
        ]);
        $search = app(DocumentSearchService::class);

        $this->assertSame(
            [$exact->id],
            $search->queryFor($actor, ['search' => 'DRMS-DISTRICT-2026-004321'])->pluck('id')->all(),
        );
        $this->assertSame(
            [$exact->id],
            $search->queryFor($actor, ['search' => 'science equipment'])->pluck('id')->all(),
        );
    }

    public function test_universal_term_covers_type_units_origin_states_remarks_and_receiver(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $submittingUnit = OrganizationalUnit::factory()->create([
            'unit_name' => 'North Harbor School',
            'unit_code' => 'NHS-001',
        ]);
        $destination = OrganizationalUnit::factory()->create([
            'unit_name' => 'South Ridge School',
            'unit_code' => 'SRS-009',
        ]);
        $type = DocumentType::factory()->create(['name' => 'Special Endorsement']);
        $origin = DocumentOrigin::factory()->create([
            'origin_type' => OriginType::External,
            'origin_name' => 'Provincial Records Center',
        ]);
        $document = Document::factory()->create([
            'created_by' => $actor->id,
            'document_type_id' => $type->id,
            'submitting_unit_id' => $submittingUnit->id,
            'origin_id' => $origin->id,
            'origin_reference_no' => 'PRC-ALPHA-88',
            'current_status' => DocumentStatus::PartiallyClaimed,
            'current_location' => PhysicalLocation::ReceivingBox,
            'priority' => Priority::Urgent,
            'description' => 'Contains solar laboratory specifications.',
            'remarks' => 'Deliver before the science fair.',
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $destination->id,
            'recipient_status' => RecipientStatus::ReceivedByRecipientUnit,
            'received_by_name' => 'Elena Mercado',
            'received_by_position' => 'School Registrar',
            'remarks' => 'Envelope seal verified.',
        ]);
        $this->transaction($document, $actor, RoutingAction::ClaimByRecipientUnit, [
            'recipient_id' => $recipient->id,
            'remarks' => 'Courier handoff complete.',
            'receiver_name' => 'Elena Mercado',
        ]);
        $search = app(DocumentSearchService::class);

        foreach ([
            'Special Endorsement',
            'North Harbor',
            'SRS-009',
            'Provincial Records',
            'External Organization',
            'Partially Claimed',
            'Receiving Box',
            'Urgent',
            'solar laboratory',
            'science fair',
            'Envelope seal',
            'Courier handoff',
            'Elena Mercado',
        ] as $term) {
            $this->assertTrue(
                $search->queryFor($actor, ['search' => $term])->whereKey($document)->exists(),
                "Expected the universal search to match [{$term}].",
            );
        }
    }

    public function test_combined_filters_apply_as_intersections(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $type = DocumentType::factory()->create();
        $matching = Document::factory()->create([
            'created_by' => $actor->id,
            'document_type_id' => $type->id,
            'current_status' => DocumentStatus::ReadyForPickup,
            'current_location' => PhysicalLocation::ReceivingBox,
            'priority' => Priority::Urgent,
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $matching->id,
            'recipient_unit_id' => $unit->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => '2026-07-20 08:00:00',
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'document_type_id' => $type->id,
            'current_status' => DocumentStatus::ReadyForPickup,
            'priority' => Priority::Normal,
        ]);

        $results = app(DocumentSearchService::class)->queryFor($actor, [
            'document_type_id' => $type->id,
            'destination_unit_id' => $unit->id,
            'current_status' => DocumentStatus::ReadyForPickup->value,
            'recipient_status' => RecipientStatus::ReadyForPickup->value,
            'current_location' => PhysicalLocation::ReceivingBox->value,
            'priority' => Priority::Urgent->value,
            'placed_from' => '2026-07-20',
            'placed_to' => '2026-07-20',
        ])->pluck('id')->all();

        $this->assertSame([$matching->id], $results);
    }

    public function test_all_document_transaction_and_recipient_date_ranges_are_supported(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $document = Document::factory()->create([
            'created_by' => $actor->id,
            'created_at' => '2026-07-01 09:00:00',
            'date_received' => '2026-07-03 09:00:00',
        ]);
        $recipient = DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'date_placed' => '2026-07-06 09:00:00',
            'date_claimed' => '2026-07-07 09:00:00',
        ]);
        $this->transaction($document, $actor, RoutingAction::SubmitByUnit, [
            'transaction_date' => '2026-07-02 09:00:00',
        ]);
        $this->transaction($document, $actor, RoutingAction::ForwardToUpstreamOffice, [
            'transaction_date' => '2026-07-04 09:00:00',
            'recipient_id' => $recipient->id,
        ]);

        $results = app(DocumentSearchService::class)->queryFor($actor, [
            'created_from' => '2026-07-01',
            'created_to' => '2026-07-01',
            'submitted_from' => '2026-07-02',
            'submitted_to' => '2026-07-02',
            'received_from' => '2026-07-03',
            'received_to' => '2026-07-03',
            'forwarded_from' => '2026-07-04',
            'forwarded_to' => '2026-07-04',
            'placed_from' => '2026-07-06',
            'placed_to' => '2026-07-06',
            'claimed_from' => '2026-07-07',
            'claimed_to' => '2026-07-07',
        ])->pluck('id')->all();

        $this->assertSame([$document->id], $results);
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(DocumentSearchService::class)->queryFor(
            User::factory()->levelTwo()->create(),
            ['created_from' => '2026-07-10', 'created_to' => '2026-07-01'],
        );
    }

    public function test_level_one_scope_covers_created_submitted_and_recipient_records_without_leakage(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($ownUnit)->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();
        $createdByOwn = Document::factory()->create([
            'created_by' => $user->id,
            'submitting_unit_id' => null,
            'subject' => 'Shared keyword own creator',
        ]);
        $submittedByOwn = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $ownUnit->id,
            'subject' => 'Shared keyword own submitter',
        ]);
        $assignedToOwn = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
            'subject' => 'Shared keyword own destination',
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $assignedToOwn->id,
            'recipient_unit_id' => $ownUnit->id,
        ]);
        $other = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
            'subject' => 'Shared keyword secret other unit',
        ]);
        $search = app(DocumentSearchService::class);

        $this->assertEqualsCanonicalizing(
            [$createdByOwn->id, $submittedByOwn->id, $assignedToOwn->id],
            $search->queryFor($user, ['search' => 'Shared keyword'])->pluck('id')->all(),
        );
        $this->assertFalse($search->queryFor($user)->whereKey($other)->exists());
        $this->assertTrue($search->queryFor($otherUser)->whereKey($other)->exists());
        $this->assertEqualsCanonicalizing(
            [$ownUnit->id, $otherUnit->id],
            array_keys($search->organizationalUnitOptions($user)),
        );
        $this->assertSame([$ownUnit->id], array_keys($search->destinationOptions($user)));

        $this->actingAs($user)
            ->get("/admin/documents/{$other->id}")
            ->assertNotFound();
    }

    public function test_level_two_sees_cancelled_and_all_matching_records(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $firstUnit = OrganizationalUnit::factory()->create();
        $secondUnit = OrganizationalUnit::factory()->create();
        $cancelled = Document::factory()->create([
            'created_by' => $actor->id,
            'submitting_unit_id' => $firstUnit->id,
            'subject' => 'Annual procurement archive',
            'current_status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
        $active = Document::factory()->create([
            'created_by' => $actor->id,
            'submitting_unit_id' => $secondUnit->id,
            'subject' => 'Annual procurement schedule',
        ]);

        $results = app(DocumentSearchService::class)
            ->queryFor($actor, ['search' => 'Annual procurement'])
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$cancelled->id, $active->id], $results);
    }

    public function test_paginated_search_uses_bounded_queries_without_n_plus_one_loading(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create();
        Document::factory()->count(30)->create([
            'created_by' => $user->id,
            'submitting_unit_id' => $unit->id,
            'subject' => 'Performance validation record',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = app(DocumentSearchService::class)
            ->queryFor($user, ['search' => 'Performance validation'])
            ->paginate(25);

        foreach ($page as $document) {
            $document->documentType?->name;
            $document->submittingUnit?->unit_name;
            $document->origin?->origin_name;
        }

        $this->assertCount(25, $page->items());
        $this->assertSame(30, $page->total());
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function transaction(
        Document $document,
        User $actor,
        RoutingAction $action,
        array $overrides = [],
    ): DocumentTransaction {
        return DocumentTransaction::query()->create([
            'document_id' => $document->id,
            'recipient_id' => null,
            'action' => $action,
            'previous_status' => DocumentStatus::Draft,
            'new_status' => DocumentStatus::Submitted,
            'from_location' => PhysicalLocation::OrganizationalUnit,
            'to_location' => PhysicalLocation::ManagingOffice,
            'performed_by' => $actor->id,
            'transaction_date' => now(),
            'remarks' => null,
            'receiver_name' => null,
            'receiver_position' => null,
            ...$overrides,
        ]);
    }
}
