<?php

namespace App\Services;

class ExpeditionEventCatalog
{
    public function eligible(string $locationSlug, int $difficulty): array
    {
        return array_filter(
            config('expedition_events', []),
            static fn (array $event): bool =>
                ($event['min_difficulty'] ?? 1) <= $difficulty
                && in_array($locationSlug, $event['locations'] ?? [], true)
                && ($event['weight'] ?? 0) > 0
        );
    }

    public function triggerChance(int $difficulty): int
    {
        return (int) config(
            'expedition_event_rules.trigger_chance_by_difficulty.'.max(1, min(5, $difficulty)),
            20
        );
    }

    public function maxEvents(int $difficulty): int
    {
        return (int) config(
            'expedition_event_rules.max_events_by_difficulty.'.max(1, min(5, $difficulty)),
            1
        );
    }
}
