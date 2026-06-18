<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Resource;
use App\Models\Robot;

class ExpeditionProcessor
{
    public function processCompletedExpeditions(Robot $robot): void
    {
        $expeditions = $robot->expeditions()
            ->where('status', 'in_progress')
            ->where('finished_at', '<=', now())
            ->get();

        foreach ($expeditions as $expedition) {
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

            $expedition->update([
                'status' => 'completed',
            ]);
        }
    }
}