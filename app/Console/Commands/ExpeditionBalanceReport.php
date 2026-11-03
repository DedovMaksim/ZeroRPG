<?php

namespace App\Console\Commands;

use App\Services\ExpeditionBalanceSimulator;
use App\Services\ExpeditionEventCatalog;
use Illuminate\Console\Command;

class ExpeditionBalanceReport extends Command
{
    protected $signature = 'expeditions:balance-report
        {--runs=10000 : Number of simulations per route}
        {--seed=42 : Deterministic random seed}
        {--ssd=32 : Robot SSD capacity}
        {--cpu=1 : Robot CPU level}
        {--output= : Optional JSON output path}';

    protected $description = 'Compare expedition balance for every configured location';

    public function handle(ExpeditionBalanceSimulator $simulator, ExpeditionEventCatalog $catalog): int
    {
        $runs = filter_var($this->option('runs'), FILTER_VALIDATE_INT);
        $seed = filter_var($this->option('seed'), FILTER_VALIDATE_INT);
        $ssd = filter_var($this->option('ssd'), FILTER_VALIDATE_INT);
        $cpu = filter_var($this->option('cpu'), FILTER_VALIDATE_INT);

        if ($runs === false || $runs < 1 || $seed === false || $ssd === false || $ssd < 0 || $cpu === false || $cpu < 1) {
            $this->error('Invalid parameters: runs and cpu must be positive; ssd must be nonnegative; seed must be an integer.');
            return self::FAILURE;
        }

        $profile = ['ssd' => $ssd, 'cpu' => $cpu];
        $results = [];
        foreach (config('locations', []) as $slug => $location) {
            $eligibleEvents = $catalog->eligible($slug, (int) $location['difficulty']);
            if ($eligibleEvents === []) {
                $this->error('No eligible expedition events for location: '.$slug);
                return self::FAILURE;
            }
            $row = $simulator->simulate($slug, $runs, $seed, $profile);
            $row['eligible_event_count'] = count($eligibleEvents);
            $row['required_level'] = (int) ($location['required_level'] ?? 1);
            $row['duration_minutes'] = (int) $location['base_duration_minutes'];
            $row['xp_per_minute'] = round($row['xp_per_run'] / $row['duration_minutes'], 3);
            $row['carried_value_per_minute'] = round($row['carried_value_per_run'] / $row['duration_minutes'], 3);
            $row['lost_value_per_run'] = round($row['found_value_per_run'] - $row['carried_value_per_run'], 3);
            // Gross energy cost excludes battery effects: these are reported separately.
            $energy = $row['effective_battery_cost'];
            $row['xp_per_energy'] = $energy > 0 ? round($row['xp_per_run'] / $energy, 4) : null;
            $row['carried_value_per_energy'] = $energy > 0 ? round($row['carried_value_per_run'] / $energy, 4) : null;
            $row['lost_value_per_minute'] = round($row['lost_value_per_run'] / $row['duration_minutes'], 3);
            $row['success_percent'] = round(100 - $row['abort_percent'], 3);
            $row['xp_per_successful_run'] = $row['successful_runs'] > 0
                ? round($row['xp_per_run'] * $runs / $row['successful_runs'], 3)
                : null;
            $row['carried_value_per_successful_run'] = $row['successful_runs'] > 0
                ? round($row['carried_value_per_run'] * $runs / $row['successful_runs'], 3)
                : null;
            $results[] = $row;
        }

        usort($results, static fn (array $a, array $b) => $a['required_level'] <=> $b['required_level'] ?: $a['duration_minutes'] <=> $b['duration_minutes']);

        $this->table(
            ['Route', 'Lv', 'Min', 'Battery', 'XP/min', 'XP/energy', 'Value/min', 'Value/energy', 'Lost/min', 'Success %', 'Damage/success'],
            array_map(static fn (array $r) => [
                $r['location'], $r['required_level'], $r['duration_minutes'], $r['effective_battery_cost'],
                $r['xp_per_minute'], $r['xp_per_energy'], $r['carried_value_per_minute'],
                $r['carried_value_per_energy'], $r['lost_value_per_minute'],
                $r['success_percent'], $r['integrity_loss_per_successful_run'],
            ], $results)
        );

        if ($this->option('output')) {
            $path = (string) $this->option('output');
            $json = json_encode([
                'parameters' => ['runs' => $runs, 'seed' => $seed, 'profile' => $profile],
                'routes' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (@file_put_contents($path, $json."\n") === false) {
                $this->error('Could not write report: '.$path);
                return self::FAILURE;
            }
            $this->info('Saved JSON report: '.$path);
        }

        return self::SUCCESS;
    }
}
