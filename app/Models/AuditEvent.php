<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'actor_id',
        'auditable_type',
        'auditable_id',
        'details',
        'ip_address',
        'device_info',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Activity audit history is immutable.'));
        static::deleting(fn (): never => throw new LogicException('Activity audit history cannot be deleted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
