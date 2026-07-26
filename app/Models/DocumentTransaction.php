<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\PhysicalLocation;
use App\Enums\RoutingAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentTransaction extends Model
{
    protected $fillable = [
        'document_id',
        'recipient_id',
        'action',
        'previous_status',
        'new_status',
        'from_location',
        'to_location',
        'performed_by',
        'transaction_date',
        'remarks',
        'receiver_name',
        'receiver_position',
        'ip_address',
        'device_info',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Document transaction history is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Document transaction history cannot be deleted.'));
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DocumentRecipient::class, 'recipient_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected function casts(): array
    {
        return [
            'action' => RoutingAction::class,
            'previous_status' => DocumentStatus::class,
            'new_status' => DocumentStatus::class,
            'from_location' => PhysicalLocation::class,
            'to_location' => PhysicalLocation::class,
            'transaction_date' => 'datetime',
        ];
    }
}
