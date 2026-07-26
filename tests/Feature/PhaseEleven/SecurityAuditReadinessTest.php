<?php

namespace Tests\Feature\PhaseEleven;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Filament\Auth\Login;
use App\Filament\Pages\OperationalReports;
use App\Filament\Resources\AuditEvents\AuditEventResource;
use App\Models\AuditEvent;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\ReceivingBoxTokenAudit;
use App\Models\User;
use App\Services\DocumentClassificationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class SecurityAuditReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    }

    public function test_successful_login_and_significant_administration_are_audited_without_passwords_or_qr_tokens(): void
    {
        $settings = DeploymentSetting::factory()->create();
        $actor = User::factory()->levelTwo()->create([
            'email' => 'security.admin@example.test',
            'password' => Hash::make('StrongPass!1234'),
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'login' => 'security.admin@example.test',
                'password' => 'StrongPass!1234',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'authentication.login_succeeded',
            'actor_id' => $actor->id,
            'auditable_type' => User::class,
            'auditable_id' => $actor->id,
        ]);

        $this->actingAs($actor);

        $unit = OrganizationalUnit::factory()->create();
        $target = User::factory()->levelOne($unit)->create();
        $type = DocumentType::factory()->create();
        $origin = DocumentOrigin::factory()->create();
        $document = Document::factory()->create([
            'document_type_id' => $type->id,
            'origin_id' => null,
            'submitting_unit_id' => $unit->id,
            'created_by' => $actor->id,
        ]);
        $box = ReceivingBox::factory()->create([
            'organizational_unit_id' => $unit->id,
            'qr_token' => str_repeat('Q', 64),
        ]);

        $settings->update(['system_name' => 'Audited DRMS']);
        $target->update(['password' => 'DifferentStrong!5678']);
        app(DocumentClassificationService::class)->classifyOrigin(
            $actor,
            $document,
            $origin->id,
            'AUDIT-REF-1',
        );

        foreach ([
            'organizational_unit.created',
            'user.created',
            'document_type.created',
            'document_origin.created',
            'document.created',
            'receiving_box.created',
            'deployment_setting.updated',
            'user.updated',
            'document.origin_classified',
        ] as $eventType) {
            $this->assertDatabaseHas('audit_events', [
                'event_type' => $eventType,
                'actor_id' => $actor->id,
            ]);
        }

        $passwordAudit = AuditEvent::query()
            ->where('event_type', 'user.updated')
            ->where('auditable_id', $target->id)
            ->latest('id')
            ->firstOrFail();
        $serializedDetails = json_encode($passwordAudit->details, JSON_THROW_ON_ERROR);

        $this->assertTrue($passwordAudit->details['changes']['password']['changed']);
        $this->assertStringNotContainsString('DifferentStrong!5678', $serializedDetails);
        $this->assertStringNotContainsString($box->qr_token, AuditEvent::query()->pluck('details')->toJson());
    }

    public function test_report_generation_and_export_are_audited_and_audit_ui_is_level_two_only(): void
    {
        DeploymentSetting::factory()->create();
        $admin = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $levelOne = User::factory()->levelOne($unit)->create();

        $this->actingAs($admin);

        Livewire::test(OperationalReports::class)
            ->call('runReport');

        $this->get(route('reports.export', ['report' => 'documents_by_date']))
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'report.generated',
            'actor_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'report.exported',
            'actor_id' => $admin->id,
        ]);

        $auditUrl = AuditEventResource::getUrl('index');

        $this->actingAs($levelOne)->get($auditUrl)->assertForbidden();
        $this->actingAs($admin)->get($auditUrl)->assertOk();
    }

    public function test_activity_custody_and_token_audit_records_are_immutable(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $document = Document::factory()->create(['created_by' => $actor->id]);
        $box = ReceivingBox::factory()->create();

        $activity = AuditEvent::query()->create([
            'event_type' => 'test.activity',
            'actor_id' => $actor->id,
            'occurred_at' => now(),
        ]);
        $transaction = DocumentTransaction::query()->create([
            'document_id' => $document->id,
            'action' => RoutingAction::SubmitByUnit,
            'previous_status' => DocumentStatus::Draft,
            'new_status' => DocumentStatus::Submitted,
            'from_location' => PhysicalLocation::OrganizationalUnit,
            'to_location' => PhysicalLocation::ManagingOffice,
            'performed_by' => $actor->id,
            'transaction_date' => now(),
        ]);
        $tokenAudit = ReceivingBoxTokenAudit::query()->create([
            'receiving_box_id' => $box->id,
            'action' => 'generated',
            'new_token_fingerprint' => hash('sha256', $box->qr_token),
            'performed_by' => $actor->id,
            'occurred_at' => now(),
        ]);

        foreach ([$activity, $transaction, $tokenAudit] as $record) {
            try {
                $record->delete();
                $this->fail('Expected immutable history deletion to fail.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
            }
        }
    }

    public function test_security_headers_csrf_registration_rate_limits_and_session_defaults_are_active(): void
    {
        $admin = User::factory()->levelTwo()->create();

        $this->get('/up')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $this->get('https://localhost/up')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $claimRoute = Route::getRoutes()->getByName('receiving-boxes.claim');
        $this->assertNotNull($claimRoute);
        $this->assertContains('web', $claimRoute->gatherMiddleware());
        $this->assertContains(
            ValidateCsrfToken::class,
            app('router')->getMiddlewareGroups()['web'],
        );
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));

        $this->actingAs($admin);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post(route('receiving-boxes.claim', ['qrToken' => str_repeat('A', 64)]))
                ->assertNotFound();
        }

        $this->post(route('receiving-boxes.claim', ['qrToken' => str_repeat('A', 64)]))
            ->assertTooManyRequests();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->get(route('reports.export', ['report' => 'documents_by_date']))
                ->assertOk();
        }

        $this->get(route('reports.export', ['report' => 'documents_by_date']))
            ->assertTooManyRequests();
    }

    public function test_inventory_escapes_untrusted_content_and_protected_fields_are_not_mass_assignable(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create();
        $box = ReceivingBox::factory()->create([
            'organizational_unit_id' => $unit->id,
        ]);
        $payload = '<script>alert("audit")</script>';
        $document = Document::factory()->create([
            'subject' => $payload,
            'current_status' => DocumentStatus::ReadyForPickup,
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('receiving-boxes.inventory', ['qrToken' => $box->qr_token]))
            ->assertOk()
            ->assertSee($payload)
            ->assertDontSee($payload, false);

        $untrusted = new Document;
        $untrusted->fill([
            'tracking_no' => 'ATTACK-TRACKING-NUMBER',
            'current_status' => DocumentStatus::Completed->value,
            'current_location' => PhysicalLocation::RecipientUnit->value,
            'subject' => 'Allowed field',
        ]);

        $this->assertNull($untrusted->tracking_no);
        $this->assertNull($untrusted->current_status);
        $this->assertNull($untrusted->current_location);
        $this->assertSame('Allowed field', $untrusted->subject);
    }
}
