<?php

namespace Tests\Feature;

use App\Services\ExpeditionCycleProjector;
use Tests\TestCase;

class ExpeditionCycleReportTest extends TestCase
{
    public function test_cycle_report_has_monotonic_time_and_feasible_routes(): void
    {
        $report = app(ExpeditionCycleProjector::class)->project(10, 100, 42, 'xp_per_cycle_minute', 2.0, 1.0);
        $previous = 0.0;
        foreach ($report['milestones'] as $milestone) {
            $this->assertGreaterThan($previous, $milestone['cumulative_cycle_minutes']);
            $this->assertGreaterThanOrEqual($milestone['cumulative_expedition_minutes'], $milestone['cumulative_cycle_minutes']);
            $level = $milestone['level'] - 1;
            $this->assertLessThanOrEqual(100 + ($level - 1) * 5, config('locations.'.$milestone['route'].'.battery_cost'));
            $previous = $milestone['cumulative_cycle_minutes'];
        }
    }

    public function test_recharge_rate_changes_time_but_not_route_selection_for_highest_unlocked(): void
    {
        $projector = app(ExpeditionCycleProjector::class);
        $slow = $projector->project(8, 100, 42, 'highest_unlocked', 1.0);
        $fast = $projector->project(8, 100, 42, 'highest_unlocked', 2.0);
        $this->assertSame(array_column($slow['milestones'], 'route'), array_column($fast['milestones'], 'route'));
        $this->assertGreaterThan(end($fast['milestones'])['cumulative_cycle_minutes'], end($slow['milestones'])['cumulative_cycle_minutes']);
    }

    public function test_cli_writes_json_report(): void
    {
        $path = storage_path('app/cycle-report-test.json');
        try {
            $this->artisan('expeditions:cycle-report', [
                '--target-level' => 5, '--runs' => 100, '--strategy' => 'xp_per_cycle_minute',
                '--output' => $path,
            ])->assertExitCode(0);
            $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(4, $report['milestones']);
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_recharge_rate_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(ExpeditionCycleProjector::class)->project(5, 100, 42, 'xp_per_cycle_minute', 0);
    }
}
