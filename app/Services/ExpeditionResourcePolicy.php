<?php

namespace App\Services;

/**
 * Pure resource-management decisions for a future stateful expedition simulator.
 * No database writes or player actions are performed here.
 */
class ExpeditionResourcePolicy
{
    /**
     * Keep enough physical scrap metal for the requested number of repairs.
     */
    public function reserve(int $repairsAhead = 2): int
    {
        if ($repairsAhead < 0) {
            throw new \InvalidArgumentException('Repairs ahead must be non-negative.');
        }

        return $repairsAhead * (int) config('repair.cost', 5);
    }

    /**
     * Return how many units of each resource could be sold/deposited.
     * Scrap metal below the reserve is never released.
     *
     * @param array<string, int> $inventory Resource slugs => quantities
     * @return array<string, int>
     */
    public function releasableInventory(array $inventory, int $repairsAhead = 2): array
    {
        $reserve = $this->reserve($repairsAhead);
        $repairResource = (string) config('repair.resource', 'scrap_metal');
        $result = [];

        foreach ($inventory as $resource => $amount) {
            if ($amount < 0) {
                throw new \InvalidArgumentException('Inventory amounts must be non-negative.');
            }

            $release = $resource === $repairResource ? max(0, $amount - $reserve) : $amount;
            if ($release > 0) {
                $result[$resource] = $release;
            }
        }

        return $result;
    }

    /**
     * Choose the best accessible scrap-farming route when reserves are low.
     *
     * @param array<string, array{required_level:int,battery_cost:int,scrap_metal:float,duration:float}> $routes
     */
    public function farmingRoute(array $routes, int $level, int $maxBattery): ?string
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($routes as $slug => $route) {
            if ($route['required_level'] > $level || $route['battery_cost'] > $maxBattery || $route['duration'] <= 0) {
                continue;
            }

            $score = max(0.0, $route['scrap_metal']) / $route['duration'];
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $slug;
            }
        }

        return $best;
    }

    public function needsFarming(int $scrapMetal, int $repairsAhead = 2): bool
    {
        return $scrapMetal < $this->reserve($repairsAhead);
    }
}
