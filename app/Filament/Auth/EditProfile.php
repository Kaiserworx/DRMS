<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('full_name')
                    ->label('Full name')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('position')
                    ->maxLength(255),
                $this->getEmailFormComponent(),
                TextInput::make('username')
                    ->required()
                    ->rules(['regex:/^[A-Za-z0-9._-]+$/'])
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? mb_strtolower(trim($state))
                        : null),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return TextInput::make('currentPassword')
            ->label(__('filament-panels::auth/pages/edit-profile.form.current_password.label'))
            ->validationAttribute(__('filament-panels::auth/pages/edit-profile.form.current_password.validation_attribute'))
            ->belowContent(__('filament-panels::auth/pages/edit-profile.form.current_password.below_content'))
            ->password()
            ->autocomplete('current-password')
            ->currentPassword(guard: Filament::getAuthGuard())
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->visible(fn (Get $get): bool => filled($get('password'))
                || ($get('email') !== $this->getUser()->getAttributeValue('email'))
                || ($get('username') !== $this->getUser()->getAttributeValue('username')))
            ->dehydrated(false);
    }
}
