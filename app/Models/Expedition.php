<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expedition extends Model
{
    protected $fillable = [
        'robot_id',
        'location_id',
        'status',
        'started_at',
        'finished_at',
        'duration_minutes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function robot(): BelongsTo
    {
        return $this->belongsTo(Robot::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ExpeditionLog::class);
    }
}