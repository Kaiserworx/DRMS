<?php

namespace App\Models;

use App\Enums\RecipientStatus;
use App\Enums\RoutingAction;
use Database\Factories\DocumentRecipientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

class DocumentRecipient extends Model
{
    /** @use HasFactory<DocumentRecipientFactory> */
    use HasFactory;

    private bool $workflowStateChangeAllowed = false;

    protected $fillable = [
        'document_id',
        'recipient_unit_id',
        'receiving_box_id',
        'recipient_status',
        'date_assigned',
        'date_placed',
        'date_claimed',
        'claimed_by_user_id',
        'received_by_name',
        'received_by_position',
        'remarks',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $recipient): void {
            $workflowFields = [
                'receiving_box_id',
                'recipient_status',
                'date_placed',
                'date_claimed',
                'claimed_by_user_id',
                'received_by_name',
                'received_by_position',
            ];

            if ($recipient->isDirty($workflowFields) && ! $recipient->workflowStateChangeAllowed) {
                throw new LogicException('Recipient workflow state may only change through an approved workflow service.');
            }
        });

        static::saved(function (self $recipient): void {
            $recipient->workflowStateChangeAllowed = false;
        });
    }

    /**
     * @param  Builder<DocumentRecipient>  $query
     * @return Builder<DocumentRecipient>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isLevelTwo()
            ? $query
            : $query->where('recipient_unit_id', $user->organizational_unit_id);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function recipientUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'recipient_unit_id')->withTrashed();
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id')->withTrashed();
    }

    public function receivingBox(): BelongsTo
    {
        return $this->belongsTo(ReceivingBox::class);
    }

    public function hasDownstreamActivity(): bool
    {
        return $this->recipient_status !== RecipientStatus::Assigned
            || $this->receiving_box_id !== null
            || $this->date_placed !== null
            || $this->date_claimed !== null
            || $this->claimed_by_user_id !== null
            || filled($this->received_by_name)
            || filled($this->received_by_position)
            || $this->transactions()
                ->where('action', '!=', RoutingAction::AssignRecipientUnit->value)
                ->exists();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(DocumentTransaction::class, 'recipient_id');
    }

    public function applyPlacementState(ReceivingBox $box, Carbon $placedAt): void
    {
        $this->workflowStateChangeAllowed = true;
        $this->receiving_box_id = $box->getKey();
        $this->recipient_status = RecipientStatus::ReadyForPickup;
        $this->date_placed = $placedAt;
    }

    public function applyClaimState(
        User $claimant,
        string $receiverName,
        string $receiverPosition,
        Carbon $claimedAt,
    ): void {
        $this->workflowStateChangeAllowed = true;
        $this->recipient_status = RecipientStatus::ReceivedByRecipientUnit;
        $this->date_claimed = $claimedAt;
        $this->claimed_by_user_id = $claimant->getKey();
        $this->received_by_name = $receiverName;
        $this->received_by_position = $receiverPosition;
    }

    protected function casts(): array
    {
        return [
            'recipient_status' => RecipientStatus::class,
            'date_assigned' => 'datetime',
            'date_placed' => 'datetime',
            'date_claimed' => 'datetime',
        ];
    }
}
