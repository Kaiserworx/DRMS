<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OperationalReportService;
use App\Services\SpreadsheetSafeCsv;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(
        Request $request,
        OperationalReportService $reports,
        SpreadsheetSafeCsv $csv,
        AuditLogger $audit,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $report = (string) $request->query('report');
        $filters = $request->only([
            'from',
            'to',
            'organizational_unit_id',
            'document_type_id',
            'origin_id',
            'performed_by',
        ]);

        $dataset = $reports->export($user, $report, $filters);
        $audit->record(
            'report.exported',
            details: [
                'report' => $report,
                'filters' => array_filter(
                    $filters,
                    fn (mixed $value): bool => filled($value),
                ),
            ],
            actor: $user,
        );
        $filename = 'drms-'.$report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(
            fn () => $csv->write(fopen('php://output', 'wb'), $dataset['columns'], $dataset['rows']),
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
