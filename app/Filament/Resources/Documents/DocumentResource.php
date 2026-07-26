<?php

namespace App\Filament\Resources\Documents;

use App\Enums\DocumentStatus;
use App\Enums\OperationalStatus;
use App\Enums\OriginType;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\Document;
use App\Models\DocumentOrigin;
use App\Models\DocumentRecipient;
use App\Models\DocumentTransaction;
use App\Models\DocumentType;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\DocumentSearchService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;

    protected static ?string $recordTitleAttribute = 'tracking_no';

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $user = Filament::auth()->user();
        $isLevelTwo = $user instanceof User && $user->isLevelTwo();

        return $schema->components([
            Section::make('Document registration')
                ->schema([
                    TextInput::make('tracking_no')
                        ->label('Tracking number')
                        ->placeholder('Generated automatically when saved')
                        ->disabled()
                        ->dehydrated(false),
                    Select::make('document_type_id')
                        ->label('Document type')
                        ->options(DocumentType::activeOptions())
                        ->searchable()
                        ->required(),
                    TextInput::make('subject')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                    Select::make('priority')
                        ->options(Priority::options())
                        ->default(Priority::Normal->value)
                        ->required(),
                    Select::make('submitting_unit_id')
                        ->label('Submitting unit')
                        ->options(OrganizationalUnit::query()
                            ->where('status', OperationalStatus::Active->value)
                            ->orderBy('unit_name')
                            ->pluck('unit_name', 'id')
                            ->all())
                        ->default($isLevelTwo ? null : $user?->organizational_unit_id)
                        ->disabled(! $isLevelTwo)
                        ->dehydrated($isLevelTwo)
                        ->searchable(),
                    Select::make('origin_id')
                        ->label('Document origin')
                        ->options(DocumentOrigin::activeOptions())
                        ->searchable()
                        ->visible($isLevelTwo),
                    TextInput::make('origin_reference_no')
                        ->label('Origin reference number')
                        ->maxLength(255)
                        ->visible($isLevelTwo),
                    DateTimePicker::make('date_received'),
                    DatePicker::make('due_date')
                        ->afterOrEqual('date_received'),
                    Textarea::make('remarks')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document details')
                ->schema([
                    TextEntry::make('tracking_no')->label('Tracking number')->copyable(),
                    TextEntry::make('current_status')
                        ->badge()
                        ->formatStateUsing(fn (DocumentStatus $state): string => $state->label()),
                    TextEntry::make('documentType.name')->label('Document type'),
                    TextEntry::make('priority')
                        ->badge()
                        ->formatStateUsing(fn (Priority $state): string => $state->label()),
                    TextEntry::make('subject')->columnSpanFull(),
                    TextEntry::make('description')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('submittingUnit.unit_name')->label('Submitting unit')->placeholder('—'),
                    TextEntry::make('origin.origin_name')->label('Origin')->placeholder('—'),
                    TextEntry::make('origin_reference_no')->label('Origin reference number')->placeholder('—'),
                    TextEntry::make('date_received')->dateTime()->placeholder('—'),
                    TextEntry::make('due_date')->date()->placeholder('—'),
                    TextEntry::make('remarks')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('recipient_units')
                        ->label('Recipient units')
                        ->state(function (Document $record): string {
                            $user = Filament::auth()->user();
                            $query = $record->recipients()->with('recipientUnit')->orderBy('date_assigned');

                            if ($user instanceof User && ! $user->isLevelTwo()) {
                                $query->where('recipient_unit_id', $user->organizational_unit_id);
                            }

                            return $query->get()
                                ->map(fn (DocumentRecipient $recipient): string => sprintf(
                                    '%s — %s',
                                    $recipient->recipientUnit->unit_name,
                                    $recipient->recipient_status->label(),
                                ))
                                ->join(', ');
                        })
                        ->placeholder('No recipient units assigned')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Transaction timeline')
                ->description('Routing history is append-only and read-only.')
                ->schema([
                    RepeatableEntry::make('transactions')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('transaction_date')->label('Date')->dateTime(),
                            TextEntry::make('action')
                                ->formatStateUsing(fn (RoutingAction $state): string => $state->label())
                                ->badge(),
                            TextEntry::make('performer.full_name')->label('Performed by'),
                            TextEntry::make('status_change')
                                ->label('Status')
                                ->state(fn (DocumentTransaction $record): string => sprintf(
                                    '%s → %s',
                                    $record->previous_status->label(),
                                    $record->new_status->label(),
                                )),
                            TextEntry::make('location_change')
                                ->label('Location')
                                ->state(fn (DocumentTransaction $record): string => sprintf(
                                    '%s → %s',
                                    $record->from_location->label(),
                                    $record->to_location->label(),
                                )),
                            TextEntry::make('remarks')->placeholder('—')->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $isLevelOne = ! static::authenticatedUser()->isLevelTwo();
        $statusColumn = TextColumn::make('current_status')
            ->label('Status')
            ->badge()
            ->formatStateUsing(fn (DocumentStatus $state): string => $state->label());

        return $table
            ->columns([
                TextColumn::make('tracking_no')
                    ->label('Tracking number')
                    ->searchable(
                        query: fn (Builder $query, string $search): Builder => app(DocumentSearchService::class)
                            ->applyTerm($query, $search),
                    )
                    ->sortable(),
                ...($isLevelOne ? [$statusColumn] : []),
                TextColumn::make('subject')
                    ->limit(70),
                TextColumn::make('documentType.name')
                    ->label('Document type'),
                TextColumn::make('submittingUnit.unit_name')
                    ->label('Submitting unit')
                    ->placeholder('—'),
                TextColumn::make('priority')
                    ->badge()
                    ->formatStateUsing(fn (Priority $state): string => $state->label()),
                ...($isLevelOne ? [] : [$statusColumn]),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('advanced')
                    ->label('Advanced filters')
                    ->schema([
                        Select::make('document_type_id')
                            ->label('Document type')
                            ->options(fn (): array => static::searchService()->documentTypeOptions(static::authenticatedUser()))
                            ->searchable(),
                        Select::make('organizational_unit_id')
                            ->label('Organizational unit')
                            ->options(fn (): array => static::searchService()->organizationalUnitOptions(static::authenticatedUser()))
                            ->searchable(),
                        Select::make('origin_type')
                            ->options(OriginType::options()),
                        Select::make('origin_id')
                            ->label('Origin name')
                            ->options(fn (): array => static::searchService()->originOptions(static::authenticatedUser()))
                            ->searchable(),
                        Select::make('destination_unit_id')
                            ->label('Destination')
                            ->options(fn (): array => static::searchService()->destinationOptions(static::authenticatedUser()))
                            ->searchable(),
                        Select::make('current_status')
                            ->label('Main status')
                            ->options(DocumentStatus::options()),
                        Select::make('recipient_status')
                            ->options(RecipientStatus::options()),
                        Select::make('current_location')
                            ->label('Current location')
                            ->options(PhysicalLocation::options()),
                        Select::make('priority')
                            ->options(Priority::options()),
                        DatePicker::make('created_from')->label('Created from'),
                        DatePicker::make('created_to')->label('Created to')->afterOrEqual('created_from'),
                        DatePicker::make('submitted_from')->label('Submitted from'),
                        DatePicker::make('submitted_to')->label('Submitted to')->afterOrEqual('submitted_from'),
                        DatePicker::make('received_from')->label('Received from'),
                        DatePicker::make('received_to')->label('Received to')->afterOrEqual('received_from'),
                        DatePicker::make('forwarded_from')->label('Forwarded from'),
                        DatePicker::make('forwarded_to')->label('Forwarded to')->afterOrEqual('forwarded_from'),
                        DatePicker::make('placed_from')->label('Placed from'),
                        DatePicker::make('placed_to')->label('Placed to')->afterOrEqual('placed_from'),
                        DatePicker::make('claimed_from')->label('Claimed from'),
                        DatePicker::make('claimed_to')->label('Claimed to')->afterOrEqual('claimed_from'),
                    ])
                    ->columns(3)
                    ->query(fn (Builder $query, array $data): Builder => static::searchService()
                        ->applyFilters($query, $data)),
            ])
            ->searchPlaceholder('Tracking, subject, type, unit, origin, status, remarks, or receiver')
            ->searchDebounce('500ms')
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([25, 50, 100])
            ->recordUrl(fn (Document $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Filament::auth()->user();

        return $user instanceof User
            ? $query->visibleTo($user)->with(['documentType', 'submittingUnit', 'origin'])
            : $query->whereRaw('1 = 0');
    }

    public static function getGlobalSearchResults(string $search): Collection
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User || blank($search)) {
            return collect();
        }

        return static::searchService()
            ->queryFor($user, ['search' => $search])
            ->limit(static::getGlobalSearchResultsLimit())
            ->get()
            ->map(fn (Document $record): GlobalSearchResult => new GlobalSearchResult(
                title: $record->tracking_no,
                url: static::getUrl('view', ['record' => $record]),
                details: [
                    'Subject' => $record->subject,
                    'Status' => $record->current_status->label(),
                ],
            ));
    }

    private static function authenticatedUser(): User
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private static function searchService(): DocumentSearchService
    {
        return app(DocumentSearchService::class);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
        ];
    }
}
