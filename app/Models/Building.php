<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = [
        'base_id',
        'key',
        'name',
        'level',
        'status',
        'capacity',
    ];

    public function base(): BelongsTo
    {
        return $this->belongsTo(Base::class);
    }

    public function warehouseInventories(): HasMany
    {
        return $this->hasMany(WarehouseInventory::class);
    }

    public function usedStorage(): int
    {
        return $this->warehouseInventories()
            ->with('resource')
            ->get()
            ->sum(function ($item) {
                return $item->amount * $item->resource->storage_size;
            });
    }

    public function freeStorage(): int
    {
        return $this->capacity - $this->usedStorage();
    }
}