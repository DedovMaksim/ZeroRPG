<?php

namespace App\Console\Commands;

use App\Services\ExpeditionCycleProjector;
use Illuminate\Console\Command;

class ExpeditionCycleReport extends Command
{
    protected $signature = 'expeditions:cycle-report
        {--target-level=20 : Target level (2..100)}
        {--runs=10000 : Monte Carlo runs per route}
        {--seed=42 : Reproducible seed}
        {--strategy=xp_per_cycle_minute : xp_per_minute, highest_unlocked, xp_per_cycle_minute}
        {--battery-per-minute=1 : Assumed battery regenerated per minute}
        {--scraps-per-integrity=1 : Assumed scrap cost per repaired integrity point}
        {--output= : Optional JSON output path}';

    protected $description = 'Compare expected expedition time, recharge and repair economics under explicit assumptions';

    public function handle(ExpeditionCycleProjector $projector): int
    {
        try {
            $report = $projector->project(
                (int) $this->option('target-level'),
                (int) $this->option('runs'),
                (int) $this->option('seed'),
                (string) $this->option('strategy'),
                (float) $this->option('battery-per-minute'),
                (float) $this->option('scraps-per-integrity')
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Level', 'Route', 'Runs', 'Expedition h', 'Recharge h', 'Cycle h', 'Scraps surplus'],
            array_map(static fn (array $row) => [
                $row['level'], $row['route'], $row['expected_runs'],
                round($row['cumulative_expedition_minutes'] / 60, 2),
                round($row['cumulative_recharge_minutes'] / 60, 2),
                round($row['cumulative_cycle_minutes'] / 60, 2),
                $row['cumulative_scraps_surplus'],
            ], $report['milestones'])
        );
        $this->warn($report['limitations']);

        if ($this->option('output')) {
            $path = (string) $this->option('output');
            $directory = dirname($path);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                $this->error('Cannot create output directory.');

                return self::FAILURE;
            }
            if (file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                $this->error('Cannot write report.');

                return self::FAILURE;
            }
            $this->info('Saved report to '.$path);
        }

        return self::SUCCESS;
    }
}
