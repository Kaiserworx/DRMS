<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response && auth()->check()) {
            /** @var User $user */
            $user = auth()->user();
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();

            app(AuditLogger::class)->record(
                'authentication.login_succeeded',
                $user,
                actor: $user,
            );
        }

        return $response;
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Email or username')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $login = mb_strtolower(trim((string) $data['login']));

        return [
            filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username' => $login,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
