<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Robot extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'version',
        'cpu',
        'ram',
        'ssd',
        'battery',
        'integrity',
        'scraps',
        'battery_updated_at',
        'level',
        'xp',
    ];

    protected $casts = [
        'battery_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function expeditions(): HasMany
    {
        return $this->hasMany(Expedition::class);
    }

    public function xpForNextLevel(): int
    {
        return 100
            + (($this->level - 1) * 50)
            + (($this->level - 1) ** 2 * 25);
    }

    public function levelProgressPercent(): int
    {
        return min(
            100,
            (int)(($this->xp / $this->xpForNextLevel()) * 100)
        );
    }

    public function maxBattery(): int
    {
        return 100 + (($this->level - 1) * 5);
    }
}