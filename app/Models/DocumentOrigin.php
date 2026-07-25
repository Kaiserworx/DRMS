<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Enums\OriginType;
use App\Services\ReferenceNameNormalizer;
use Database\Factories\DocumentOriginFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentOrigin extends Model
{
    /** @use HasFactory<DocumentOriginFactory> */
    use HasFactory;

    protected $fillable = [
        'origin_type',
        'origin_name',
        'office_code',
        'address',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $documentOrigin): void {
            $documentOrigin->origin_name = ReferenceNameNormalizer::display($documentOrigin->origin_name);
            $documentOrigin->normalized_name = ReferenceNameNormalizer::key($documentOrigin->origin_name);
        });
    }

    /**
     * @param  Builder<DocumentOrigin>  $query
     * @return Builder<DocumentOrigin>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OperationalStatus::Active->value);
    }

    /**
     * @return array<int, string>
     */
    public static function activeOptions(): array
    {
        return self::query()
            ->active()
            ->orderBy('origin_name')
            ->pluck('origin_name', 'id')
            ->all();
    }

    protected function officeCode(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value)
                ? mb_strtoupper(trim($value))
                : null,
        );
    }

    protected function casts(): array
    {
        return [
            'origin_type' => OriginType::class,
            'status' => OperationalStatus::class,
        ];
    }
}
