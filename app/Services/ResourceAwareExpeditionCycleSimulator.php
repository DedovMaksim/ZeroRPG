<?php

namespace App\Services;

/**
 * Opt-in deterministic progression scenario with physical inventory.
 * Route outcomes remain Monte Carlo averages, NOT event-by-event outcomes.
 */
class ResourceAwareExpeditionCycleSimulator
{
    public function __construct(
        private ExpeditionBalanceSimulator $balance,
        private ExpeditionResourcePolicy $policy,
    ) {}

    public function simulate(
        int $targetLevel = 20,
        int $runs = 10000,
        int $seed = 42,
        string $strategy = 'xp_per_cycle_minute',
        int $maxExpeditions = 100000
    ): array {
        if ($targetLevel < 2 || $targetLevel > 100 || $runs < 1 || $maxExpeditions < 1) {
            throw new \InvalidArgumentException('Invalid simulation parameters.');
        }
        if (! in_array($strategy, ['xp_per_minute', 'highest_unlocked', 'xp_per_cycle_minute'], true)) {
            throw new \InvalidArgumentException('Unknown strategy.');
        }

        $routes = [];
        foreach (config('locations') as $slug => $location) {
            $stats = $this->balance->simulate($slug, $runs, $seed, [
                'ssd' => 32, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0,
            ]);
            $loot = [];
            foreach ($stats['carried_loot_per_run'] as $name => $amount) {
                foreach (config('resources') as $resourceSlug => $definition) {
                    if ($definition['name'] === $name) {
                        $loot[$resourceSlug] = (float) $amount;
                        break;
                    }
                }
            }
            $routes[$slug] = [
                'required_level' => (int) $location['required_level'],
                'duration' => (float) $location['base_duration_minutes'],
                'battery_cost' => (int) $stats['effective_battery_cost'],
                'xp' => (float) $stats['xp_per_run'],
                'damage' => (float) $stats['integrity_loss_per_run'],
                'scrap_metal' => (float) ($loot['scrap_metal'] ?? 0),
                'loot' => $loot,
            ];
        }

        $inventory = new ExpeditionInventoryLedger(32);
        $fractions = [];
        $level = 1;
        $xp = 0.0;
        $battery = 100.0;
        $integrity = 100.0;
        $elapsed = 0.0;
        $waiting = 0.0;
        $expeditionMinutes = 0.0;
        $repairs = 0;
        $expeditions = 0;
        $farmingExpeditions = 0;
        $unloads = 0;
        $lostLoot = 0;
        $history = [];
        $routeUsage = [];
        $stopped = null;
        $repairCost = (int) config('repair.cost', 5);
        $repairRecovery = (int) config('repair.integrity_per_repair', 10);
        $repairResource = (string) config('repair.resource', 'scrap_metal');
        $chargePerMinute = 4.0;
        if ($repairCost <= 0 || $repairRecovery <= 0) {
            throw new \RuntimeException('Invalid repair configuration.');
        }

        while ($level < $targetLevel && $expeditions < $maxExpeditions) {
            $maxBattery = 100 + ($level - 1) * 5;
            $maxIntegrity = 100 + ($level - 1) * 5;
            $choices = array_filter($routes, static fn (array $route) => $route['required_level'] <= $level
                && $route['battery_cost'] <= $maxBattery && $route['xp'] > 0);
            if ($choices === []) {
                $stopped = 'no_battery_feasible_route';
                break;
            }

            $chosen = null;
            $best = -INF;
            foreach ($choices as $slug => $route) {
                $cycle = max($route['duration'], $route['battery_cost'] / $chargePerMinute);
                $score = match ($strategy) {
                    'highest_unlocked' => $route['required_level'] * 1000000 + $route['xp'] / $cycle,
                    'xp_per_cycle_minute' => $route['xp'] / $cycle,
                    default => $route['xp'] / $route['duration'],
                };
                if ($score > $best) {
                    $best = $score;
                    $chosen = $slug;
                }
            }

            // If repair stock is low, farm physical scrap metal rather than blindly
            // repeating an XP-optimized route that cannot sustain repairs.
            $farming = $this->policy->needsFarming($inventory->items()[$repairResource] ?? 0);
            if ($farming) {
                $farm = $this->policy->farmingRoute($choices, $level, $maxBattery);
                if ($farm !== null) {
                    $chosen = $farm;
                }
            }
            $route = $choices[$chosen];

            // Keep the robot alive when possible. If metal is missing, attempt a
            // metal-producing route only if its expected damage is survivable.
            while ($integrity < max(1.0, $route['damage']) && $integrity < $maxIntegrity) {
                if (! $inventory->consume($repairResource, $repairCost)) {
                    $stopped = 'insufficient_scrap_metal_for_repair';
                    break 2;
                }
                $integrity = min($maxIntegrity, $integrity + $repairRecovery);
                $repairs++;
            }

            // Unload all non-reserved resources between runs. This is an assumed
            // instantaneous offloading policy, not a simulated market transaction.
            $removed = $inventory->unload($this->policy);
            if ($removed !== []) {
                $unloads++;
            }

            if ($battery + 1e-9 < $route['battery_cost']) {
                $minutes = ($route['battery_cost'] - $battery) / $chargePerMinute;
                $waiting += $minutes;
                $elapsed += $minutes;
                $battery = (float) $route['battery_cost'];
            }
            $battery -= $route['battery_cost'];
            $elapsed += $route['duration'];
            $expeditionMinutes += $route['duration'];
            $battery = min($maxBattery, $battery + $route['duration'] * $chargePerMinute);
            $integrity = max(0.0, $integrity - $route['damage']);

            foreach ($route['loot'] as $slug => $average) {
                $fractions[$slug] = ($fractions[$slug] ?? 0.0) + $average;
                $units = (int) floor($fractions[$slug] + 1e-9);
                $fractions[$slug] -= $units;
                if ($units > 0) {
                    $lostLoot += $units - $inventory->add($slug, $units);
                }
            }
            $xp += $route['xp'];
            $expeditions++;
            $purpose = $farming ? 'farming' : 'progression';
            $routeUsage[$chosen] ??= ['total' => 0, 'progression' => 0, 'farming' => 0];
            $routeUsage[$chosen]['total']++;
            $routeUsage[$chosen][$purpose]++;
            if ($farming) {
                $farmingExpeditions++;
            }

            while ($level < $targetLevel) {
                $threshold = 100 + ($level - 1) * 50 + ($level - 1) ** 2 * 25;
                if ($xp + 1e-9 < $threshold) {
                    break;
                }
                $xp -= $threshold;
                $level++;
                $battery += 5;
                $integrity += 5;
                $history[] = [
                    'level' => $level,
                    'route' => $chosen,
                    'expeditions' => $expeditions,
                    'repairs' => $repairs,
                    'farming_expeditions' => $farmingExpeditions,
                    'elapsed_minutes' => round($elapsed, 2),
                    'waiting_minutes' => round($waiting, 2),
                    'integrity' => round($integrity, 2),
                    'scrap_metal' => $inventory->items()[$repairResource] ?? 0,
                ];
            }
        }

        if ($level < $targetLevel && $stopped === null) {
            $stopped = 'expedition_limit';
        }

        ksort($routeUsage);

        return [
            'route_usage' => $routeUsage,
            'parameters' => compact('targetLevel', 'runs', 'seed', 'strategy', 'maxExpeditions'),
            'result' => [
                'reached_level' => $level,
                'remaining_xp' => round($xp, 3),
                'expeditions' => $expeditions,
                'elapsed_minutes' => round($elapsed, 2),
                'expedition_minutes' => round($expeditionMinutes, 2),
                'waiting_minutes' => round($waiting, 2),
                'repairs' => $repairs,
                'remaining_battery' => round($battery, 2),
                'remaining_integrity' => round($integrity, 2),
                'remaining_scrap_metal' => $inventory->items()[$repairResource] ?? 0,
                'ssd_used' => $inventory->usedStorage(),
                'unloads' => $unloads,
                'farming_expeditions' => $farmingExpeditions,
                'lost_loot_units' => $lostLoot,
                'stop_reason' => $stopped,
            ],
            'inventory' => $inventory->items(),
            'milestones' => $history,
            'limitations' => 'Opt-in deterministic scenario using fractional route averages accumulated into integer loot. Not event-by-event Monte Carlo. Assumes instant free unloading, active repair bay, fixed SSD=32, no upgrades or warehouse/market currency, average damage/XP, charging during expeditions. Farming route is chosen by average scrap per minute and is not guaranteed to prevent a stop.',
        ];
    }
}
