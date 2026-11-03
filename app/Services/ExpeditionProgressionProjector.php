<?php

namespace App\Services;

class ExpeditionProgressionProjector
{
    public function __construct(private ExpeditionBalanceSimulator $simulator) {}

    public function project(int $targetLevel = 20, int $runs = 10000, int $seed = 42, string $strategy = 'xp_per_minute'): array
    {
        if ($targetLevel < 2 || $targetLevel > 100 || $runs < 1) {
            throw new \InvalidArgumentException('Target level must be 2..100 and runs must be positive.');
        }
        if (! in_array($strategy, ['xp_per_minute', 'highest_unlocked'], true)) {
            throw new \InvalidArgumentException('Unknown route selection strategy.');
        }

        $routes = [];
        foreach (config('locations') as $slug => $location) {
            $stats = $this->simulator->simulate($slug, $runs, $seed, [
                'ssd' => 32, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0,
            ]);
            $routes[$slug] = [
                'required_level' => (int) $location['required_level'],
                'duration_minutes' => (int) $location['base_duration_minutes'],
                'battery_cost' => $stats['effective_battery_cost'],
                'xp_per_run' => $stats['xp_per_run'],
                'integrity_loss_per_run' => $stats['integrity_loss_per_run'],
                'abort_percent' => $stats['abort_percent'],
            ];
        }

        $milestones = [];
        $totalMinutes = 0.0;
        $totalRuns = 0.0;
        $totalBattery = 0.0;
        $totalDamage = 0.0;
        for ($level = 1; $level < $targetLevel; $level++) {
            $threshold = 100 + (($level - 1) * 50) + (($level - 1) ** 2 * 25);
            $choices = array_filter($routes, static fn (array $route) => $route['required_level'] <= $level && $route['xp_per_run'] > 0);
            if ($choices === []) {
                throw new \RuntimeException('No eligible XP-producing routes for level '.$level);
            }
            $selected = null;
            $best = -1.0;
            foreach ($choices as $slug => $route) {
                $score = $strategy === 'highest_unlocked'
                    ? $route['required_level'] * 1000000 + $route['xp_per_run'] / $route['duration_minutes']
                    : $route['xp_per_run'] / $route['duration_minutes'];
                if ($score > $best) {
                    $best = $score;
                    $selected = $slug;
                }
            }
            $route = $routes[$selected];
            // Fractional expected runs: not a literal gameplay session or recharge simulation.
            $expectedRuns = $threshold / $route['xp_per_run'];
            $totalRuns += $expectedRuns;
            $totalMinutes += $expectedRuns * $route['duration_minutes'];
            $totalBattery += $expectedRuns * $route['battery_cost'];
            $totalDamage += $expectedRuns * $route['integrity_loss_per_run'];
            $milestones[] = [
                'level' => $level + 1,
                'route' => $selected,
                'xp_required' => $threshold,
                'expected_runs' => round($expectedRuns, 2),
                'cumulative_expedition_minutes' => round($totalMinutes, 2),
                'cumulative_battery_spent' => round($totalBattery, 2),
                'cumulative_integrity_damage' => round($totalDamage, 2),
            ];
        }

        return [
            'parameters' => ['target_level' => $targetLevel, 'runs_per_route' => $runs, 'seed' => $seed, 'strategy' => $strategy],
            'limitations' => 'Expected-value projection only. Assumes XP begins at zero at level 1 and route rewards remain stationary. Excludes battery recharge/wait, repair costs, inventory, equipment, RAM duration modifiers and actual run-by-run randomness. Battery is gross configured cost; event battery deltas are excluded.',
            'milestones' => $milestones,
        ];
    }
}
