<?php

namespace App\Filament\Resources\OrganizationalUnits;

use App\Enums\DeploymentProfile;
use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Filament\Resources\OrganizationalUnits\Pages\CreateOrganizationalUnit;
use App\Filament\Resources\OrganizationalUnits\Pages\EditOrganizationalUnit;
use App\Filament\Resources\OrganizationalUnits\Pages\ListOrganizationalUnits;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Services\OrganizationalUnitHierarchy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationalUnitResource extends Resource
{
    protected static ?string $model = OrganizationalUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'unit_name';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $profile = DeploymentSetting::current()?->deployment_profile ?? DeploymentProfile::District;
        $allowedTypes = app(OrganizationalUnitHierarchy::class)->allowedTypes($profile);

        return $schema
            ->components([
                Section::make('Organizational unit')
                    ->schema([
                        Select::make('unit_type')
                            ->options(OrganizationalUnitType::options($allowedTypes))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
                        Select::make('parent_id')
                            ->label('Parent organizational unit')
                            ->options(fn (?OrganizationalUnit $record): array => OrganizationalUnit::query()
                                ->where('status', OperationalStatus::Active->value)
                                ->where('unit_type', OrganizationalUnitType::District->value)
                                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                ->orderBy('unit_name')
                                ->pluck('unit_name', 'id')
                                ->all())
                            ->searchable()
                            ->visible(fn (Get $get): bool => $profile === DeploymentProfile::Division
                                && $get('unit_type') === OrganizationalUnitType::School->value),
                        TextInput::make('unit_code')
                            ->required()
                            ->maxLength(50)
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper(trim((string) $state))),
                        TextInput::make('unit_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('short_name')
                            ->maxLength(255),
                        Select::make('status')
                            ->options(OperationalStatus::options())
                            ->default(OperationalStatus::Active->value)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Contact information')
                    ->schema([
                        Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('contact_person')
                            ->maxLength(255),
                        TextInput::make('contact_number')
                            ->maxLength(50),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unit_name')
            ->columns([
                TextColumn::make('parent.unit_name')
                    ->label('Parent unit')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('unit_type')
                    ->badge()
                    ->formatStateUsing(fn (OrganizationalUnitType $state): string => $state->label()),
                TextColumn::make('unit_code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('unit_name')
                    ->searchable(),
                TextColumn::make('short_name')
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->searchable(),
                TextColumn::make('contact_number')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
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
                SelectFilter::make('unit_type')
                    ->options(OrganizationalUnitType::options(
                        app(OrganizationalUnitHierarchy::class)->allowedTypes(
                            DeploymentSetting::current()?->deployment_profile ?? DeploymentProfile::District,
                        ),
                    )),
                SelectFilter::make('status')
                    ->options(OperationalStatus::options()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListOrganizationalUnits::route('/'),
            'create' => CreateOrganizationalUnit::route('/create'),
            'edit' => EditOrganizationalUnit::route('/{record}/edit'),
        ];
    }
}
