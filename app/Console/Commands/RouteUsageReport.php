<?php

namespace App\Console\Commands;

use App\Services\ResourceAwareExpeditionCycleSimulator;
use Illuminate\Console\Command;

class RouteUsageReport extends Command
{
    protected $signature = 'expeditions:route-usage
        {--target-level=20}
        {--runs=100}
        {--seed=42}
        {--strategy=xp_per_cycle_minute}
        {--max-expeditions=100000}
        {--output= : Optional JSON output path}';

    protected $description = 'Report route usage by progression versus scrap-farming intent';

    public function handle(ResourceAwareExpeditionCycleSimulator $simulator): int
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

        $usage = $report['route_usage'];
        uasort($usage, static fn (array $a, array $b) => $b['total'] <=> $a['total']);
        $this->table(
            ['Route', 'Total', 'Progression', 'Farming'],
            array_map(
                static fn (string $slug, array $counts) => [$slug, $counts['total'], $counts['progression'], $counts['farming']],
                array_keys($usage),
                array_values($usage)
            )
        );
        $this->info('Expeditions: '.$report['result']['expeditions']);
        $this->info('Reached level: '.$report['result']['reached_level']);
        $this->info('Stop reason: '.($report['result']['stop_reason'] ?? 'none'));
        $this->warn('Farming means route selected while below repair reserve, not guaranteed scrap loot. This is a deterministic scenario, not actual player telemetry.');

        if ($this->option('output')) {
            $path = (string) $this->option('output');
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
                $this->error('Cannot create output directory.');

                return self::FAILURE;
            }
            if (file_put_contents($path, json_encode([
                'parameters' => $report['parameters'],
                'result' => $report['result'],
                'route_usage' => $report['route_usage'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
                $this->error('Cannot write JSON report.');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
