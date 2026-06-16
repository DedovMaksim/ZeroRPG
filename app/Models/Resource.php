<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'storage_size',
    ];

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }
}