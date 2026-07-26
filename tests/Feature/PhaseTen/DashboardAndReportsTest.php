<?php

namespace Tests\Feature\PhaseTen;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Models\Document;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\OperationalReportService;
use App\Services\SpreadsheetSafeCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DashboardAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_one_dashboard_counts_are_scoped_to_the_users_unit(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($ownUnit)->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();

        Document::factory()->create([
            'created_by' => $user->id,
            'submitting_unit_id' => $ownUnit->id,
            'current_status' => DocumentStatus::Draft,
        ]);
        Document::factory()->create([
            'created_by' => $user->id,
            'submitting_unit_id' => $ownUnit->id,
            'current_status' => DocumentStatus::Submitted,
        ]);
        Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
            'current_status' => DocumentStatus::Draft,
        ]);

        $ready = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
            'current_status' => DocumentStatus::ReadyForPickup,
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $ready->id,
            'recipient_unit_id' => $ownUnit->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now()->subHour(),
        ]);

        $completed = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
            'current_status' => DocumentStatus::Completed,
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $completed->id,
            'recipient_unit_id' => $ownUnit->id,
            'recipient_status' => RecipientStatus::ReceivedByRecipientUnit,
            'date_placed' => now()->subDay(),
            'date_claimed' => now(),
        ]);
        $this->createUnreadNotification($user);

        $metrics = app(DashboardMetricsService::class)->metricsFor($user);

        $this->assertSame(1, $metrics['draft']);
        $this->assertSame(1, $metrics['submitted']);
        $this->assertSame(1, $metrics['ready_pickup']);
        $this->assertSame(1, $metrics['claimed_today']);
        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(1, $metrics['unread_notifications']);
    }

    public function test_level_two_dashboard_and_box_summary_match_database_facts(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $box = ReceivingBox::factory()->create([
            'organizational_unit_id' => $unit->id,
            'box_location' => 'Counter A',
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'current_status' => DocumentStatus::Submitted,
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'current_status' => DocumentStatus::ReceivedAtManagingOffice,
            'date_received' => now(),
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'current_status' => DocumentStatus::ForwardedToUpstreamOffice,
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'current_status' => DocumentStatus::Completed,
        ]);
        $overdue = Document::factory()->create([
            'created_by' => $actor->id,
            'current_status' => DocumentStatus::ReceivedAtManagingOffice,
            'due_date' => today()->subDay(),
        ]);
        $ready = Document::factory()->create([
            'created_by' => $actor->id,
            'current_status' => DocumentStatus::ReadyForPickup,
            'due_date' => today()->addDay(),
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $ready->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now()->subDays(2),
        ]);

        $service = app(DashboardMetricsService::class);
        $metrics = $service->metricsFor($actor);
        $summary = $service->receivingBoxSummary($actor)->sole();

        $this->assertSame(1, $metrics['received_today']);
        $this->assertSame(1, $metrics['awaiting_verification']);
        $this->assertSame(2, $metrics['waiting_forwarding']);
        $this->assertSame(1, $metrics['at_upstream']);
        $this->assertSame(1, $metrics['in_receiving_boxes']);
        $this->assertSame(1, $metrics['pending_confirmation']);
        $this->assertSame(1, $metrics['completed']);
        $this->assertSame(2, $metrics['overdue_unclaimed']);
        $this->assertSame('Counter A', $summary['location']);
        $this->assertSame(1, $summary['waiting']);
        $this->assertNotNull($summary['oldest_waiting_at']);
        $this->assertTrue($overdue->exists);
    }

    public function test_reports_filter_authorized_database_facts_and_categorize_terminal_states(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $completed = Document::factory()->create([
            'created_by' => $actor->id,
            'submitting_unit_id' => $unit->id,
            'subject' => 'Completed report row',
            'current_status' => DocumentStatus::Completed,
            'created_at' => '2026-07-10 08:00:00',
        ]);
        $cancelled = Document::factory()->create([
            'created_by' => $actor->id,
            'submitting_unit_id' => $unit->id,
            'subject' => 'Cancelled report row',
            'current_status' => DocumentStatus::Cancelled,
            'cancelled_at' => '2026-07-11 08:00:00',
            'created_at' => '2026-07-11 08:00:00',
        ]);
        Document::factory()->create([
            'created_by' => $actor->id,
            'submitting_unit_id' => $otherUnit->id,
            'created_at' => '2026-07-12 08:00:00',
        ]);

        $reports = app(OperationalReportService::class);
        $filtered = $reports->paginate($actor, 'documents_by_unit', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'organizational_unit_id' => $unit->id,
        ])['rows'];
        $cancelledRows = $reports->paginate($actor, 'cancelled_documents', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ])['rows'];

        $this->assertEqualsCanonicalizing(
            [$completed->tracking_no, $cancelled->tracking_no],
            collect($filtered->items())->pluck('tracking_no')->all(),
        );
        $this->assertSame([$cancelled->tracking_no], collect($cancelledRows->items())->pluck('tracking_no')->all());
    }

    public function test_level_one_reports_exports_and_filter_options_do_not_leak_other_unit_records(): void
    {
        $ownUnit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($ownUnit)->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();
        $visible = Document::factory()->create([
            'created_by' => $user->id,
            'submitting_unit_id' => $ownUnit->id,
            'subject' => '=HYPERLINK("https://example.test","Visible")',
        ]);
        $hidden = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
            'subject' => 'Secret other-unit report',
        ]);

        $rows = app(OperationalReportService::class)
            ->paginate($user, 'documents_by_date', [])['rows'];

        $this->assertSame([$visible->tracking_no], collect($rows->items())->pluck('tracking_no')->all());

        $response = $this->actingAs($user)->get(route('reports.export', [
            'report' => 'documents_by_date',
        ]));
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString($visible->tracking_no, $csv);
        $this->assertStringNotContainsString($hidden->tracking_no, $csv);
        $this->assertStringNotContainsString('Secret other-unit report', $csv);
        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringNotContainsString('Document ID', $csv);

        $this->expectException(ValidationException::class);
        app(OperationalReportService::class)->paginate($user, 'documents_by_unit', [
            'organizational_unit_id' => $otherUnit->id,
        ]);
    }

    public function test_invalid_report_dates_and_unknown_report_are_rejected(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $reports = app(OperationalReportService::class);

        try {
            $reports->paginate($actor, 'documents_by_date', [
                'from' => '2026-07-20',
                'to' => '2026-07-01',
            ]);
            $this->fail('Expected the reversed date range to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('to', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $reports->paginate($actor, 'not-a-report', []);
    }

    public function test_transaction_monthly_and_time_reports_use_document_scope_and_defined_intervals(): void
    {
        $unit = OrganizationalUnit::factory()->create();
        $otherUnit = OrganizationalUnit::factory()->create();
        $user = User::factory()->levelOne($unit)->create();
        $otherUser = User::factory()->levelOne($otherUnit)->create();
        $document = Document::factory()->create([
            'created_by' => $user->id,
            'submitting_unit_id' => $unit->id,
            'current_status' => DocumentStatus::Completed,
            'created_at' => '2026-07-01 08:00:00',
        ]);
        $hidden = Document::factory()->create([
            'created_by' => $otherUser->id,
            'submitting_unit_id' => $otherUnit->id,
        ]);
        $this->transaction($document, $user, RoutingAction::SubmitByUnit, [
            'transaction_date' => '2026-07-01 08:00:00',
        ]);
        $this->transaction($document, $user, RoutingAction::ClaimByRecipientUnit, [
            'new_status' => DocumentStatus::Completed,
            'transaction_date' => '2026-07-02 08:00:00',
        ]);
        $this->transaction($hidden, $otherUser, RoutingAction::SubmitByUnit);
        DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'recipient_status' => RecipientStatus::ReceivedByRecipientUnit,
            'date_placed' => '2026-07-01 20:00:00',
            'date_claimed' => '2026-07-02 08:00:00',
        ]);

        $reports = app(OperationalReportService::class);
        $monthly = $reports->paginate($user, 'monthly_movement', [
            'from' => '2026-07-01',
            'to' => '2026-07-31',
        ])['rows'];
        $averages = $reports->paginate($user, 'time_averages', [])['rows'];

        $this->assertSame(2, $monthly->total());
        $this->assertSame(2, $averages->total());
        $this->assertSame('24.00', $averages->items()[0]['average_hours']);
        $this->assertSame('12.00', $averages->items()[1]['average_hours']);
        $this->assertSame(1, $averages->items()[0]['observations']);
        $this->assertSame(1, $averages->items()[1]['observations']);
    }

    public function test_spreadsheet_safety_handles_whitespace_and_formula_prefixes(): void
    {
        $csv = app(SpreadsheetSafeCsv::class);

        foreach (['=1+1', '+SUM(A1:A2)', '-2+3', '@IMPORTXML()', " \t=CMD()"] as $payload) {
            $this->assertStringStartsWith("'", $csv->safeValue($payload));
        }

        $this->assertSame('Ordinary subject', $csv->safeValue('Ordinary subject'));
        $this->assertSame(42, $csv->safeValue(42));
    }

    public function test_all_approved_report_types_execute_with_pagination(): void
    {
        $actor = User::factory()->levelTwo()->create();
        $unit = OrganizationalUnit::factory()->create();
        $box = ReceivingBox::factory()->create(['organizational_unit_id' => $unit->id]);
        $document = Document::factory()->create([
            'created_by' => $actor->id,
            'submitting_unit_id' => $unit->id,
            'current_status' => DocumentStatus::ReadyForPickup,
        ]);
        DocumentRecipient::factory()->create([
            'document_id' => $document->id,
            'recipient_unit_id' => $unit->id,
            'receiving_box_id' => $box->id,
            'recipient_status' => RecipientStatus::ReadyForPickup,
            'date_placed' => now(),
        ]);
        $this->transaction($document, $actor, RoutingAction::ForwardToUpstreamOffice);

        $reports = app(OperationalReportService::class);

        $this->assertCount(12, $reports->reportOptions());

        foreach (array_keys($reports->reportOptions()) as $report) {
            $result = $reports->paginate($actor, $report, []);

            $this->assertNotEmpty($result['columns'], "Expected columns for [{$report}].");
            $this->assertSame(25, $result['rows']->perPage());
        }
    }

    public function test_authenticated_users_can_open_the_dashboard_and_reports_page(): void
    {
        $user = User::factory()->levelTwo()->create();

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/reports')->assertOk();
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

    private function createUnreadNotification(User $user): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'phase-ten-test',
            'data' => ['title' => 'Unread dashboard test'],
        ]);
    }
}
