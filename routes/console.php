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
