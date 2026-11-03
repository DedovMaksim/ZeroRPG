<?php

namespace App\Console\Commands;

use App\Services\ExpeditionProgressionProjector;
use Illuminate\Console\Command;

class ExpeditionProgressionReport extends Command
{
    protected $signature = 'expeditions:progression-report
        {--target-level=20 : Target level (2..100)}
        {--runs=10000 : Monte Carlo runs per route}
        {--seed=42 : Reproducible simulation seed}
        {--strategy=xp_per_minute : xp_per_minute or highest_unlocked}
        {--output= : Optional JSON output path}';

    protected $description = 'Project XP progression from level 1 using simulated average expedition outcomes';

    public function handle(ExpeditionProgressionProjector $projector): int
    {
        try {
            $result = $projector->project(
                (int) $this->option('target-level'),
                (int) $this->option('runs'),
                (int) $this->option('seed'),
                (string) $this->option('strategy')
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Level', 'Route', 'XP needed', 'Expected runs', 'Total expedition hours', 'Battery spent', 'Integrity damage'],
            array_map(static fn (array $row) => [
                $row['level'],
                $row['route'],
                $row['xp_required'],
                $row['expected_runs'],
                round($row['cumulative_expedition_minutes'] / 60, 2),
                $row['cumulative_battery_spent'],
                $row['cumulative_integrity_damage'],
            ], $result['milestones'])
        );
        $this->warn($result['limitations']);

        if ($this->option('output')) {
            $path = (string) $this->option('output');
            $directory = dirname($path);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                $this->error('Cannot create output directory.');

                return self::FAILURE;
            }
            if (file_put_contents($path, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                $this->error('Cannot write JSON report.');

                return self::FAILURE;
            }
            $this->info('Saved report to '.$path);
        }

        return self::SUCCESS;
    }
}
