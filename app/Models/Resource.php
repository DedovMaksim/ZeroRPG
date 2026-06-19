<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'storage_size',
        'scrap_value',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}