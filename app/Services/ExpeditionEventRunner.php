<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionLog;

class ExpeditionEventRunner
{
    public function __construct(
        private ExpeditionEventCatalog $catalog,
        private ExpeditionEventApplier $applier
    ) {}

    public function run(Expedition $expedition): bool
    {
        $difficulty = (int) $expedition->location->difficulty;
        $pool = $this->catalog->eligible($expedition->location->slug, $difficulty);
        $slots = max(0, $this->catalog->maxEvents($difficulty));
        $seconds = max(1, (int) $expedition->started_at->diffInSeconds($expedition->finished_at));
        $slots = min($slots, max(1, $seconds - 1));

        for ($slot = 1; $slot <= $slots && $pool !== []; $slot++) {
            if (random_int(1, 100) > $this->catalog->triggerChance($difficulty)) {
                continue;
            }

            $roll = random_int(1, array_sum(array_column($pool, 'weight')));
            $key = null;

            foreach ($pool as $candidate => $event) {
                $roll -= $event['weight'];
                if ($roll <= 0) {
                    $key = $candidate;
                    break;
                }
            }

            if ($key === null) {
                continue;
            }

            $event = $pool[$key];
            if (! config('expedition_event_rules.allow_duplicate_event_keys', false)) {
                unset($pool[$key]);
            }

            $offset = (int) floor($slot * $seconds / ($slots + 1));
            $time = $expedition->started_at->copy()->addSeconds($offset);
            $texts = config('expedition_event_texts.'.$key, []);
            $description = $texts !== [] ? $texts[array_rand($texts)] : $event['message'];
            $result = $this->applier->apply($expedition, $key);
            $critical = ($event['effect']['type'] ?? '') === 'critical_battery';

            ExpeditionLog::create([
                'expedition_id' => $expedition->id,
                'minute' => (int) floor($offset / 60),
                'event_type' => $critical ? 'critical_event' : 'random_event',
                'message' => $description.' '.$result,
                'event_time' => $time,
            ]);

            if ($critical) {
                $expedition->logs()->where('event_time', '>', $time)->delete();
                $expedition->update([
                    'finished_at' => $time,
                    'duration_minutes' => (int) ceil($offset / 60),
                ]);

                ExpeditionLog::create([
                    'expedition_id' => $expedition->id,
                    'minute' => (int) floor($offset / 60),
                    'event_type' => 'emergency_return',
                    'message' => 'Экспедиция прервана. Zero эвакуирован на базу без маршрутных наград.',
                    'event_time' => $time,
                ]);

                return true;
            }
        }

        return false;
    }
}
