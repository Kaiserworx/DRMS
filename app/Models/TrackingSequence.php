<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSequence extends Model
{
    protected $fillable = [
        'prefix',
        'office_code',
        'year',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
