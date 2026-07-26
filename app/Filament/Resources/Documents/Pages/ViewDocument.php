<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Filament\Resources\Documents\DocumentResource;
use App\Models\DocumentOrigin;
use App\Models\DocumentRecipient;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentClassificationService;
use App\Services\DocumentRoutingService;
use App\Services\RecipientAssignmentService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            Action::make('classifyOrigin')
                ->label('Define origin')
                ->icon('heroicon-o-map-pin')
                ->visible(fn (): bool => $actor->can('classifyOrigin', $this->record))
                ->schema([
                    Select::make('origin_id')
                        ->label('Official document origin')
                        ->options(DocumentOrigin::activeOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('origin_reference_no')
                        ->label('Origin reference number')
                        ->maxLength(255),
                ])
                ->action(function (array $data) use ($actor): void {
                    app(DocumentClassificationService::class)->classifyOrigin(
                        $actor,
                        $this->record,
                        (int) $data['origin_id'],
                        $data['origin_reference_no'] ?? null,
                    );
                    $this->record->refresh();
                    Notification::make()->title('Official origin defined')->success()->send();
                    $this->redirect(DocumentResource::getUrl('view', ['record' => $this->record]));
                }),
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
                    && $this->record->recipients()
                        ->where('recipient_status', RecipientStatus::Assigned->value)
                        ->whereNull('receiving_box_id')
                        ->whereNull('date_placed')
                        ->exists())
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
            Action::make('placeInReceivingBox')
                ->label(RoutingAction::PlaceInReceivingBox->label())
                ->icon('heroicon-o-inbox-arrow-down')
                ->visible(fn (): bool => $actor->isLevelTwo()
                    && in_array($this->record->current_status, [
                        DocumentStatus::ForDistribution,
                        DocumentStatus::ReadyForPickup,
                    ], true)
                    && $this->record->recipients()
                        ->where('recipient_status', RecipientStatus::Assigned->value)
                        ->whereNull('receiving_box_id')
                        ->whereNull('date_placed')
                        ->whereHas('recipientUnit', fn ($query) => $query
                            ->where('status', OperationalStatus::Active->value))
                        ->whereHas('recipientUnit.receivingBox', fn ($query) => $query
                            ->where('status', OperationalStatus::Active->value))
                        ->exists())
                ->schema([
                    Select::make('recipient_id')
                        ->label('Recipient assignment')
                        ->options(fn (): array => $this->record->recipients()
                            ->with(['recipientUnit.receivingBox'])
                            ->where('recipient_status', RecipientStatus::Assigned->value)
                            ->whereNull('receiving_box_id')
                            ->whereNull('date_placed')
                            ->whereHas('recipientUnit', fn ($query) => $query
                                ->where('status', OperationalStatus::Active->value))
                            ->whereHas('recipientUnit.receivingBox', fn ($query) => $query
                                ->where('status', OperationalStatus::Active->value))
                            ->get()
                            ->mapWithKeys(fn (DocumentRecipient $recipient): array => [
                                $recipient->getKey() => sprintf(
                                    '%s — %s',
                                    $recipient->recipientUnit->unit_name,
                                    $recipient->recipientUnit->receivingBox->box_location ?: 'Location not recorded',
                                ),
                            ])
                            ->all())
                        ->searchable()
                        ->required(),
                    Textarea::make('remarks')->rows(2),
                ])
                ->action(function (array $data) use ($actor): void {
                    $recipient = $this->record->recipients()
                        ->with('recipientUnit.receivingBox')
                        ->findOrFail($data['recipient_id']);
                    $box = $recipient->recipientUnit->receivingBox;

                    app(DocumentRoutingService::class)->placeInReceivingBox(
                        $actor,
                        $recipient,
                        $box,
                        $data['remarks'] ?? null,
                        [
                            'ip_address' => request()->ip(),
                            'device_info' => request()->userAgent(),
                        ],
                    );
                    $this->record->refresh();
                    Notification::make()->title('Document placed in receiving box')->success()->send();
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
