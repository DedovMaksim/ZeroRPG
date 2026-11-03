<?php

namespace App\Console\Commands;

use App\Services\StatefulExpeditionCycleSimulator;
use Illuminate\Console\Command;

class StatefulExpeditionReport extends Command
{
    protected $signature = 'expeditions:stateful-report
        {--target-level=20 : Target level}
        {--runs=10000 : Route samples}
        {--seed=42 : Seed}
        {--strategy=xp_per_cycle_minute : xp_per_minute, highest_unlocked or xp_per_cycle_minute}
        {--max-expeditions=100000 : Safety limit}
        {--output= : JSON output path}';

    protected $description = 'Approximate stateful progression with overlapping battery recharge and scrap-metal repairs';

    public function handle(StatefulExpeditionCycleSimulator $simulator): int
    {
        try {
            $report = $simulator->simulate(
                (int) $this->option('target-level'),
                (int) $this->option('runs'),
                (int) $this->option('seed'),
                (string) $this->option('strategy'),
                (int) $this->option('max-expeditions')
            );
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->table(['Metric', 'Value'], array_map(
            static fn ($key, $value) => [$key, (string) ($value ?? 'none')],
            array_keys($report['result']),
            array_values($report['result'])
        ));
        $this->warn($report['limitations']);

        if ($this->option('output')) {
            $path = (string) $this->option('output');
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
                $this->error('Cannot create output directory.');
                return self::FAILURE;
            }
            if (file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                $this->error('Cannot write JSON report.');
                return self::FAILURE;
            }
            $this->info('Saved report to '.$path);
        }
        return self::SUCCESS;
    }
}
