<?php

namespace Tests\Feature;

use App\Services\ResourceAwareExpeditionCycleSimulator;
use Tests\TestCase;

class RouteUsageReportTest extends TestCase
{
    public function test_route_counts_reconcile_with_expedition_totals(): void
    {
        $report = app(ResourceAwareExpeditionCycleSimulator::class)
            ->simulate(5, 100, 42, 'xp_per_cycle_minute', 10000);

        $total = 0;
        $farming = 0;
        foreach ($report['route_usage'] as $counts) {
            $this->assertSame($counts['total'], $counts['progression'] + $counts['farming']);
            $total += $counts['total'];
            $farming += $counts['farming'];
        }

        $this->assertSame($report['result']['expeditions'], $total);
        $this->assertSame($report['result']['farming_expeditions'], $farming);
    }

    public function test_route_usage_command_runs(): void
    {
        $this->artisan('expeditions:route-usage', [
            '--target-level' => 3,
            '--runs' => 100,
            '--max-expeditions' => 1000,
        ])->assertExitCode(0);
    }
}
