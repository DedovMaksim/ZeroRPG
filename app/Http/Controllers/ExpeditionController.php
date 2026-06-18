<?php

namespace App\Http\Controllers;

use App\Models\Expedition;
use App\Models\Inventory;
use App\Models\Location;
use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ExpeditionController extends Controller
{
    public function start(Location $location): RedirectResponse
    {
        $user = Auth::user();
        $robot = $user->robot;

        Expedition::create([
            'robot_id' => $robot->id,
            'location_id' => $location->id,
            'status' => 'completed',
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $loot = [
            'metal_scrap' => 5,
            'copper' => 2,
        ];

        foreach ($loot as $resourceSlug => $amount) {
            $resource = Resource::where('slug', $resourceSlug)->firstOrFail();

            $inventory = Inventory::firstOrCreate(
                [
                    'robot_id' => $robot->id,
                    'resource_id' => $resource->id,
                ],
                [
                    'amount' => 0,
                ]
            );

            $inventory->increment('amount', $amount);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Экспедиция завершена. Робот вернулся с ресурсами.');
    }
}