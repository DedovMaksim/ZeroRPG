<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\WarehouseInventory;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function deposit(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $robot = auth()->user()->robot;

        if ($inventory->robot_id !== $robot->id) {
            abort(403);
        }

        $warehouse = $robot->base
            ->buildings()
            ->where('key', 'warehouse')
            ->where('status', 'active')
            ->first();

        if (! $warehouse) {
            return back()->with('error', 'Склад ещё не восстановлен.');
        }

        $resourceSize = $inventory->resource->storage_size;
        $freeStorage = $warehouse->freeStorage();

        $maxByStorage = intdiv($freeStorage, $resourceSize);

        $amount = min(
            $data['amount'],
            $inventory->amount,
            $maxByStorage
        );

        if ($amount <= 0) {
            return back()->with('error', 'На складе недостаточно места.');
        }

        $warehouseItem = $warehouse->warehouseInventories()->firstOrCreate(
            ['resource_id' => $inventory->resource_id],
            ['amount' => 0]
        );

        $warehouseItem->increment('amount', $amount);
        $inventory->decrement('amount', $amount);

        if ($inventory->fresh()->amount <= 0) {
            $inventory->delete();
        }

        return back()->with('success', 'Ресурс переложен на склад.');
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
            ['resource_id' => $warehouseInventory->resource_id],
            ['amount' => 0]
        );

        $inventoryItem->increment('amount', $amount);
        $warehouseInventory->decrement('amount', $amount);

        if ($warehouseInventory->fresh()->amount <= 0) {
            $warehouseInventory->delete();
        }

        return back()->with('success', 'Ресурс перенесён на SSD робота.');
    }
}