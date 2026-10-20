<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('game:simulate-expeditions
    {--runs=10000 : Number of simulated expeditions per location}
    {--seed=42 : Deterministic random seed}
    {--location= : Optional location slug}', function (\App\Services\ExpeditionBalanceSimulator $simulator) {
    $runs = filter_var($this->option('runs'), FILTER_VALIDATE_INT);
    $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);

    if ($runs === false || $runs < 1 || $runs > 1000000 || $seed === false) {
        $this->error('Use --runs between 1 and 1000000 and an integer --seed.');
        return 1;
    }

    $location = $this->option('location');
    $slugs = $location ? [$location] : array_keys(config('locations', []));

    foreach ($slugs as $slug) {
        if (! config('locations.'.$slug)) {
            $this->error('Unknown location: '.$slug);
            return 1;
        }

        $result = $simulator->simulate($slug, $runs, $seed);
        $this->info(config('locations.'.$slug.'.name').' ('.$slug.')');
        $this->table(['Metric', 'Value'], [
            ['Runs', $result['runs']],
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

        $this->line('Event counts: '.json_encode($result['event_counts'], JSON_UNESCAPED_UNICODE));
    }

    return 0;
})->purpose('Simulate expedition balance without modifying game data');
