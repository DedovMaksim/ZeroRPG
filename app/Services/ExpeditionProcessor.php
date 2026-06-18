<?php

namespace App\Services;

use App\Models\Robot;
use App\Services\ExpeditionLootGenerator;

class ExpeditionProcessor
{
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

            $expedition->update([
                'status' => 'completed',
            ]);
        }
    }

    public function __construct(
        private ExpeditionLootGenerator $lootGenerator
    ) {}

    private function writeBatteryCostLog($expedition): void
    {
        \App\Models\ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => 'battery_cost',
            'message' => 'Потрачено энергии на маршрут: Battery -' . $expedition->location->battery_cost . '%',
            'event_time' => $expedition->finished_at,
        ]);
    }

    private function writeRandomEventLog($expedition): void
    {
        if (rand(1, 100) > 25) {
            return;
        }

        \App\Models\ExpeditionLog::create([
            'expedition_id' => $expedition->id,
            'minute' => $expedition->duration_minutes,
            'event_type' => 'random_event',
            'message' => 'Стая бродячих роботов заметила Zero. Пришлось удирать. Battery -5%',
            'event_time' => $expedition->finished_at,
        ]);
    }
}