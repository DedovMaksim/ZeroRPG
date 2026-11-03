<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'difficulty',
        'required_level',
        'base_duration_minutes',
        'battery_cost',
    ];

    public function isUnlockedFor(int $level): bool
    {
        return $level >= $this->required_level;
    }

    public function expeditions(): HasMany
    {
        return $this->hasMany(Expedition::class);
    }
}