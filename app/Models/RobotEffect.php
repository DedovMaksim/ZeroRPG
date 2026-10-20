<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RobotEffect extends Model
{
    protected $fillable = ['key', 'stat', 'modifier', 'expires_at'];

    protected $casts = [
        'modifier' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }
}
