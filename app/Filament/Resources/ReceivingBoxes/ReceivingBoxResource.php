<?php

namespace App\Filament\Resources\ReceivingBoxes;

use App\Enums\OperationalStatus;
use App\Enums\ReceivingBoxTokenAction;
use App\Enums\RecipientStatus;
use App\Filament\Resources\ReceivingBoxes\Pages\CreateReceivingBox;
use App\Filament\Resources\ReceivingBoxes\Pages\EditReceivingBox;
use App\Filament\Resources\ReceivingBoxes\Pages\ListReceivingBoxes;
use App\Filament\Resources\ReceivingBoxes\Pages\ViewReceivingBox;
use App\Models\OrganizationalUnit;
use App\Models\ReceivingBox;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReceivingBoxResource extends Resource
{
    protected static ?string $model = ReceivingBox::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $recordTitleAttribute = 'organizationalUnit.unit_name';

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Permanent receiving box')
                ->description('Each organizational unit has one permanent box record and a regenerable secure QR token.')
                ->schema([
                    Select::make('organizational_unit_id')
                        ->label('Organizational unit')
                        ->options(fn (?ReceivingBox $record): array => OrganizationalUnit::query()
                            ->where('status', OperationalStatus::Active->value)
                            ->when(
                                $record,
                                fn ($query) => $query->where(function ($query) use ($record): void {
                                    $query->whereKey($record->organizational_unit_id)
                                        ->orWhereDoesntHave('receivingBox');
                                }),
                                fn ($query) => $query->whereDoesntHave('receivingBox'),
                            )
                            ->orderBy('unit_name')
                            ->pluck('unit_name', 'id')
                            ->all())
                        ->searchable()
                        ->disabledOn('edit')
                        ->required()
                        ->unique('receiving_boxes', 'organizational_unit_id', ignoreRecord: true),
                    TextInput::make('box_location')
                        ->label('Physical box location')
                        ->maxLength(255)
                        ->placeholder('Example: Records counter, cabinet 2'),
                    Select::make('status')
                        ->options(OperationalStatus::options())
                        ->default(OperationalStatus::Active->value)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Receiving-box details')
                ->schema([
                    TextEntry::make('organizationalUnit.unit_name')->label('Organizational unit'),
                    TextEntry::make('box_location')->label('Physical box location')->placeholder('Not recorded'),
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (OperationalStatus $state): string => $state->label())
                        ->color(fn (OperationalStatus $state): string => $state === OperationalStatus::Active ? 'success' : 'gray'),
                    TextEntry::make('last_qr_generated_at')->label('QR last generated')->dateTime(),
                    TextEntry::make('ready_for_pickup_count')
                        ->label('Inventory preview')
                        ->state(fn (ReceivingBox $record): string => sprintf(
                            '%d document(s) ready for pickup',
                            $record->recipients()
                                ->where('recipient_status', RecipientStatus::ReadyForPickup->value)
                                ->count(),
                        ))
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('QR token audit')
                ->description('Audit history is immutable. Only token fingerprints are retained after regeneration.')
                ->schema([
                    RepeatableEntry::make('tokenAudits')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('occurred_at')->label('Date')->dateTime(),
                            TextEntry::make('action')
                                ->badge()
                                ->formatStateUsing(fn (ReceivingBoxTokenAction $state): string => ucfirst($state->value)),
                            TextEntry::make('performer.full_name')->label('Performed by'),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organizationalUnit.unit_name')
                    ->label('Organizational unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('box_location')
                    ->label('Physical location')
                    ->placeholder('Not recorded')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OperationalStatus $state): string => $state->label())
                    ->color(fn (OperationalStatus $state): string => $state === OperationalStatus::Active ? 'success' : 'gray'),
                TextColumn::make('recipients_count')
                    ->label('Placed records')
                    ->counts('recipients'),
                TextColumn::make('last_qr_generated_at')
                    ->label('QR last generated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(OperationalStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceivingBoxes::route('/'),
            'create' => CreateReceivingBox::route('/create'),
            'view' => ViewReceivingBox::route('/{record}'),
            'edit' => EditReceivingBox::route('/{record}/edit'),
        ];
    }
}
