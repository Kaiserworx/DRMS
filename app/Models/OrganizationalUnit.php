<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Enums\OrganizationalUnitType;
use App\Services\OrganizationalUnitHierarchy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'unit_type',
        'unit_code',
        'unit_name',
        'short_name',
        'address',
        'contact_person',
        'contact_number',
        'email',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            app(OrganizationalUnitHierarchy::class)->validate($unit);
        });
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    protected function casts(): array
    {
        return [
            'unit_type' => OrganizationalUnitType::class,
            'status' => OperationalStatus::class,
        ];
    }
}
