<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionLog;
use App\Models\Robot;

class ExpeditionProcessor
{
    public function __construct(
        private ExpeditionLootGenerator $lootGenerator
    ) {}

    public function processCompletedExpeditions(Robot $robot): void
    {
        $expeditions = $robot->expeditions()
            ->where('status', 'in_progress')
            ->where('finished_at', '<=', now())
            ->get();

        foreach ($expeditions as $expedition) {
            $this->writeBatteryCostLog($expedition);
            $this->writeRandomEventLog($expedition);

            $this->lootGenerator->generate($expedition);

            $this->giveExperience($robot, $expedition);

            $expedition->update([
                'status' => 'completed',
            ]);
        }
    }

    private function giveExperience(Robot $robot, Expedition $expedition): void
    {
        $xp = match ($expedition->location->slug) {
            'drone_dump' => random_int(1, 3),
            'abandoned_factory' => random_int(2, 4),
            'old_substation' => random_int(3, 5),
            default => 1,
        };

        $robot->xp += $xp;

        $this->checkLevelUp($robot, $expedition);

        $robot->save();

        $this->writeXpLog($expedition, $xp);
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
            'message' => 'Потрачено энергии на маршрут: Battery -' . $expedition->location->battery_cost . '%',
            'event_time' => $expedition->finished_at,
        ]);
    }

    private function writeRandomEventLog(Expedition $expedition): void
    {
        if (rand(1, 100) > 25) {
            return;
        }

        ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => 'random_event',
            'message' => 'Стая бродячих роботов заметила Zero. Пришлось удирать. Battery -5%',
            'event_time' => $expedition->finished_at,
        ]);
    }
}