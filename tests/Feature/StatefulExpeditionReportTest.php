<?php

namespace Tests\Feature;

use App\Services\StatefulExpeditionCycleSimulator;
use Tests\TestCase;

class StatefulExpeditionReportTest extends TestCase
{
    public function test_stateful_progression_is_reproducible_and_time_is_consistent(): void
    {
        $simulator = app(StatefulExpeditionCycleSimulator::class);
        $first = $simulator->simulate(5, 100, 42, 'xp_per_cycle_minute', 10000);
        $second = $simulator->simulate(5, 100, 42, 'xp_per_cycle_minute', 10000);
        $this->assertSame($first, $second);
        $result = $first['result'];
        $this->assertEqualsWithDelta(
            $result['expedition_minutes'] + $result['waiting_minutes'],
            $result['elapsed_minutes'],
            0.02
        );
        $this->assertGreaterThanOrEqual(1, $result['reached_level']);
        $this->assertLessThanOrEqual(5, $result['reached_level']);
        $this->assertGreaterThanOrEqual(0, $result['remaining_battery']);
    }

    public function test_invalid_parameters_fail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(StatefulExpeditionCycleSimulator::class)->simulate(20, 100, 42, 'unknown');
    }

    public function test_command_generates_json(): void
    {
        $path = storage_path('app/stateful-test.json');
        try {
            $this->artisan('expeditions:stateful-report', [
                '--target-level' => 3,
                '--runs' => 100,
                '--max-expeditions' => 1000,
                '--output' => $path,
            ])->assertExitCode(0);
            $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('stop_reason', $report['result']);
            $this->assertArrayHasKey('milestones', $report);
        } finally {
            @unlink($path);
        }
    }
}
