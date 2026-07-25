<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Enums\OperationalStatus;
use App\Filament\Resources\DocumentTypes\Pages\CreateDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\EditDocumentType;
use App\Filament\Resources\DocumentTypes\Pages\ListDocumentTypes;
use App\Models\DocumentType;
use App\Rules\UniqueNormalizedReferenceName;
use App\Services\ReferenceNameNormalizer;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Reference Data';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document type')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->rules(fn (?DocumentType $record): array => [
                                new UniqueNormalizedReferenceName(
                                    DocumentType::class,
                                    $record?->getKey(),
                                ),
                            ])
                            ->dehydrateStateUsing(
                                fn (string $state): string => ReferenceNameNormalizer::display($state),
                            ),
                        Select::make('status')
                            ->options(OperationalStatus::options())
                            ->default(OperationalStatus::Active->value)
                            ->required(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('default_workflow')
                            ->label('Default workflow')
                            ->placeholder('Reserved for Phase 5')
                            ->helperText('Controlled workflow definitions are introduced by the approved routing phase.')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(80)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('default_workflow')
                    ->label('Default workflow')
                    ->placeholder('Reserved for Phase 5'),
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
            'index' => ListDocumentTypes::route('/'),
            'create' => CreateDocumentType::route('/create'),
            'edit' => EditDocumentType::route('/{record}/edit'),
        ];
    }
}
