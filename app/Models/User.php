<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Services\UserAssignmentValidator;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organizational_unit_id',
        'full_name',
        'position',
        'email',
        'username',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            app(UserAssignmentValidator::class)->validate($user);
        });
    }

    /**
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class)->withTrashed();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->status === OperationalStatus::Active
            && ($this->isLevelTwo() || (
                $this->organizationalUnit !== null
                && ! $this->organizationalUnit->trashed()
                && $this->organizationalUnit->status === OperationalStatus::Active
            ));
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function isLevelTwo(): bool
    {
        return $this->role === UserRole::LevelTwo;
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value)
                ? mb_strtolower(trim($value))
                : null,
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => OperationalStatus::class,
        ];
    }
}
