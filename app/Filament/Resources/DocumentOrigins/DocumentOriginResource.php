<?php

namespace App\Filament\Resources\DocumentOrigins;

use App\Enums\OperationalStatus;
use App\Enums\OriginType;
use App\Filament\Resources\DocumentOrigins\Pages\CreateDocumentOrigin;
use App\Filament\Resources\DocumentOrigins\Pages\EditDocumentOrigin;
use App\Filament\Resources\DocumentOrigins\Pages\ListDocumentOrigins;
use App\Models\DocumentOrigin;
use App\Rules\UniqueNormalizedReferenceName;
use App\Services\ReferenceNameNormalizer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentOriginResource extends Resource
{
    protected static ?string $model = DocumentOrigin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $recordTitleAttribute = 'origin_name';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document origin')
                    ->schema([
                        Select::make('origin_type')
                            ->options(OriginType::options())
                            ->required()
                            ->live(),
                        TextInput::make('origin_name')
                            ->required()
                            ->maxLength(255)
                            ->rules(fn (Get $get, ?DocumentOrigin $record): array => [
                                new UniqueNormalizedReferenceName(
                                    DocumentOrigin::class,
                                    $record?->getKey(),
                                    ['origin_type' => $get('origin_type')],
                                ),
                            ])
                            ->dehydrateStateUsing(
                                fn (string $state): string => ReferenceNameNormalizer::display($state),
                            ),
                        TextInput::make('office_code')
                            ->label('Office code')
                            ->alphaDash()
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                ? mb_strtoupper(trim($state))
                                : null),
                        Select::make('status')
                            ->options(OperationalStatus::options())
                            ->default(OperationalStatus::Active->value)
                            ->required(),
                        Textarea::make('address')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('origin_name')
            ->columns([
                TextColumn::make('origin_type')
                    ->badge()
                    ->formatStateUsing(fn (OriginType $state): string => $state->label()),
                TextColumn::make('origin_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('office_code')
                    ->label('Office code')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('address')
                    ->limit(80)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OperationalStatus $state): string => $state->label())
                    ->color(fn (OperationalStatus $state): string => $state === OperationalStatus::Active ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('origin_type')
                    ->options(OriginType::options()),
                SelectFilter::make('status')
                    ->options(OperationalStatus::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentOrigins::route('/'),
            'create' => CreateDocumentOrigin::route('/create'),
            'edit' => EditDocumentOrigin::route('/{record}/edit'),
        ];
    }
}
