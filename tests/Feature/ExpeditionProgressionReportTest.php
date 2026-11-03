<?php

namespace Tests\Feature;

use App\Services\ExpeditionProgressionProjector;
use Tests\TestCase;

class ExpeditionProgressionReportTest extends TestCase
{
    public function test_projection_reaches_target_and_uses_only_unlocked_routes(): void
    {
        $report = app(ExpeditionProgressionProjector::class)->project(6, 40, 42);
        $this->assertCount(5, $report['milestones']);
        $this->assertSame(6, end($report['milestones'])['level']);

        $previousMinutes = 0;
        foreach ($report['milestones'] as $milestone) {
            $this->assertLessThanOrEqual(
                $milestone['level'] - 1,
                config('locations.'.$milestone['route'].'.required_level')
            );
            $this->assertGreaterThan($previousMinutes, $milestone['cumulative_expedition_minutes']);
            $this->assertGreaterThan(0, $milestone['expected_runs']);
            $previousMinutes = $milestone['cumulative_expedition_minutes'];
        }
    }

    public function test_strategies_are_reproducible_and_differ_after_unlocks(): void
    {
        $projector = app(ExpeditionProgressionProjector::class);
        $fast = $projector->project(10, 100, 42, 'xp_per_minute');
        $fastAgain = $projector->project(10, 100, 42, 'xp_per_minute');
        $unlocked = $projector->project(10, 100, 42, 'highest_unlocked');

        $this->assertSame($fast, $fastAgain);
        $this->assertNotSame(
            array_column($fast['milestones'], 'route'),
            array_column($unlocked['milestones'], 'route')
        );
    }

    public function test_command_writes_json_report(): void
    {
        $path = sys_get_temp_dir().'/zero-progression-'.uniqid('', true).'.json';
        try {
            $this->artisan('expeditions:progression-report', [
                '--target-level' => 5, '--runs' => 20, '--output' => $path,
            ])->assertSuccessful();

            $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(4, $report['milestones']);
            $this->assertSame(5, $report['parameters']['target_level']);
            $this->assertArrayHasKey('limitations', $report);
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_strategy_is_rejected(): void
    {
        $this->artisan('expeditions:progression-report', [
            '--strategy' => 'invalid',
        ])->assertFailed();
    }
}
