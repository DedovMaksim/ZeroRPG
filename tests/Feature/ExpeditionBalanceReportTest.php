<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExpeditionBalanceReportTest extends TestCase
{
    public function test_report_covers_all_routes(): void
    {
        $path = sys_get_temp_dir().'/zero-balance-'.uniqid('', true).'.json';

        try {
            $this->artisan('expeditions:balance-report', [
                '--runs' => 20,
                '--seed' => 42,
                '--ssd' => 32,
                '--cpu' => 1,
                '--output' => $path,
            ])->assertSuccessful();

            $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame(20, $report['parameters']['runs']);
            $this->assertCount(11, $report['routes']);
            $this->assertSame(array_keys(config('locations')), array_column($report['routes'], 'location'));
            foreach ($report['routes'] as $route) {
                $this->assertSame(20, $route['runs']);
                $this->assertArrayHasKey('abort_percent', $route);
                $this->assertArrayHasKey('xp_per_minute', $route);
                $this->assertArrayHasKey('carried_value_per_minute', $route);
                $this->assertArrayHasKey('lost_value_per_run', $route);
                $this->assertArrayHasKey('xp_per_energy', $route);
                $this->assertArrayHasKey('carried_value_per_energy', $route);
                $this->assertArrayHasKey('lost_value_per_minute', $route);
                $this->assertArrayHasKey('success_percent', $route);
                $this->assertArrayHasKey('integrity_loss_per_successful_run', $route);
                $this->assertArrayHasKey('xp_per_successful_run', $route);
                $this->assertArrayHasKey('carried_value_per_successful_run', $route);
                $this->assertSame(20, $route['successful_runs'] + (int) round($route['abort_percent'] * 20 / 100));
                $this->assertEqualsWithDelta(100 - $route['abort_percent'], $route['success_percent'], 0.001);
                if ($route['effective_battery_cost'] > 0) {
                    $this->assertEqualsWithDelta(
                        $route['xp_per_run'] / $route['effective_battery_cost'],
                        $route['xp_per_energy'],
                        0.0001
                    );
                }
            }
        } finally {
            @unlink($path);
        }
    }

    public function test_xp_progression_keeps_starter_routes_and_scales_later_routes(): void
    {
        $expected = [
            'drone_dump' => [1, 3],
            'abandoned_factory' => [2, 4],
            'old_substation' => [3, 5],
            'repair_terminal' => [6, 9],
            'cargo_hub' => [8, 12],
            'research_sector' => [12, 17],
            'data_archive' => [15, 21],
            'security_perimeter' => [20, 28],
            'assembly_plant' => [25, 35],
            'dead_comms_zone' => [35, 47],
            'old_network_node' => [48, 64],
        ];

        foreach ($expected as $slug => [$min, $max]) {
            $this->assertSame($min, config('locations.'.$slug.'.xp.min'));
            $this->assertSame($max, config('locations.'.$slug.'.xp.max'));
        }

        // Longer, higher-level routes must offer more base XP per completed run.
        $previous = 0;
        foreach (array_keys($expected) as $slug) {
            $average = (config('locations.'.$slug.'.xp.min') + config('locations.'.$slug.'.xp.max')) / 2;
            $this->assertGreaterThan($previous, $average);
            $previous = $average;
        }
    }

    public function test_report_rejects_invalid_runs(): void
    {
        $this->artisan('expeditions:balance-report', ['--runs' => 0])->assertFailed();
    }
}
