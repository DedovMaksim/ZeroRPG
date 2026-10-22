<?php

namespace App\Services;

use Random\Engine\Mt19937;
use Random\Randomizer;

class ExpeditionBalanceSimulator
{
    public function simulate(string $slug, int $runs = 10000, int $seed = 42): array
    {
        $location = config('locations.'.$slug);
        if (! is_array($location)) {
            throw new \InvalidArgumentException('Unknown location: '.$slug);
        }
        if ($runs < 1) {
            throw new \InvalidArgumentException('Runs must be positive.');
        }

        $rng = new Randomizer(new Mt19937($seed));
        $difficulty = (int) $location['difficulty'];
        $catalog = app(ExpeditionEventCatalog::class);
        $totals = [
            'runs' => $runs, 'aborted' => 0, 'events' => 0,
            'xp' => 0, 'battery_delta' => 0, 'integrity_loss' => 0,
            'loot' => [], 'event_counts' => [],
        ];

        for ($i = 0; $i < $runs; $i++) {
            $pool = $catalog->eligible($slug, $difficulty);
            $aborted = false;

            for ($slot = 0; $slot < $catalog->maxEvents($difficulty); $slot++) {
                if ($rng->getInt(1, 100) > $catalog->triggerChance($difficulty)) {
                    continue;
                }

                $key = $this->pickEvent($pool, $rng);
                if ($key === null) {
                    continue;
                }

                $event = $pool[$key];
                if (! config('expedition_event_rules.allow_duplicate_event_keys', false)) {
                    unset($pool[$key]);
                }

                $totals['events']++;
                $totals['event_counts'][$key] = ($totals['event_counts'][$key] ?? 0) + 1;
                $effect = $event['effect'];
                $type = $effect['type'];

                if ($type === 'critical_battery') {
                    $aborted = true;
                    $totals['aborted']++;
                    break;
                }

                if (in_array($type, ['battery', 'integrity', 'xp', 'resource'], true)) {
                    $amount = $rng->getInt((int) $effect['min'], (int) $effect['max']);
                    if ($type === 'battery') {
                        $totals['battery_delta'] += $amount;
                    } elseif ($type === 'integrity') {
                        $totals['integrity_loss'] += max(0, -$amount);
                    } elseif ($type === 'xp') {
                        $totals['xp'] += $amount;
                    } else {
                        $resource = $effect['resource'];
                        $totals['loot'][$resource] = ($totals['loot'][$resource] ?? 0) + $amount;
                    }
                }
            }

            if ($aborted) {
                continue;
            }

            $totals['xp'] += $rng->getInt((int) $location['xp']['min'], (int) $location['xp']['max']);
            $loot = [];
            foreach ($location['loot'] as $item) {
                if ($rng->getInt(1, 100) <= (int) $item['chance']) {
                    $loot[$item['resource']] = ($loot[$item['resource']] ?? 0)
                        + $rng->getInt((int) $item['min'], (int) $item['max']);
                }
            }

            if ($loot === []) {
                $item = $location['loot'][$rng->getInt(0, count($location['loot']) - 1)];
                $loot[$item['resource']] = $rng->getInt((int) $item['min'], (int) $item['max']);
            }

            foreach ($loot as $resource => $amount) {
                $totals['loot'][$resource] = ($totals['loot'][$resource] ?? 0) + $amount;
            }
        }

        // Route loot uses display names, while event loot uses resource slugs.
        // Merge both under the canonical resource name for the report.
        $resourceNames = [];
        foreach (config('resources', []) as $slug => $resource) {
            $resourceNames[$slug] = $resource['name'];
        }
        $normalizedLoot = [];
        foreach ($totals['loot'] as $resource => $amount) {
            $name = $resourceNames[$resource] ?? $resource;
            $normalizedLoot[$name] = ($normalizedLoot[$name] ?? 0) + $amount;
        }
        $totals['loot'] = $normalizedLoot;

        ksort($totals['loot']);
        ksort($totals['event_counts']);
        $averages = [];
        foreach ($totals['loot'] as $resource => $amount) {
            $averages[$resource] = round($amount / $runs, 3);
        }

        return [
            'location' => $slug,
            'runs' => $runs,
            'seed' => $seed,
            'abort_percent' => round(100 * $totals['aborted'] / $runs, 3),
            'events_per_run' => round($totals['events'] / $runs, 3),
            'xp_per_run' => round($totals['xp'] / $runs, 3),
            'integrity_loss_per_run' => round($totals['integrity_loss'] / $runs, 3),
            'battery_cost' => (int) $location['battery_cost'],
            'event_battery_delta_per_run' => round($totals['battery_delta'] / $runs, 3),
            'loot_per_run' => $averages,
            'event_counts' => $totals['event_counts'],
        ];
    }

    private function pickEvent(array $pool, Randomizer $rng): ?string
    {
        $groups = [];
        foreach ($pool as $key => $event) {
            if (($event['weight'] ?? 0) > 0) {
                $groups[$event['category'] ?? 'neutral'][$key] = $event;
            }
        }

        $weights = [];
        foreach ($groups as $category => $events) {
            $weight = (int) config('expedition_event_rules.category_weights.'.$category, 0);
            if ($weight > 0) {
                $weights[$category] = $weight;
            }
        }

        if ($weights === []) {
            return null;
        }

        $roll = $rng->getInt(1, array_sum($weights));
        foreach ($weights as $category => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                $events = $groups[$category];
                $roll = $rng->getInt(1, array_sum(array_column($events, 'weight')));
                foreach ($events as $key => $event) {
                    $roll -= (int) $event['weight'];
                    if ($roll <= 0) {
                        return (string) $key;
                    }
                }
            }
        }

        return null;
    }
}
