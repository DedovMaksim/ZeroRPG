<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionLog;
use App\Models\Inventory;
use App\Models\Resource;

class ExpeditionLootGenerator
{
    public function generate(Expedition $expedition): array
    {
        $lootTable = $this->getLootTable($expedition->location->slug);

        $generatedLoot = [];
        $reportLoot = [];

        foreach ($lootTable as $lootItem) {
            if (rand(1, 100) <= $lootItem['chance']) {
                $cpuBonus = min(5, floor($expedition->robot->cpu / 10));

                $generatedLoot[] = [
                    'resource' => $lootItem['resource'],
                    'amount' => rand($lootItem['min'], $lootItem['max']) + $cpuBonus,
                ];
            }
        }

        if (empty($generatedLoot)) {
            $guaranteedItem = $lootTable[array_rand($lootTable)];

            $cpuBonus = min(5, floor($expedition->robot->cpu / 10));

            $generatedLoot[] = [
                'resource' => $guaranteedItem['resource'],
                'amount' => rand($guaranteedItem['min'], $guaranteedItem['max']) + $cpuBonus,
            ];
        }

        $robot = $expedition->robot;

        $usedStorage = $robot->inventories()
            ->with('resource')
            ->get()
            ->sum(function ($inventoryItem) {
                return $inventoryItem->amount
                    * $inventoryItem->resource->storage_size;
            });

        $freeStorage = $robot->maxSsd() - $usedStorage;

        foreach ($generatedLoot as $item) {
            $resource = Resource::where('name', $item['resource'])->first();

            if (! $resource) {
                continue;
            }

            $inventory = Inventory::firstOrCreate(
                [
                    'robot_id' => $expedition->robot_id,
                    'resource_id' => $resource->id,
                ],
                [
                    'amount' => 0,
                ]
            );

            $requiredStorage = $item['amount'] * $resource->storage_size;

            if ($freeStorage <= 0) {
                ExpeditionLog::create([
                    'expedition_id' => $expedition->id,
                    'minute' => $expedition->duration_minutes,
                    'event_type' => 'storage_full',
                    'message' => 'Склад заполнен. Робот не смог унести: '
                        . $resource->name,
                    'event_time' => $expedition->finished_at,
                ]);

                continue;
            }

            if ($requiredStorage > $freeStorage) {
                $canTake = floor($freeStorage / $resource->storage_size);

                if ($canTake <= 0) {
                    ExpeditionLog::create([
                        'expedition_id' => $expedition->id,
                        'minute' => $expedition->duration_minutes,
                        'event_type' => 'storage_full',
                        'message' => 'Недостаточно места для ресурса: '
                            . $resource->name,
                        'event_time' => $expedition->finished_at,
                    ]);

                    continue;
                }

                $inventory->increment('amount', $canTake);
                $freeStorage -= $canTake * $resource->storage_size;

                $reportLoot[] = [
                    'resource' => $resource->name,
                    'amount' => $canTake,
                ];

                ExpeditionLog::create([
                    'expedition_id' => $expedition->id,
                    'minute' => $expedition->duration_minutes,
                    'event_type' => 'loot_partial',
                    'message' => 'Получено: '
                        . $resource->name
                        . ' +'
                        . $canTake
                        . '. Остальное не поместилось на складе.',
                    'event_time' => $expedition->finished_at,
                ]);

                continue;
            }

            $inventory->increment('amount', $item['amount']);
            $freeStorage -= $requiredStorage;

            $reportLoot[] = [
                'resource' => $resource->name,
                'amount' => $item['amount'],
            ];

            ExpeditionLog::create([
                'expedition_id' => $expedition->id,
                'minute' => $expedition->duration_minutes,
                'event_type' => 'loot',
                'message' => 'Получено: '
                    . $resource->name
                    . ' +'
                    . $item['amount'],
                'event_time' => $expedition->finished_at,
            ]);
        }

        return $reportLoot;
    }

    private function getLootTable(string $locationSlug): array
    {
        return config("locations.{$locationSlug}.loot", [
            [
                'resource' => 'Металлолом',
                'chance' => 100,
                'min' => 1,
                'max' => 3,
            ],
        ]);
    }
}