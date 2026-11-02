<?php

namespace App\Services;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Isolated CPU loot-selection experiment. Does not affect live expeditions.
 * Models route loot only; event loot and inventory already in storage are excluded.
 */
class CpuLootExperiment
{
    public function compare(string $slug, int $runs = 10000, int $seed = 42): array
    {
        $location = config('locations.'.$slug);
        if (! is_array($location) || $runs < 1) {
            throw new \InvalidArgumentException('Invalid location or run count.');
        }

        $resources = [];
        foreach (config('resources', []) as $resource) {
            $resources[$resource['name']] = $resource;
        }

        $rng = new Randomizer(new Mt19937($seed));
        $rows = [];
        foreach ([1, 5, 10, 15, 20] as $level) {
            $rows[$level] = [
                'level' => $level, 'cpu' => $level, 'ssd' => 8 + $level - 1,
                'base' => 0.0, 'cpu_module' => 0.0, 'ssd_module' => 0.0,
                'cpu_optimized' => 0, 'cpu_opportunities' => 0,
            ];
        }

        for ($i = 0; $i < $runs; $i++) {
            $found = [];
            foreach ($location['loot'] as $entry) {
                if ($rng->getInt(1, 100) <= $entry['chance']) {
                    $found[$entry['resource']] = $rng->getInt($entry['min'], $entry['max']);
                }
            }
            if ($found === []) {
                $entry = $location['loot'][$rng->getInt(0, count($location['loot']) - 1)];
                $found[$entry['resource']] = $rng->getInt($entry['min'], $entry['max']);
            }

            foreach ($rows as $level => &$row) {
                $base = $this->greedy($found, $resources, $row['ssd']);
                $best = $this->optimal($found, $resources, $row['ssd']);
                $baseCpu = $this->value($base, $resources);
                $bestCpu = $this->value($best, $resources);
                $chance = fn (int $cpu): int => min(95, 10 + 4 * ($cpu - 1));
                // Shared roll makes comparisons paired and reproducible.
                $roll = $rng->getInt(1, 100);
                $withBaseCpu = $roll <= $chance($row['cpu']) ? $bestCpu : $baseCpu;
                $withModuleCpu = $roll <= $chance($row['cpu'] + 1) ? $bestCpu : $baseCpu;
                $withSsd = $this->greedy($found, $resources, $row['ssd'] + 8);
                $withSsdBest = $this->optimal($found, $resources, $row['ssd'] + 8);
                $withSsdValue = $roll <= $chance($row['cpu'])
                    ? $this->value($withSsdBest, $resources)
                    : $this->value($withSsd, $resources);
                $row['base'] += $withBaseCpu;
                $row['cpu_module'] += $withModuleCpu;
                $row['ssd_module'] += $withSsdValue;
                if ($bestCpu > $baseCpu) {
                    $row['cpu_opportunities']++;
                    if ($roll > $chance($row['cpu']) && $roll <= $chance($row['cpu'] + 1)) {
                        $row['cpu_optimized']++;
                    }
                }
            }
            unset($row);
        }

        foreach ($rows as &$row) {
            foreach (['base', 'cpu_module', 'ssd_module'] as $field) {
                $row[$field] = round($row[$field] / $runs, 4);
            }
            $row['cpu_gain'] = round($row['cpu_module'] - $row['base'], 4);
            $row['ssd_gain'] = round($row['ssd_module'] - $row['base'], 4);
            $row['cpu_payback'] = $row['cpu_gain'] > 0 ? (int) ceil(25 / $row['cpu_gain']) : null;
        }
        unset($row);

        return array_values($rows);
    }

    private function greedy(array $found, array $resources, int $capacity): array
    {
        $taken = [];
        foreach ($found as $name => $amount) {
            $size = (int) $resources[$name]['storage_size'];
            $count = min($amount, intdiv($capacity, $size));
            $taken[$name] = $count;
            $capacity -= $count * $size;
        }
        return $taken;
    }

    private function optimal(array $found, array $resources, int $capacity): array
    {
        $dp = array_fill(0, $capacity + 1, ['value' => 0, 'taken' => []]);
        foreach ($found as $name => $amount) {
            $size = (int) $resources[$name]['storage_size'];
            $value = (int) $resources[$name]['scrap_value'];
            for ($unit = 0; $unit < $amount; $unit++) {
                for ($c = $capacity; $c >= $size; $c--) {
                    $candidate = $dp[$c - $size]['value'] + $value;
                    if ($candidate > $dp[$c]['value']) {
                        $taken = $dp[$c - $size]['taken'];
                        $taken[$name] = ($taken[$name] ?? 0) + 1;
                        $dp[$c] = ['value' => $candidate, 'taken' => $taken];
                    }
                }
            }
        }
        return $dp[$capacity]['taken'];
    }

    private function value(array $taken, array $resources): int
    {
        $total = 0;
        foreach ($taken as $name => $amount) {
            $total += $amount * $resources[$name]['scrap_value'];
        }
        return $total;
    }
}
