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
        'base_duration_minutes',
        'battery_cost',
    ];

    public function expeditions(): HasMany
    {
        return $this->hasMany(Expedition::class);
    }
}