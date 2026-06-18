<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpeditionLog extends Model
{
    protected $fillable = [
        'expedition_id',
        'minute',
        'event_type',
        'message',
        'event_time',
    ];

    protected $casts = [
        'event_time' => 'datetime',
    ];

    public function expedition(): BelongsTo
    {
        return $this->belongsTo(Expedition::class);
    }
}