<?php

namespace App\Filament\Widgets;

use App\Enums\OperationalStatus;
use App\Models\DeploymentSetting;
use App\Models\ReceivingBox;
use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Services\ReceivingBoxQrCodeService;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Gate;

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
        $levelOneReceivingBox = $this->levelOneReceivingBox($user);

        return [
            'user' => $user,
            'settings' => DeploymentSetting::current(),
            'metrics' => $service->metricsFor($user),
            'metricLabels' => $user->isLevelTwo()
                ? $this->levelTwoLabels()
                : $this->levelOneLabels(),
            'boxSummary' => $service->receivingBoxSummary($user),
            'levelOneReceivingBox' => $levelOneReceivingBox,
        ];
    }

    /**
     * @return array{location: string|null, url: string, svg: string}|null
     */
    private function levelOneReceivingBox(User $user): ?array
    {
        if ($user->isLevelTwo()) {
            return null;
        }

        $unit = $user->organizationalUnit;

        if ($unit === null || $unit->trashed() || $unit->status !== OperationalStatus::Active) {
            return null;
        }

        $box = ReceivingBox::query()
            ->active()
            ->where('organizational_unit_id', $unit->id)
            ->first();

        if ($box === null || Gate::forUser($user)->denies('viewInventory', $box)) {
            return null;
        }

        $qrCode = app(ReceivingBoxQrCodeService::class);

        return [
            'location' => $box->box_location,
            'url' => $qrCode->inventoryUrl($box),
            'svg' => $qrCode->svg($box, 224),
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
