<?php

namespace App\Console\Commands;

use App\Services\ProgressionPaceAnalyzer;
use App\Services\ResourceAwareExpeditionCycleSimulator;
use Illuminate\Console\Command;

class ProgressionPaceReport extends Command
{
    protected $signature = 'expeditions:pace-report
        {--target-level=20}
        {--runs=100}
        {--seed=42}
        {--strategy=xp_per_cycle_minute}
        {--max-expeditions=100000}
        {--output= : Optional JSON report path}';

    protected $description = 'Show time, repairs, farming and expeditions for each achieved level';

    public function handle(ResourceAwareExpeditionCycleSimulator $simulator, ProgressionPaceAnalyzer $analyzer): int
    {
        try {
            $report = $simulator->simulate(
                (int) $this->option('target-level'),
                (int) $this->option('runs'),
                (int) $this->option('seed'),
                (string) $this->option('strategy'),
                (int) $this->option('max-expeditions')
            );
            $analysis = $analyzer->analyze($report);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Levels', 'Minutes', 'Waiting', 'Runs', 'Repairs', 'Farming', 'Last route'],
            array_map(static fn (array $row) => [
                $row['from_level'].' -> '.$row['to_level'],
                $row['minutes'], $row['waiting_minutes'], $row['expeditions'],
                $row['repairs'], $row['farming_expeditions'], $row['last_route'],
            ], $analysis['levels'])
        );
        $this->info('Reached level: '.$analysis['reached_level']);
        $this->info('Stop reason: '.($analysis['stop_reason'] ?? 'none'));
        $this->warn($analysis['limitations']);

        if ($this->option('output')) {
            $path = (string) $this->option('output');
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
                $this->error('Cannot create output directory.');

                return self::FAILURE;
            }
            if (file_put_contents($path, json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                $this->error('Cannot write JSON report.');

                return self::FAILURE;
            }
            $this->info('Saved report to '.$path);
        }

        return self::SUCCESS;
    }
}
