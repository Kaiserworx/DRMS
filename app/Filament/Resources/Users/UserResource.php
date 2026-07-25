<?php

namespace App\Filament\Resources\Users;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\DeploymentSetting;
use App\Models\OrganizationalUnit;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $levelOneLabel = DeploymentSetting::current()?->level_1_unit_label ?? 'Unit';

        return $schema
            ->components([
                Section::make('Account')
                    ->schema([
                        TextInput::make('full_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('username')
                            ->rules(['regex:/^[A-Za-z0-9._-]+$/'])
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                                ? mb_strtolower(trim($state))
                                : null),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state)),
                    ])
                    ->columns(2),
                Section::make('Authorization')
                    ->schema([
                        Select::make('role')
                            ->options([
                                UserRole::LevelOne->value => "Level 1 — {$levelOneLabel} Encoder",
                                UserRole::LevelTwo->value => 'Level 2 — Records Administrator',
                            ])
                            ->required()
                            ->live(),
                        Select::make('organizational_unit_id')
                            ->label($levelOneLabel)
                            ->options(fn (Get $get): array => OrganizationalUnit::query()
                                ->when(
                                    $get('role') === UserRole::LevelOne->value,
                                    fn ($query) => $query->where('status', OperationalStatus::Active->value),
                                )
                                ->orderBy('unit_name')
                                ->pluck('unit_name', 'id')
                                ->all())
                            ->required(fn (Get $get): bool => $get('role') === UserRole::LevelOne->value)
                            ->searchable(),
                        Select::make('status')
                            ->options(OperationalStatus::options())
                            ->default(OperationalStatus::Active->value)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('organizationalUnit.unit_name')
                    ->label(DeploymentSetting::current()?->level_1_unit_label ?? 'Organizational unit')
                    ->placeholder('Managing-office access')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('position')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('username')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state === UserRole::LevelOne
                        ? 'Level 1'
                        : 'Level 2')
                    ->color(fn (UserRole $state): string => $state === UserRole::LevelTwo ? 'primary' : 'info'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OperationalStatus $state): string => $state->label())
                    ->color(fn (OperationalStatus $state): string => $state === OperationalStatus::Active ? 'success' : 'gray'),
                TextColumn::make('last_login_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
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
                SelectFilter::make('role')
                    ->options([
                        UserRole::LevelOne->value => 'Level 1',
                        UserRole::LevelTwo->value => 'Level 2',
                    ]),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
