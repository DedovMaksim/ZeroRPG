<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\RobotItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RobotItemController extends Controller
{
    public function buy(Item $item): RedirectResponse
    {
        $purchased = DB::transaction(function () use ($item): bool {
            $robot = auth()->user()->robot;
            $balance = $robot->newQuery()->whereKey($robot->id)
                ->where('scraps', '>=', $item->price)
                ->decrement('scraps', $item->price);

            if ($balance !== 1) {
                return false;
            }

            $robot->robotItems()->create(['item_id' => $item->id, 'equipped' => false]);

            return true;
        });

        return back()->with($purchased ? 'success' : 'error',
            $purchased ? 'Предмет куплен.' : 'Недостаточно скрапов.');
    }

    public function equip(RobotItem $robotItem): RedirectResponse
    {
        $robot = auth()->user()->robot;
        abort_unless($robotItem->robot_id === $robot->id, 403);

        DB::transaction(function () use ($robot, $robotItem): void {
            $type = $robotItem->item->type;
            $robot->robotItems()
                ->whereHas('item', fn ($query) => $query->where('type', $type))
                ->update(['equipped' => false]);
            $robotItem->update(['equipped' => true]);
        });

        return back()->with('success', 'Предмет экипирован.');
    }

    public function unequip(RobotItem $robotItem): RedirectResponse
    {
        $robot = auth()->user()->robot;
        abort_unless($robotItem->robot_id === $robot->id, 403);
        $robotItem->update(['equipped' => false]);

        return back()->with('success', 'Предмет снят.');
    }
}
