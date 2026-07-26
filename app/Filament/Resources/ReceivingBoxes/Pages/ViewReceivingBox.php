<?php

namespace App\Filament\Resources\ReceivingBoxes\Pages;

use App\Filament\Resources\ReceivingBoxes\ReceivingBoxResource;
use App\Models\User;
use App\Services\ReceivingBoxService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReceivingBox extends ViewRecord
{
    protected static string $resource = ReceivingBoxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewInventory')
                ->label('Preview inventory')
                ->icon('heroicon-o-inbox')
                ->url(fn (): string => route('receiving-boxes.inventory', ['qrToken' => $this->record->qr_token]))
                ->openUrlInNewTab(),
            Action::make('printLabel')
                ->label('Print QR label')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('receiving-boxes.label', $this->record))
                ->openUrlInNewTab(),
            Action::make('regenerateQr')
                ->label('Regenerate QR')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('The existing QR code will stop working immediately. Print and attach the replacement label.')
                ->action(function (): void {
                    /** @var User $actor */
                    $actor = Filament::auth()->user();
                    $this->record = app(ReceivingBoxService::class)->regenerateToken(
                        $actor,
                        $this->record,
                        [
                            'ip_address' => request()->ip(),
                            'device_info' => request()->userAgent(),
                        ],
                    );
                    Notification::make()->title('QR token regenerated')->success()->send();
                    $this->redirect(ReceivingBoxResource::getUrl('view', ['record' => $this->record]));
                }),
            EditAction::make(),
        ];
    }
}
