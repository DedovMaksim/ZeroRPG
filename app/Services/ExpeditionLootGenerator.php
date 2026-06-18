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

        foreach ($lootTable as $lootItem) {
            if (rand(1, 100) <= $lootItem['chance']) {
                $generatedLoot[] = [
                    'resource' => $lootItem['resource'],
                    'amount' => rand($lootItem['min'], $lootItem['max']),
                ];
            }
        }

        if (empty($generatedLoot)) {
            $guaranteedItem = $lootTable[array_rand($lootTable)];

            $generatedLoot[] = [
                'resource' => $guaranteedItem['resource'],
                'amount' => rand($guaranteedItem['min'], $guaranteedItem['max']),
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

        $freeStorage = $robot->ssd - $usedStorage;

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

            /*
            |--------------------------------------------------------------------------
            | Места нет вообще
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Влезает только часть добычи
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Всё помещается
            |--------------------------------------------------------------------------
            */

            $inventory->increment('amount', $item['amount']);

            $freeStorage -= $requiredStorage;

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

        return $generatedLoot;
    }

    private function getLootTable(string $locationSlug): array
    {
        return match ($locationSlug) {
            'drone_dump' => [
                [
                    'resource' => 'Металлолом',
                    'chance' => 95,
                    'min' => 3,
                    'max' => 7,
                ],
                [
                    'resource' => 'Медь',
                    'chance' => 35,
                    'min' => 1,
                    'max' => 3,
                ],
                [
                    'resource' => 'Электроника',
                    'chance' => 10,
                    'min' => 1,
                    'max' => 1,
                ],
            ],

            'abandoned_factory' => [
                [
                    'resource' => 'Металлолом',
                    'chance' => 65,
                    'min' => 2,
                    'max' => 5,
                ],
                [
                    'resource' => 'Медь',
                    'chance' => 75,
                    'min' => 2,
                    'max' => 5,
                ],
                [
                    'resource' => 'Электроника',
                    'chance' => 20,
                    'min' => 1,
                    'max' => 1,
                ],
            ],

            'old_substation' => [
                [
                    'resource' => 'Металлолом',
                    'chance' => 30,
                    'min' => 1,
                    'max' => 3,
                ],
                [
                    'resource' => 'Медь',
                    'chance' => 70,
                    'min' => 2,
                    'max' => 4,
                ],
                [
                    'resource' => 'Электроника',
                    'chance' => 55,
                    'min' => 1,
                    'max' => 2,
                ],
            ],

            default => [
                [
                    'resource' => 'Металлолом',
                    'chance' => 100,
                    'min' => 1,
                    'max' => 3,
                ],
            ],
        };
    }
}