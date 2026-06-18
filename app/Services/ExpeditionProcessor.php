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
            $this->lootGenerator->generate($expedition);

            $expedition->update([
                'status' => 'completed',
            ]);
        }
    }

    public function __construct(
        private ExpeditionLootGenerator $lootGenerator
    ) {}
}