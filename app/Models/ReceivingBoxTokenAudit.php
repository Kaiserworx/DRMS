<?php

namespace App\Models;

use App\Enums\ReceivingBoxTokenAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReceivingBoxTokenAudit extends Model
{
    protected $fillable = [
        'receiving_box_id',
        'action',
        'previous_token_fingerprint',
        'new_token_fingerprint',
        'performed_by',
        'occurred_at',
        'ip_address',
        'device_info',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Receiving-box token audit history is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Receiving-box token audit history cannot be deleted.'));
    }

    public function receivingBox(): BelongsTo
    {
        return $this->belongsTo(ReceivingBox::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected function casts(): array
    {
        return [
            'action' => ReceivingBoxTokenAction::class,
            'occurred_at' => 'datetime',
        ];
    }
}
