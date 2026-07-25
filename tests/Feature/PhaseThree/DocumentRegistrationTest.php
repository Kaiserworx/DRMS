<?php

namespace Tests\Feature\PhaseThree;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Models\DeploymentSetting;
use App\Models\DocumentOrigin;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class DocumentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeploymentSetting::factory()->create([
            'tracking_prefix' => 'DRMS',
            'managing_office_code' => 'NORTH',
        ]);
    }

    public function test_tracking_numbers_are_settings_derived_sequential_unique_and_immutable(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $type = DocumentType::factory()->create();
        $service = app(DocumentRegistrationService::class);

        $first = $service->register($actor, $this->input($type->id));
        $second = $service->register($actor, $this->input($type->id));

        $year = now()->format('Y');
        $this->assertSame("DRMS-NORTH-{$year}-000001", $first->tracking_no);
        $this->assertSame("DRMS-NORTH-{$year}-000002", $second->tracking_no);
        $this->assertNotSame($first->tracking_no, $second->tracking_no);

        $this->expectException(LogicException::class);
        $first->tracking_no = 'CHANGED';
        $first->save();
    }

    public function test_level_one_registration_derives_own_unit_and_forbids_origin(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $actor = User::factory()->levelOne($unit)->create();
        $type = DocumentType::factory()->create();
        $origin = DocumentOrigin::factory()->create();
        $service = app(DocumentRegistrationService::class);

        $document = $service->register($actor, $this->input($type->id, [
            'submitting_unit_id' => OrganizationalUnit::factory()->create()->id,
        ]));

        $this->assertSame($unit->id, $document->submitting_unit_id);
        $this->assertNull($document->origin_id);
        $this->assertSame(PhysicalLocation::OrganizationalUnit, $document->current_location);

        $this->expectException(ValidationException::class);
        $service->register($actor, $this->input($type->id, ['origin_id' => $origin->id]));
    }

    public function test_level_two_may_register_with_any_active_unit_and_origin_or_neither(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $type = DocumentType::factory()->create();
        $unit = OrganizationalUnit::factory()->create();
        $origin = DocumentOrigin::factory()->create();
        $service = app(DocumentRegistrationService::class);

        $withBoth = $service->register($actor, $this->input($type->id, [
            'submitting_unit_id' => $unit->id,
            'origin_id' => $origin->id,
        ]));
        $withNeither = $service->register($actor, $this->input($type->id));

        $this->assertSame($unit->id, $withBoth->submitting_unit_id);
        $this->assertSame($origin->id, $withBoth->origin_id);
        $this->assertNull($withNeither->submitting_unit_id);
        $this->assertNull($withNeither->origin_id);
        $this->assertSame(PhysicalLocation::ManagingOffice, $withNeither->current_location);
    }

    public function test_inactive_reference_values_and_invalid_dates_are_rejected_without_insert(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $inactiveType = DocumentType::factory()->inactive()->create();

        try {
            app(DocumentRegistrationService::class)->register($actor, $this->input($inactiveType->id, [
                'date_received' => '2026-07-25 09:00:00',
                'due_date' => '2026-07-24',
            ]));
            $this->fail('Expected validation to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('documents', 0);
            $this->assertDatabaseCount('tracking_sequences', 0);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(int $typeId, array $overrides = []): array
    {
        return array_merge([
            'document_type_id' => $typeId,
            'subject' => 'Board Resolution for review',
            'description' => null,
            'origin_id' => null,
            'origin_reference_no' => null,
            'submitting_unit_id' => null,
            'priority' => Priority::Normal->value,
            'initial_status' => DocumentStatus::Draft->value,
            'date_received' => null,
            'due_date' => null,
            'remarks' => null,
        ], $overrides);
    }
}
