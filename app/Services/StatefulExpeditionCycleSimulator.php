<?php

namespace App\Services;

/**
 * Deterministic, stateful run-by-run approximation using Monte Carlo route averages.
 * Not a replacement for ExpeditionEventRunner/ExpeditionLootGenerator.
 */
class StatefulExpeditionCycleSimulator
{
    public function __construct(private ExpeditionBalanceSimulator $balance) {}

    public function simulate(int $targetLevel = 20, int $runs = 10000, int $seed = 42, string $strategy = 'xp_per_cycle_minute', int $maxExpeditions = 100000): array
    {
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
            $routes[$slug] = [
                'required_level' => (int) $location['required_level'],
                'duration' => (float) $location['base_duration_minutes'],
                'battery_cost' => (int) $stats['effective_battery_cost'],
                'xp' => (float) $stats['xp_per_run'],
                'damage' => (float) $stats['integrity_loss_per_run'],
                'scrap_metal' => (float) ($stats['carried_loot_per_run'][config('resources.scrap_metal.name', 'Металлолом')] ?? 0),
                'abort_percent' => (float) $stats['abort_percent'],
            ];
        }

        $level = 1;
        $xp = 0.0;
        $battery = 100.0;
        $integrity = 100.0;
        $scrapMetal = 0.0;
        $elapsed = 0.0;
        $waiting = 0.0;
        $expeditionMinutes = 0.0;
        $repairs = 0;
        $expeditions = 0;
        $history = [];
        $stopped = null;
        $repairCost = (int) config('repair.cost', 5);
        $repairRecovery = (int) config('repair.integrity_per_repair', 10);
        $chargePerMinute = 4.0; // BatteryProcessor: 100 / 25 minutes at base charge rate.
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
                    $chosen = $slug;
                    $best = $score;
                }
            }
            $route = $choices[$chosen];

            // Repair is available only when a repair bay is active; this scenario assumes it is built.
            // Do not allow a run that would exhaust expected integrity without repairing first.
            while ($integrity < max(1.0, $route['damage']) && $integrity < $maxIntegrity) {
                if ($scrapMetal + 1e-9 < $repairCost) {
                    $stopped = 'insufficient_scrap_metal_for_repair';
                    break 2;
                }
                $scrapMetal -= $repairCost;
                $integrity = min($maxIntegrity, $integrity + $repairRecovery);
                $repairs++;
            }

            if ($battery + 1e-9 < $route['battery_cost']) {
                $minutes = ($route['battery_cost'] - $battery) / $chargePerMinute;
                $waiting += $minutes;
                $elapsed += $minutes;
                $battery = (float) $route['battery_cost'];
            }

            // The expedition consumes energy at departure. Charging resumes immediately,
            // and continues while the robot is away (BatteryProcessor).
            $battery -= $route['battery_cost'];
            $elapsed += $route['duration'];
            $expeditionMinutes += $route['duration'];
            $battery = min($maxBattery, $battery + $route['duration'] * $chargePerMinute);
            $integrity = max(0.0, $integrity - $route['damage']);
            $scrapMetal += $route['scrap_metal'];
            $xp += $route['xp'];
            $expeditions++;

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
                    'elapsed_minutes' => round($elapsed, 2),
                    'waiting_minutes' => round($waiting, 2),
                    'integrity' => round($integrity, 2),
                    'scrap_metal' => round($scrapMetal, 2),
                ];
            }
        }

        if ($level < $targetLevel && $stopped === null) {
            $stopped = 'expedition_limit';
        }

        return [
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
                'remaining_scrap_metal' => round($scrapMetal, 2),
                'stop_reason' => $stopped,
            ],
            'milestones' => $history,
            'limitations' => 'Stateful deterministic approximation, NOT event-by-event Monte Carlo. Uses fractional average XP, damage and scrap metal per run; aborts are folded into route averages rather than simulated individually. Assumes active repair bay, free instant repairs, no player upgrades/effects, constant SSD=32, base charging 4 units/minute even during expeditions, no battery event deltas, no event-specific critical return timing, no inventory constraints beyond Monte Carlo carried loot, and no market transactions. Repairs consume only scrap_metal, not sale value. Integrity repair is triggered before an expected-fatal run; exact game death/failure rules are not simulated.',
        ];
    }
}
