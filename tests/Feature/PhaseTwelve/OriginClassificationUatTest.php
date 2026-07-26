<?php

namespace Tests\Feature\PhaseTwelve;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentClassificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class OriginClassificationUatTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_two_defines_an_unclassified_level_one_document_origin_with_atomic_audit(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $creator = User::factory()->levelOne($unit)->create();
        $admin = User::factory()->levelTwo()->create();
        $origin = DocumentOrigin::factory()->create(['origin_name' => 'Division Office']);
        $document = Document::factory()->create([
            'created_by' => $creator->id,
            'submitting_unit_id' => $unit->id,
            'origin_id' => null,
            'origin_reference_no' => null,
        ]);

        $this->actingAs($admin);

        $classified = app(DocumentClassificationService::class)->classifyOrigin(
            $admin,
            $document,
            $origin->id,
            'DIV-2026-001',
        );

        $this->assertSame($origin->id, $classified->origin_id);
        $this->assertSame('DIV-2026-001', $classified->origin_reference_no);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'document.origin_classified',
            'actor_id' => $admin->id,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_wrong_role_inactive_origin_and_repeat_classification_are_rejected(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $levelOne = User::factory()->levelOne($unit)->create();
        $admin = User::factory()->levelTwo()->create();
        $activeOrigin = DocumentOrigin::factory()->create();
        $inactiveOrigin = DocumentOrigin::factory()->inactive()->create();
        $document = Document::factory()->create([
            'origin_id' => null,
            'origin_reference_no' => null,
        ]);
        $service = app(DocumentClassificationService::class);

        try {
            $service->classifyOrigin($levelOne, $document, $activeOrigin->id);
            $this->fail('Expected Level 1 classification to be denied.');
        } catch (AuthorizationException) {
            $this->assertNull($document->fresh()->origin_id);
        }

        try {
            $service->classifyOrigin($admin, $document, $inactiveOrigin->id);
            $this->fail('Expected inactive origin validation.');
        } catch (ValidationException) {
            $this->assertNull($document->fresh()->origin_id);
        }

        $service->classifyOrigin($admin, $document, $activeOrigin->id);

        $this->expectException(ValidationException::class);
        $service->classifyOrigin($admin, $document, $activeOrigin->id);
    }

    public function test_direct_origin_changes_fail_and_classification_rolls_back_when_audit_write_fails(): void
    {
        $admin = User::factory()->levelTwo()->create();
        $origin = DocumentOrigin::factory()->create();
        $directMutation = Document::factory()->create([
            'origin_id' => null,
            'origin_reference_no' => null,
        ]);

        try {
            $directMutation->origin_id = $origin->id;
            $directMutation->save();
            $this->fail('Expected direct origin mutation to fail.');
        } catch (LogicException) {
            $this->assertNull($directMutation->fresh()->origin_id);
        }

        $document = Document::factory()->create([
            'origin_id' => null,
            'origin_reference_no' => null,
        ]);

        AuditEvent::creating(function (): never {
            throw new RuntimeException('Simulated classification audit failure.');
        });

        try {
            app(DocumentClassificationService::class)->classifyOrigin(
                $admin,
                $document,
                $origin->id,
            );
            $this->fail('Expected classification audit failure.');
        } catch (RuntimeException) {
            $this->assertNull($document->fresh()->origin_id);
        }
    }
}
