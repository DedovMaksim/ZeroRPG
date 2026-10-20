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
        private ExpeditionEventRunner $eventRunner
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
                $critical = $this->eventRunner->run($locked);
                $currentRobot->refresh();

                if ($critical) {
                    $this->writeBatteryCostLog($locked);
                    $locked->update(['status' => 'aborted']);
                    return;
                }

                $this->writeBatteryCostLog($locked);
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

                ExpeditionLog::create([
                    'expedition_id' => $locked->id,
                    'minute' => $locked->duration_minutes,
                    'event_type' => 'finish',
                    'message' => 'Экспедиция завершена. Zero вернулся на базу.',
                    'event_time' => $locked->finished_at,
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
            'minute' => 0,
            'event_type' => 'battery_cost',
            'message' => 'Потрачено энергии на маршрут: Battery -' .
                ($expedition->battery_cost_paid ?? $expedition->location->battery_cost) . '%',
            'event_time' => $expedition->started_at,
        ]);
    }

}
