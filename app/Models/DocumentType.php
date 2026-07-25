<?php

namespace App\Models;

use App\Enums\OperationalStatus;
use App\Services\ReferenceNameNormalizer;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'default_workflow',
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $documentType): void {
            $documentType->name = ReferenceNameNormalizer::display($documentType->name);
            $documentType->normalized_name = ReferenceNameNormalizer::key($documentType->name);
        });
    }

    /**
     * @param  Builder<DocumentType>  $query
     * @return Builder<DocumentType>
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
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function casts(): array
    {
        return [
            'status' => OperationalStatus::class,
        ];
    }
}
