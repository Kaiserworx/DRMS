<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\Priority;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    private bool $routingStateChangeAllowed = false;

    private bool $originClassificationAllowed = false;

    protected $fillable = [
        'document_type_id',
        'subject',
        'description',
        'origin_id',
        'origin_reference_no',
        'submitting_unit_id',
        'priority',
        'date_received',
        'due_date',
        'remarks',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $document): void {
            if ($document->isDirty('tracking_no')) {
                throw new LogicException('A document tracking number is immutable.');
            }

            if (($document->isDirty('current_status') || $document->isDirty('current_location'))
                && ! $document->routingStateChangeAllowed) {
                throw new LogicException('Document status and location may only change through the routing service.');
            }

            if (($document->isDirty('origin_id') || $document->isDirty('origin_reference_no'))
                && ! $document->originClassificationAllowed) {
                throw new LogicException('Document origin may only be defined through the classification service.');
            }
        });

        static::saved(function (self $document): void {
            $document->routingStateChangeAllowed = false;
            $document->originClassificationAllowed = false;
        });
    }

    /**
     * @param  Builder<Document>  $query
     * @return Builder<Document>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isLevelTwo()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('submitting_unit_id', $user->organizational_unit_id)
                ->orWhereHas('creator', fn (Builder $query) => $query
                    ->where('organizational_unit_id', $user->organizational_unit_id))
                ->orWhereHas('recipients', fn (Builder $query) => $query
                    ->where('recipient_unit_id', $user->organizational_unit_id));
        });
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(DocumentOrigin::class);
    }

    public function submittingUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'submitting_unit_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(DocumentRecipient::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DocumentTransaction::class)->orderBy('transaction_date')->orderBy('id');
    }

    public function applyRoutingState(DocumentStatus $status, PhysicalLocation $location): void
    {
        $this->routingStateChangeAllowed = true;
        $this->current_status = $status;
        $this->current_location = $location;
    }

    public function applyOriginClassification(int $originId, ?string $originReferenceNo): void
    {
        $this->originClassificationAllowed = true;
        $this->origin_id = $originId;
        $this->origin_reference_no = $originReferenceNo;
    }

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'current_status' => DocumentStatus::class,
            'current_location' => PhysicalLocation::class,
            'date_received' => 'datetime',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }
}
