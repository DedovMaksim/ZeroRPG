<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\WarehouseInventory;

class WarehouseInventory extends Model
{
    protected $fillable = [
        'building_id',
        'resource_id',
        'amount',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function withdraw(Request $request, WarehouseInventory $warehouseInventory)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $robot = auth()->user()->robot;

        $warehouse = $robot->base
            ->buildings()
            ->where('key', 'warehouse')
            ->where('status', 'active')
            ->first();

        if (! $warehouse || $warehouseInventory->building_id !== $warehouse->id) {
            abort(403);
        }

        $resourceSize = $warehouseInventory->resource->storage_size;
        $freeRobotStorage = $robot->totalStorage() - $robot->usedStorage();

        $maxByStorage = intdiv($freeRobotStorage, $resourceSize);

        $amount = min(
            $data['amount'],
            $warehouseInventory->amount,
            $maxByStorage
        );

        if ($amount <= 0) {
            return back()->with('error', 'На SSD робота недостаточно места.');
        }

        $inventoryItem = $robot->inventories()->firstOrCreate(
            [
                'resource_id' => $warehouseInventory->resource_id,
            ],
            [
                'amount' => 0,
            ]
        );

        $inventoryItem->increment('amount', $amount);

        $warehouseInventory->decrement('amount', $amount);

        if ($warehouseInventory->fresh()->amount <= 0) {
            $warehouseInventory->delete();
        }

        return back()->with('success', 'Ресурс перенесён на SSD робота.');
    }
}