<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketController extends Controller
{
    public function index(): View
    {
        $robot = auth()->user()->robot;

        $inventory = $robot->inventories()
        ->with('resource')
        ->where('amount', '>', 0)
        ->get();

        return view('market.index', [
            'robot' => $robot,
            'inventory' => $inventory,
        ]);
    }

    public function sell(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $robot = auth()->user()->robot;

        $inventoryItem = $robot->inventories()
            ->with('resource')
            ->where('id', $validated['inventory_id'])
            ->firstOrFail();

        if ($validated['amount'] > $inventoryItem->amount) {
            return redirect()
                ->route('market.index')
                ->with('error', 'На складе нет такого количества ресурса.');
        }

        $earnedScraps = $validated['amount'] * $inventoryItem->resource->scrap_value;

        $robot->increment('scraps', $earnedScraps);

        $robot->gainLogisticsXp($earnedScraps);

        $inventoryItem->decrement('amount', $validated['amount']);

        if ($inventoryItem->fresh()->amount <= 0) {
            $inventoryItem->delete();
        }

        return redirect()
            ->route('market.index')
            ->with('success', "Получено {$earnedScraps} скрапов. Логист получил {$earnedScraps} XP.");
    }

    public function sellAll(): RedirectResponse
    {
        $robot = auth()->user()->robot;

        $inventoryItems = $robot->inventories()
            ->with('resource')
            ->where('amount', '>', 0)
            ->get();

        if ($inventoryItems->isEmpty()) {
            return redirect()
                ->route('market.index')
                ->with('error', 'На складе нет ресурсов.');
        }

        $totalScraps = 0;

        foreach ($inventoryItems as $item) {
            $totalScraps +=
                $item->amount * $item->resource->scrap_value;
        }

        $robot->increment('scraps', $totalScraps);

        $robot->gainLogisticsXp($totalScraps);

        $robot->inventories()->delete();

        return redirect()
            ->route('market.index')
            ->with(
                'success',
                "Все ресурсы сданы. Получено {$totalScraps} скрапов. Логист получил {$totalScraps} XP."
            );
    }
}