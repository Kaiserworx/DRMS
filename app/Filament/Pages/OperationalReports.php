<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OperationalReportService;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\WithPagination;

class OperationalReports extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.operational-reports';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $slug = 'reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    protected static ?int $navigationSort = 20;

    public string $report = 'documents_by_date';

    /**
     * @var array<string, mixed>
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'organizational_unit_id' => null,
            'document_type_id' => null,
            'origin_id' => null,
            'performed_by' => null,
        ];
    }

    public function runReport(): void
    {
        app(OperationalReportService::class)->validatedFilters(
            $this->user(),
            $this->report,
            $this->filters,
        );

        app(AuditLogger::class)->record(
            'report.generated',
            details: $this->auditDetails(),
            actor: $this->user(),
        );

        $this->resetPage();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(OperationalReportService::class);
        $result = $service->paginate($this->user(), $this->report, $this->filters);

        return [
            'reportOptions' => $service->reportOptions(),
            'filterOptions' => $service->filterOptions($this->user()),
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'exportUrl' => route('reports.export', array_filter(
                ['report' => $this->report, ...$this->filters],
                fn (mixed $value): bool => filled($value),
            )),
        ];
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditDetails(): array
    {
        return [
            'report' => $this->report,
            'filters' => array_filter(
                $this->filters,
                fn (mixed $value): bool => filled($value),
            ),
        ];
    }
}
