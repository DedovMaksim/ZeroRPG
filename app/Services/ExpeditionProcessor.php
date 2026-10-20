<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionLog;
use App\Models\ExpeditionReport;
use App\Models\Robot;
use Illuminate\Support\Facades\DB;

class ExpeditionProcessor
{
    public function __construct(
        private ExpeditionLootGenerator $lootGenerator,
        private ExpeditionEventApplier $eventApplier,
        private ExpeditionEventCatalog $eventCatalog
    ) {}

    public function processCompletedExpeditions(Robot $robot): void
    {
        $expeditions = $robot->expeditions()
            ->where('status', 'in_progress')
            ->where('finished_at', '<=', now())
            ->get();

        foreach ($expeditions as $expedition) {
            DB::transaction(function () use ($expedition, $robot): void {
                $locked = Expedition::whereKey($expedition->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'in_progress') {
                    return;
                }

                $currentRobot = Robot::whereKey($robot->id)->lockForUpdate()->firstOrFail();
                $this->writeBatteryCostLog($locked);
                $critical = $this->applyRandomEvent($locked);
                $currentRobot->refresh();

                if ($critical) {
                    $locked->update(['status' => 'aborted']);
                    return;
                }

                $loot = $this->lootGenerator->generate($locked);
                $xp = $this->giveExperience($currentRobot, $locked);

                ExpeditionReport::create([
                    'robot_id' => $currentRobot->id,
                    'location_id' => $locked->location_id,
                    'location_name' => $locked->location->name,
                    'resources' => $loot,
                    'xp_gained' => $xp,
                    'started_at' => $locked->started_at,
                    'finished_at' => $locked->finished_at,
                ]);

                $locked->update(['status' => 'completed']);
            });
        }
    }

    private function giveExperience(Robot $robot, Expedition $expedition): int
    {
        $xpConfig = config(
            "locations.{$expedition->location->slug}.xp",
            [
                'min' => 1,
                'max' => 1,
            ]
        );

        $xp = random_int(
            $xpConfig['min'],
            $xpConfig['max']
        );

        $xp = max(0, (int) round($xp * (100 + $robot->stats()->xpPercent($robot)) / 100));

        $robot->xp += $xp;

        $this->checkLevelUp($robot, $expedition);

        $robot->save();

        $this->writeXpLog($expedition, $xp);

        return $xp;
    }

    private function checkLevelUp(Robot $robot, Expedition $expedition): void
    {
        while ($robot->xp >= $robot->xpForNextLevel()) {
            $robot->xp -= $robot->xpForNextLevel();

            $robot->level++;

            $this->writeLevelUpLog(
                $expedition,
                $robot->level
            );

            $robot->cpu += 1;
            $robot->ram = 4 * (2 ** ($robot->level - 1));
            $robot->ssd += 1;
            $robot->battery += 5;
            $robot->integrity += 5;
        }
    }

    private function writeLevelUpLog(Expedition $expedition, int $level): void
    {
        ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => 'level_up',
            'message' => '⚡ Получен уровень ' . $level .
                '. Все характеристики улучшены.',
            'event_time' => $expedition->finished_at,
        ]);
    }

    private function writeXpLog(Expedition $expedition, int $xp): void
    {
        ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => 'xp',
            'message' => 'Получено опыта: +' . $xp . ' XP',
            'event_time' => $expedition->finished_at,
        ]);
    }

    private function writeBatteryCostLog(Expedition $expedition): void
    {
        ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => 'battery_cost',
            'message' => 'Потрачено энергии на маршрут: Battery -' .
                ($expedition->battery_cost_paid ?? $expedition->location->battery_cost) . '%',
            'event_time' => $expedition->finished_at,
        ]);
    }

    private function applyRandomEvent(Expedition $expedition): bool
    {
        $difficulty = (int) $expedition->location->difficulty;

        if (random_int(1, 100) > $this->eventCatalog->triggerChance($difficulty)) {
            return false;
        }

        $eligible = $this->eventCatalog->eligible($expedition->location->slug, $difficulty);
        if ($eligible === []) {
            return false;
        }

        $totalWeight = array_sum(array_column($eligible, 'weight'));
        $roll = random_int(1, $totalWeight);
        $selectedKey = null;

        foreach ($eligible as $key => $event) {
            $roll -= (int) $event['weight'];

            if ($roll <= 0) {
                $selectedKey = $key;
                break;
            }
        }

        if ($selectedKey === null) {
            return false;
        }

        $variants = config('expedition_event_texts.'.$selectedKey, []);
        $description = $variants !== []
            ? $variants[array_rand($variants)]
            : config('expedition_events.'.$selectedKey.'.message');

        $consequence = $this->eventApplier->apply($expedition, $selectedKey);

        ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => ($eligible[$selectedKey]['effect']['type'] ?? '') === 'critical_battery' ? 'critical_event' : 'random_event',
            'message' => $description.' '.$consequence,
            'event_time' => $expedition->finished_at,
        ]);

        return ($eligible[$selectedKey]['effect']['type'] ?? '') === 'critical_battery';
    }
}