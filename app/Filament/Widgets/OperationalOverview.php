<?php

namespace App\Filament\Widgets;

use App\Models\DeploymentSetting;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class OperationalOverview extends Widget
{
    protected string $view = 'filament.widgets.operational-overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $service = app(DashboardMetricsService::class);

        return [
            'user' => $user,
            'settings' => DeploymentSetting::current(),
            'metrics' => $service->metricsFor($user),
            'metricLabels' => $user->isLevelTwo()
                ? $this->levelTwoLabels()
                : $this->levelOneLabels(),
            'boxSummary' => $service->receivingBoxSummary($user),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function levelOneLabels(): array
    {
        return [
            'draft' => 'Draft documents',
            'submitted' => 'Submitted documents',
            'received_managing' => 'Received by Managing Office',
            'forwarded_upstream' => 'Forwarded to Upstream Office',
            'ready_pickup' => 'Ready for Pickup',
            'claimed_today' => 'Claimed today',
            'completed' => 'Completed',
            'unread_notifications' => 'Unread notifications',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function levelTwoLabels(): array
    {
        return [
            'received_today' => 'Received today',
            'awaiting_verification' => 'Awaiting verification',
            'waiting_forwarding' => 'Waiting for forwarding',
            'at_upstream' => 'At Upstream Office',
            'returned_upstream' => 'Returned from Upstream Office',
            'waiting_distribution' => 'Waiting for distribution',
            'in_receiving_boxes' => 'In receiving boxes',
            'pending_confirmation' => 'Pending confirmation',
            'completed' => 'Completed',
            'overdue_unclaimed' => 'Overdue or unclaimed',
        ];
    }
}
