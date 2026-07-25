<?php

namespace App\Filament\Widgets;

use App\Models\DeploymentSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class PhaseOneOverview extends Widget
{
    protected string $view = 'filament.widgets.phase-one-overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $settings = DeploymentSetting::current();

        return [
            'settings' => $settings,
            'user' => $user,
        ];
    }
}
