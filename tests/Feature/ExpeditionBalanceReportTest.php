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

    public function test_report_rejects_invalid_runs(): void
    {
        $this->artisan('expeditions:balance-report', ['--runs' => 0])->assertFailed();
    }
}
