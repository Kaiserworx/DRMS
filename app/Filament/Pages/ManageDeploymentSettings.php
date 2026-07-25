<?php

namespace App\Filament\Pages;

use App\Enums\DeploymentProfile;
use App\Enums\OperationalStatus;
use App\Models\DeploymentSetting;
use App\Models\Document;
use App\Models\User;
use App\Services\OrganizationalUnitHierarchy;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class ManageDeploymentSettings extends Page
{
    protected string $view = 'filament.pages.manage-deployment-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Deployment Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public DeploymentSetting $settings;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isLevelTwo();
    }

    public function mount(): void
    {
        $this->settings = DeploymentSetting::query()->firstOrCreate(
            ['id' => 1],
            DeploymentSetting::defaults(),
        );

        $this->form->fill($this->settings->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deployment profile')
                    ->description('Controls generic hierarchy rules and the labels displayed throughout DRMS.')
                    ->schema([
                        Select::make('deployment_profile')
                            ->options(DeploymentProfile::options())
                            ->required(),
                        TextInput::make('system_name')
                            ->required()
                            ->maxLength(255),
                        Select::make('status')
                            ->options(OperationalStatus::options())
                            ->required(),
                    ])
                    ->columns(3),
                Section::make('Managing office')
                    ->schema([
                        TextInput::make('managing_office_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('managing_office_code')
                            ->required()
                            ->maxLength(50)
                            ->rules(['regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'])
                            ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper(trim((string) $state))),
                        Select::make('managing_office_level')
                            ->options(DeploymentProfile::options())
                            ->required(),
                        TextInput::make('tracking_prefix')
                            ->required()
                            ->maxLength(20)
                            ->rules(['regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/'])
                            ->dehydrateStateUsing(fn (?string $state): string => mb_strtoupper(trim((string) $state))),
                    ])
                    ->columns(2),
                Section::make('Display labels')
                    ->description('These labels change the interface without changing domain or database code.')
                    ->schema([
                        TextInput::make('upstream_office_label')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('level_1_unit_label')
                            ->label('Level 1 unit label')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('receiving_box_label')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(3),
            ])
            ->model($this->settings)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $profile = DeploymentProfile::from($data['deployment_profile']);

        if (Document::query()->exists()
            && ($data['tracking_prefix'] !== $this->settings->tracking_prefix
                || $data['managing_office_code'] !== $this->settings->managing_office_code)) {
            Notification::make()
                ->title('Tracking identity is locked')
                ->body('The tracking prefix and managing office code cannot change after the first document is registered.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($data, $profile): void {
            app(OrganizationalUnitHierarchy::class)->validateExistingUnitsForProfile($profile);
            $this->settings->update($data);
        });

        Notification::make()
            ->title('Deployment settings saved')
            ->success()
            ->send();
    }
}
