<?php

namespace App\Services;

class ExpeditionCycleProjector
{
    public function __construct(private ExpeditionBalanceSimulator $simulator) {}

    /**
     * Scenario-based expected-value model. This is NOT a stateful game simulation.
     * Recharge and repair assumptions are explicit, not inferred from gameplay.
     */
    public function project(
        int $targetLevel = 20,
        int $runs = 10000,
        int $seed = 42,
        string $strategy = 'xp_per_minute',
        float $batteryPerMinute = 1.0,
        float $scrapsPerIntegrity = 1.0
    ): array {
        if ($targetLevel < 2 || $targetLevel > 100 || $runs < 1 || $batteryPerMinute <= 0 || $scrapsPerIntegrity < 0) {
            throw new \InvalidArgumentException('Invalid level, runs, recharge rate or repair cost.');
        }
        if (! in_array($strategy, ['xp_per_minute', 'highest_unlocked', 'xp_per_cycle_minute'], true)) {
            throw new \InvalidArgumentException('Unknown strategy.');
        }

        $routes = [];
        foreach (config('locations') as $slug => $location) {
            $stats = $this->simulator->simulate($slug, $runs, $seed, [
                'ssd' => 32, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0,
            ]);
            $routes[$slug] = [
                'required_level' => (int) $location['required_level'],
                'duration_minutes' => (int) $location['base_duration_minutes'],
                'battery_cost' => (float) $stats['effective_battery_cost'],
                'xp_per_run' => (float) $stats['xp_per_run'],
                'damage_per_run' => (float) $stats['integrity_loss_per_run'],
                'scraps_per_run' => (float) $stats['carried_value_per_run'],
                'abort_percent' => (float) $stats['abort_percent'],
            ];
        }

        $totalRuns = $expeditionMinutes = $rechargeMinutes = $batterySpent = $damage = $scrapsEarned = $repairCost = 0.0;
        $milestones = [];
        for ($level = 1; $level < $targetLevel; $level++) {
            $maxBattery = 100 + ($level - 1) * 5;
            $eligible = array_filter($routes, static fn (array $route) => $route['required_level'] <= $level
                && $route['battery_cost'] <= $maxBattery && $route['xp_per_run'] > 0);
            if ($eligible === []) {
                throw new \RuntimeException('No battery-feasible route at level '.$level);
            }

            $chosen = null;
            $best = -INF;
            foreach ($eligible as $slug => $route) {
                $cycleMinutes = $route['duration_minutes'] + $route['battery_cost'] / $batteryPerMinute;
                $score = match ($strategy) {
                    'highest_unlocked' => $route['required_level'] * 1000000 + $route['xp_per_run'] / $cycleMinutes,
                    'xp_per_cycle_minute' => $route['xp_per_run'] / $cycleMinutes,
                    default => $route['xp_per_run'] / $route['duration_minutes'],
                };
                if ($score > $best) {
                    $chosen = $slug;
                    $best = $score;
                }
            }

            $route = $eligible[$chosen];
            $threshold = 100 + ($level - 1) * 50 + ($level - 1) ** 2 * 25;
            $expectedRuns = $threshold / $route['xp_per_run'];
            $totalRuns += $expectedRuns;
            $expeditionMinutes += $expectedRuns * $route['duration_minutes'];
            $batterySpent += $expectedRuns * $route['battery_cost'];
            $damage += $expectedRuns * $route['damage_per_run'];
            $scrapsEarned += $expectedRuns * $route['scraps_per_run'];
            $repairCost = $damage * $scrapsPerIntegrity;
            // Long-run steady-state approximation: battery restored between runs.
            // Initial full battery and any charging during expeditions are ignored.
            $rechargeMinutes += $expectedRuns * $route['battery_cost'] / $batteryPerMinute;

            $milestones[] = [
                'level' => $level + 1,
                'route' => $chosen,
                'expected_runs' => round($expectedRuns, 2),
                'cumulative_expedition_minutes' => round($expeditionMinutes, 2),
                'cumulative_recharge_minutes' => round($rechargeMinutes, 2),
                'cumulative_cycle_minutes' => round($expeditionMinutes + $rechargeMinutes, 2),
                'cumulative_battery_spent' => round($batterySpent, 2),
                'cumulative_integrity_damage' => round($damage, 2),
                'cumulative_scraps_earned_if_sold' => round($scrapsEarned, 2),
                'cumulative_repair_scraps_needed' => round($repairCost, 2),
                'cumulative_scraps_surplus' => round($scrapsEarned - $repairCost, 2),
            ];
        }

        return [
            'parameters' => compact('targetLevel', 'runs', 'seed', 'strategy', 'batteryPerMinute', 'scrapsPerIntegrity'),
            'limitations' => 'Scenario model, not an exact gameplay simulation. Uses stationary Monte Carlo averages and fractional runs; resets XP at each level. Recharge rate and repair cost are supplied assumptions. Assumes selling all carried loot at configured scrap values, instantaneous selling/repair, no repairs during runs, no recharge during expeditions, no battery event deltas, no upgrades, no inventory carryover and no XP rollover. Repair deficit is a warning, not enforced bankruptcy.',
            'milestones' => $milestones,
        ];
    }
}
