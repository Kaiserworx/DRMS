<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\OperationalStatus;
use App\Enums\RoutingAction;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentRoutingService;
use App\Services\RecipientAssignmentService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        /** @var User $actor */
        $actor = Filament::auth()->user();
        $routing = app(DocumentRoutingService::class);
        $allowed = $routing->allowedActions($actor, $this->record);

        $routingActions = collect($allowed)
            ->reject(fn (RoutingAction $action): bool => in_array($action, [
                RoutingAction::AssignRecipientUnit,
                RoutingAction::Cancel,
            ], true))
            ->map(fn (RoutingAction $routingAction): Action => Action::make($routingAction->value)
                ->label($routingAction->label())
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('remarks')->rows(2),
                ])
                ->action(function (array $data) use ($actor, $routing, $routingAction): void {
                    $routing->perform(
                        $actor,
                        $this->record,
                        $routingAction,
                        $data['remarks'] ?? null,
                        [
                            'ip_address' => request()->ip(),
                            'device_info' => request()->userAgent(),
                        ],
                    );
                    $this->record->refresh();
                    Notification::make()->title("{$routingAction->label()} recorded")->success()->send();
                    $this->redirect(DocumentResource::getUrl('view', ['record' => $this->record]));
                }))
            ->all();

        return [
            ...$routingActions,
            Action::make('assignRecipients')
                ->label('Assign recipients')
                ->icon('heroicon-o-user-group')
                ->visible(in_array(RoutingAction::AssignRecipientUnit, $allowed, true))
                ->schema([
                    Select::make('recipient_unit_ids')
                        ->label('Recipient units')
                        ->options(OrganizationalUnit::query()
                            ->where('status', OperationalStatus::Active->value)
                            ->whereNotIn('id', $this->record->recipients()->select('recipient_unit_id'))
                            ->orderBy('unit_name')
                            ->pluck('unit_name', 'id')
                            ->all())
                        ->multiple()
                        ->searchable()
                        ->required(),
                    Textarea::make('remarks')->rows(2),
                ])
                ->action(function (array $data): void {
                    /** @var User $actor */
                    $actor = Filament::auth()->user();
                    app(DocumentRoutingService::class)->assignRecipients(
                        $actor,
                        $this->record,
                        $data['recipient_unit_ids'],
                        $data['remarks'] ?? null,
                        [
                            'ip_address' => request()->ip(),
                            'device_info' => request()->userAgent(),
                        ],
                    );
                    $this->record->refresh();
                    Notification::make()->title('Recipient units assigned')->success()->send();
                    $this->redirect(DocumentResource::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('removeRecipient')
                ->label('Remove recipient')
                ->color('danger')
                ->visible(fn (): bool => Filament::auth()->user() instanceof User
                    && Filament::auth()->user()->isLevelTwo()
                    && $this->record->recipients()->exists())
                ->schema([
                    Select::make('recipient_id')
                        ->label('Recipient assignment')
                        ->options($this->record->recipients()
                            ->with('recipientUnit')
                            ->get()
                            ->reject->hasDownstreamActivity()
                            ->pluck('recipientUnit.unit_name', 'id')
                            ->all())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User $actor */
                    $actor = Filament::auth()->user();
                    $recipient = $this->record->recipients()->findOrFail($data['recipient_id']);
                    app(RecipientAssignmentService::class)->remove($actor, $recipient);
                    $this->record->refresh();
                    Notification::make()->title('Recipient assignment removed')->success()->send();
                    $this->redirect(DocumentResource::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('cancelDocument')
                ->label(RoutingAction::Cancel->label())
                ->color('danger')
                ->requiresConfirmation()
                ->visible(in_array(RoutingAction::Cancel, $allowed, true))
                ->schema([
                    Textarea::make('reason')
                        ->label('Cancellation reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($actor, $routing): void {
                    $routing->perform(
                        $actor,
                        $this->record,
                        RoutingAction::Cancel,
                        $data['reason'],
                        [
                            'ip_address' => request()->ip(),
                            'device_info' => request()->userAgent(),
                        ],
                    );
                    $this->record->refresh();
                    Notification::make()->title('Document cancelled')->success()->send();
                    $this->redirect(DocumentResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
