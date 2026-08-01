<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use Database\Factories\ReceivingBoxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ReceivingBox extends Model
{
    /** @use HasFactory<ReceivingBoxFactory> */
    use HasFactory;

    protected $fillable = [
        'organizational_unit_id',
        'box_location',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $box): void {
            if ($box->status !== OperationalStatus::Active) {
                return;
            }

            $unitIsActive = OrganizationalUnit::query()
                ->whereKey($box->organizational_unit_id)
                ->where('status', OperationalStatus::Active->value)
                ->exists();

            if (! $unitIsActive) {
                throw ValidationException::withMessages([
                    'organizational_unit_id' => 'An active receiving box requires an active organizational unit.',
                ]);
            }
        });
    }

    /**
     * @param  Builder<ReceivingBox>  $query
     * @return Builder<ReceivingBox>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OperationalStatus::Active->value);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class)->withTrashed();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(DocumentRecipient::class);
    }

    public function tokenAudits(): HasMany
    {
        return $this->hasMany(ReceivingBoxTokenAudit::class);
    }

    protected function casts(): array
    {
        return [
            'status' => OperationalStatus::class,
            'last_qr_generated_at' => 'datetime',
        ];
    }
}
