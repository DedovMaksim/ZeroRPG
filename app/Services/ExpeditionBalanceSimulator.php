<?php

namespace App\Services;

use Random\Engine\Mt19937;
use Random\Randomizer;

class ExpeditionBalanceSimulator
{
    public function simulate(string $slug, int $runs = 10000, int $seed = 42, ?array $profile = null): array
    {
        $location = config('locations.'.$slug);
        if (! is_array($location)) {
            throw new \InvalidArgumentException('Unknown location: '.$slug);
        }
        if ($runs < 1) {
            throw new \InvalidArgumentException('Runs must be positive.');
        }

        $profile ??= ['ssd' => 8, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0];
        $capacity = (int) ($profile['ssd'] ?? 8);
        $cpu = (int) ($profile['cpu'] ?? 1);
        $lootPercent = (int) ($profile['loot_percent'] ?? 0);
        $energyCostPercent = (int) ($profile['energy_cost_percent'] ?? 0);
        if ($capacity < 0 || $cpu < 0 || $lootPercent < -100 || $energyCostPercent < -100) {
            throw new \InvalidArgumentException('Invalid robot profile.');
        }
        $resourceConfig = config('resources', []);
        $resourceByName = [];
        foreach ($resourceConfig as $slugKey => $resourceData) {
            $resourceByName[$resourceData['name']] = $slugKey;
        }

        $rng = new Randomizer(new Mt19937($seed));
        $difficulty = (int) $location['difficulty'];
        $catalog = app(ExpeditionEventCatalog::class);
        $totals = [
            'runs' => $runs, 'aborted' => 0, 'events' => 0,
            'xp' => 0, 'battery_delta' => 0, 'integrity_loss' => 0,
            'loot' => [], 'event_counts' => [], 'carried' => [], 'found_value' => 0, 'carried_value' => 0, 'lost_units' => 0,
        ];

        for ($i = 0; $i < $runs; $i++) {
            $pool = $catalog->eligible($slug, $difficulty);
            $aborted = false;
            $freeStorage = $capacity;

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
                        $this->recordLoot($totals, $resource, $amount, $freeStorage, $resourceConfig, $resourceByName);
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
                    $baseAmount = $rng->getInt((int) $item['min'], (int) $item['max']);
                    $amount = max(0, (int) round(($baseAmount + min(5, intdiv($cpu, 10))) * (100 + $lootPercent) / 100));
                    $loot[$item['resource']] = ($loot[$item['resource']] ?? 0) + $amount;
                }
            }

            if ($loot === []) {
                $item = $location['loot'][$rng->getInt(0, count($location['loot']) - 1)];
                $baseAmount = $rng->getInt((int) $item['min'], (int) $item['max']);
                $loot[$item['resource']] = max(0, (int) round(($baseAmount + min(5, intdiv($cpu, 10))) * (100 + $lootPercent) / 100));
            }

            foreach ($loot as $resource => $amount) {
                $this->recordLoot($totals, $resource, $amount, $freeStorage, $resourceConfig, $resourceByName);
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

        ksort($totals['carried']);
        ksort($totals['loot']);
        ksort($totals['event_counts']);
        $averages = [];
        foreach ($totals['loot'] as $resource => $amount) {
            $averages[$resource] = round($amount / $runs, 3);
        }

        $carriedAverages = [];
        foreach ($totals['carried'] as $resource => $amount) {
            $carriedAverages[$resource] = round($amount / $runs, 3);
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
            'effective_battery_cost' => max(0, (int) ceil($location['battery_cost'] * (100 + $energyCostPercent) / 100)),
            'profile' => $profile,
            'found_value_per_run' => round($totals['found_value'] / $runs, 3),
            'carried_value_per_run' => round($totals['carried_value'] / $runs, 3),
            'lost_units_per_run' => round($totals['lost_units'] / $runs, 3),
            'carried_loot_per_run' => $carriedAverages,
            'event_battery_delta_per_run' => round($totals['battery_delta'] / $runs, 3),
            'loot_per_run' => $averages,
            'event_counts' => $totals['event_counts'],
        ];
    }

    private function recordLoot(array &$totals, string $resource, int $amount, int &$freeStorage, array $resources, array $byName): void
    {
        $slug = array_key_exists($resource, $resources) ? $resource : ($byName[$resource] ?? null);
        if ($slug === null || $amount <= 0) {
            return;
        }

        $definition = $resources[$slug];
        $name = $definition['name'];
        $size = (int) $definition['storage_size'];
        $value = (int) $definition['scrap_value'];
        $carried = $size > 0 ? min($amount, intdiv($freeStorage, $size)) : $amount;
        $freeStorage -= $carried * $size;

        $totals['loot'][$name] = ($totals['loot'][$name] ?? 0) + $amount;
        $totals['carried'][$name] = ($totals['carried'][$name] ?? 0) + $carried;
        $totals['found_value'] += $amount * $value;
        $totals['carried_value'] += $carried * $value;
        $totals['lost_units'] += $amount - $carried;
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
