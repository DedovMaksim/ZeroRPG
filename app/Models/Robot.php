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
}