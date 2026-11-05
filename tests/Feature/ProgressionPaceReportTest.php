<?php

namespace Tests\Feature;

use App\Services\ProgressionPaceAnalyzer;
use Tests\TestCase;

class ProgressionPaceReportTest extends TestCase
{
    public function test_analyzer_uses_cumulative_milestones_without_inventing_levels(): void
    {
        $report = [
            'parameters' => ['seed' => 42],
            'result' => ['reached_level' => 3, 'stop_reason' => null],
            'milestones' => [
                ['level' => 2, 'elapsed_minutes' => 50, 'waiting_minutes' => 20, 'expeditions' => 3, 'repairs' => 1, 'farming_expeditions' => 2, 'route' => 'drone_dump'],
                ['level' => 3, 'elapsed_minutes' => 130, 'waiting_minutes' => 50, 'expeditions' => 8, 'repairs' => 3, 'farming_expeditions' => 4, 'route' => 'old_substation'],
            ],
        ];

        $analysis = app(ProgressionPaceAnalyzer::class)->analyze($report);
        $this->assertCount(2, $analysis['levels']);
        $this->assertSame(80.0, $analysis['levels'][1]['minutes']);
        $this->assertSame(5, $analysis['levels'][1]['expeditions']);
        $this->assertSame(2, $analysis['levels'][1]['repairs']);
        $this->assertSame(2, $analysis['levels'][1]['farming_expeditions']);
        $this->assertSame(3, $analysis['slowest_intervals'][0]['to_level']);
    }

    public function test_pace_command_runs(): void
    {
        $this->artisan('expeditions:pace-report', [
            '--target-level' => 3,
            '--runs' => 100,
            '--max-expeditions' => 1000,
        ])->assertExitCode(0);
    }
}
