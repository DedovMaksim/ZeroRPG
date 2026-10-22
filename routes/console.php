<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('game:simulate-expeditions
    {--runs=10000 : Number of simulated expeditions per location}
    {--seed=42 : Deterministic random seed}
    {--location= : Optional location slug}
    {--profile=starter : Robot scenario}', function (\App\Services\ExpeditionBalanceSimulator $simulator) {
    $runs = filter_var($this->option('runs'), FILTER_VALIDATE_INT);
    $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);

    if ($runs === false || $runs < 1 || $runs > 1000000 || $seed === false) {
        $this->error('Use --runs between 1 and 1000000 and an integer --seed.');
        return 1;
    }

    $profiles = [
        'starter' => ['ssd' => 8, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0],
        'developed' => ['ssd' => 16, 'cpu' => 10, 'loot_percent' => 0, 'energy_cost_percent' => -10],
        'advanced' => ['ssd' => 32, 'cpu' => 20, 'loot_percent' => 10, 'energy_cost_percent' => -20],
    ];
    $profile = (string) $this->option('profile');
    if (! isset($profiles[$profile])) {
        $this->error('Unknown profile.');
        return 1;
    }
    $location = $this->option('location');
    $slugs = $location ? [$location] : array_keys(config('locations', []));

    foreach ($slugs as $slug) {
        if (! config('locations.'.$slug)) {
            $this->error('Unknown location: '.$slug);
            return 1;
        }

        $result = $simulator->simulate($slug, $runs, $seed, $profiles[$profile]);
        $this->info(config('locations.'.$slug.'.name').' ('.$slug.')');
        $this->table(['Metric', 'Value'], [
            ['Runs', $result['runs']],
            ['Profile', $profile],
            ['SSD', $result['profile']['ssd'].' MB'],
            ['Effective battery cost', $result['effective_battery_cost'].'%'],
            ['Found value', $result['found_value_per_run'].' scraps'],
            ['Carried value', $result['carried_value_per_run'].' scraps'],
            ['Lost units', $result['lost_units_per_run']],
            ['Abort rate', $result['abort_percent'].'%'],
            ['Events per run', $result['events_per_run']],
            ['XP per run', $result['xp_per_run']],
            ['Integrity loss per run', $result['integrity_loss_per_run']],
            ['Base route battery cost', $result['battery_cost'].'%'],
            ['Event battery delta per run (unclamped)', $result['event_battery_delta_per_run']],
        ]);

        $this->table(
            ['Resource', 'Average amount per run'],
            array_map(
                fn ($name, $amount) => [$name, $amount],
                array_keys($result['loot_per_run']),
                array_values($result['loot_per_run'])
            )
        );

        $this->line('Carried loot: '.json_encode($result['carried_loot_per_run'], JSON_UNESCAPED_UNICODE));
        $this->line('Event counts: '.json_encode($result['event_counts'], JSON_UNESCAPED_UNICODE));
    }

    return 0;
})->purpose('Simulate expedition balance without modifying game data');


Artisan::command('game:compare-upgrades
    {--runs=10000 : Simulations per configuration}
    {--seed=42 : Shared random seed}
    {--location=drone_dump : Location slug}', function (\App\Services\ExpeditionBalanceSimulator $simulator) {
    $runs = filter_var($this->option('runs'), FILTER_VALIDATE_INT);
    $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);
    $slug = (string) $this->option('location');

    if ($runs === false || $runs < 1 || $runs > 1000000 || $seed === false) {
        $this->error('Use --runs between 1 and 1000000 and an integer --seed.');
        return 1;
    }
    if (! config('locations.'.$slug)) {
        $this->error('Unknown location: '.$slug);
        return 1;
    }

    $baseline = ['ssd' => 8, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0];
    $scenarios = [
        'Baseline' => $baseline,
        'SSD 16 MB' => array_replace($baseline, ['ssd' => 16]),
        'SSD 32 MB' => array_replace($baseline, ['ssd' => 32]),
        'CPU 10' => array_replace($baseline, ['cpu' => 10]),
        'CPU 20' => array_replace($baseline, ['cpu' => 20]),
        'Energy -10%' => array_replace($baseline, ['energy_cost_percent' => -10]),
        'Energy -20%' => array_replace($baseline, ['energy_cost_percent' => -20]),
    ];

    $rows = [];
    $baselineValue = null;
    foreach ($scenarios as $name => $profile) {
        $result = $simulator->simulate($slug, $runs, $seed, $profile);
        $value = $result['carried_value_per_run'];
        $baselineValue ??= $value;
        $energy = $result['effective_battery_cost'];
        $rows[] = [
            $name,
            $profile['ssd'],
            $profile['cpu'],
            $energy,
            $value,
            $baselineValue > 0 ? round(100 * ($value / $baselineValue - 1), 1).'%' : 'n/a',
            $energy > 0 ? round(100 * $value / $energy, 3) : 'n/a',
            $result['lost_units_per_run'],
        ];
    }

    $this->info(config('locations.'.$slug.'.name').' — isolated upgrade comparison ('.$runs.' runs)');
    $this->table(
        ['Scenario', 'SSD MB', 'CPU', 'Energy %', 'Scraps/run', 'vs baseline', 'Scraps/100 energy', 'Lost units/run'],
        $rows
    );
    $this->line('Each scenario starts with an empty SSD; only one stat changes from baseline.');
    $this->line('Scrap values are potential sale values, not confirmed market proceeds.');
    return 0;
})->purpose('Compare isolated SSD, CPU and energy upgrades without changing game data');


Artisan::command('game:module-economy
    {--runs=10000 : Simulations per scenario}
    {--seed=42 : Shared random seed}
    {--location= : Optional location slug}', function (\App\Services\ExpeditionBalanceSimulator $simulator) {
    $runs = filter_var($this->option('runs'), FILTER_VALIDATE_INT);
    $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);
    if ($runs === false || $runs < 1 || $runs > 1000000 || $seed === false) {
        $this->error('Use --runs between 1 and 1000000 and an integer --seed.');
        return 1;
    }

    $slugs = $this->option('location') ? [(string) $this->option('location')] : array_keys(config('locations', []));
    foreach ($slugs as $slug) {
        if (! config('locations.'.$slug)) {
            $this->error('Unknown location: '.$slug);
            return 1;
        }
    }

    // The baseline is deliberately fixed: empty SSD, CPU 1, logistics level 1.
    // An equipped module replaces the module of the same type; bonuses never stack.
    $baseline = ['ssd' => 8, 'cpu' => 1, 'loot_percent' => 0, 'energy_cost_percent' => 0];
    $modules = [
        ['slug' => 'ssd-module-mk1', 'name' => 'SSD Module Mk.1', 'price' => 40, 'ssd_bonus' => 8, 'cpu_bonus' => 0],
        ['slug' => 'cpu-module-mk1', 'name' => 'CPU Module Mk.1', 'price' => 25, 'ssd_bonus' => 0, 'cpu_bonus' => 1],
    ];

    foreach ($slugs as $slug) {
        $base = $simulator->simulate($slug, $runs, $seed, $baseline);
        $baseValue = $base['carried_value_per_run'];
        $rows = [];
        foreach ($modules as $module) {
            $profile = $baseline;
            $profile['ssd'] += $module['ssd_bonus'];
            $profile['cpu'] += $module['cpu_bonus'];
            $upgraded = $simulator->simulate($slug, $runs, $seed, $profile);
            $gain = $upgraded['carried_value_per_run'] - $baseValue;
            $rows[] = [
                $module['name'],
                $module['price'],
                $baseValue,
                $upgraded['carried_value_per_run'],
                round($gain, 3),
                $gain > 0 ? (string) (int) ceil($module['price'] / $gain) : 'no payback',
                $baseValue > 0 ? (string) (int) ceil($module['price'] / $baseValue) : 'n/a',
            ];
        }
        $this->info(config('locations.'.$slug.'.name').' ('.$slug.')');
        $this->table(
            ['Module', 'Price', 'Base scraps/run', 'With module', 'Gain/run', 'Payback runs', 'Runs to afford'],
            $rows
        );
    }
    $this->line('Static estimate only: empty SSD before each run, no logistics leveling, repairs, existing inventory or battery waiting.');
    $this->line('Prices and module bonuses reflect ItemSeeder defaults; database rows may differ.');
    $this->line('Only the equipped module of each type contributes; duplicate purchases do not stack.');
    $this->line('RAM is excluded: no direct loot-value bonus in the expedition simulator.');
    return 0;
})->purpose('Estimate payback of actual Mk.1 equipment against starter expedition economy');
