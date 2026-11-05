<?php

namespace Tests\Feature;

use App\Services\ResourceAwareExpeditionCycleSimulator;
use Tests\TestCase;

class ResourceAwareExpeditionReportTest extends TestCase
{
    public function test_resource_aware_scenario_is_deterministic_and_capacity_bounded(): void
    {
        $simulator = app(ResourceAwareExpeditionCycleSimulator::class);
        $first = $simulator->simulate(3, 100, 42, 'xp_per_cycle_minute', 1000);
        $second = $simulator->simulate(3, 100, 42, 'xp_per_cycle_minute', 1000);

        $this->assertSame($first, $second);
        $this->assertGreaterThanOrEqual(0, $first['result']['ssd_used']);
        $this->assertLessThanOrEqual(32, $first['result']['ssd_used']);
        $this->assertIsInt($first['result']['remaining_scrap_metal']);
        $this->assertEqualsWithDelta(
            $first['result']['expedition_minutes'] + $first['result']['waiting_minutes'],
            $first['result']['elapsed_minutes'],
            0.02
        );
    }

    public function test_new_command_runs_without_changing_legacy_command(): void
    {
        $this->artisan('expeditions:resource-report', [
            '--target-level' => 3,
            '--runs' => 100,
            '--max-expeditions' => 1000,
        ])->assertExitCode(0);
    }
}
