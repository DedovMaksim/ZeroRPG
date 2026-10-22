<?php

namespace Tests\Feature;

use App\Services\ExpeditionBalanceSimulator;
use Tests\TestCase;

class ExpeditionBalanceSimulatorTest extends TestCase
{
    public function test_same_seed_produces_identical_results(): void
    {
        $simulator = app(ExpeditionBalanceSimulator::class);
        $this->assertSame(
            $simulator->simulate('drone_dump', 200, 42),
            $simulator->simulate('drone_dump', 200, 42)
        );
    }

    public function test_abort_rate_is_zero_when_critical_event_is_not_eligible(): void
    {
        $result = app(ExpeditionBalanceSimulator::class)->simulate('drone_dump', 200, 42);
        $this->assertSame(0.0, $result['abort_percent']);
    }

    public function test_no_events_when_all_trigger_chances_are_zero(): void
    {
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.3', 0);
        $result = app(ExpeditionBalanceSimulator::class)->simulate('old_substation', 200, 42);

        $this->assertSame(0.0, $result['events_per_run']);
        $this->assertSame(0.0, $result['abort_percent']);
        $this->assertEqualsWithDelta(4.0, $result['xp_per_run'], 0.5);
    }

    public function test_command_runs_for_selected_location(): void
    {
        $this->artisan('game:simulate-expeditions', [
            '--runs' => '10',
            '--seed' => '42',
            '--location' => 'drone_dump',
        ])->assertExitCode(0);
    }
    public function test_larger_ssd_never_reduces_carried_value_for_same_seed(): void
    {
        $simulator = app(ExpeditionBalanceSimulator::class);
        $small = $simulator->simulate('drone_dump', 500, 42, ['ssd' => 8, 'cpu' => 1]);
        $large = $simulator->simulate('drone_dump', 500, 42, ['ssd' => 32, 'cpu' => 1]);

        $this->assertGreaterThanOrEqual($small['carried_value_per_run'], $large['carried_value_per_run']);
        $this->assertEquals($small['found_value_per_run'], $large['found_value_per_run']);
        $this->assertLessThanOrEqual($large['found_value_per_run'], $large['carried_value_per_run']);
    }

    public function test_simulator_rejects_negative_storage(): void
    {
        $this->expectException(\\InvalidArgumentException::class);
        app(ExpeditionBalanceSimulator::class)->simulate('drone_dump', 10, 42, ['ssd' => -1]);
    }

    public function test_command_accepts_developed_profile(): void
    {
        $this->artisan('game:simulate-expeditions', [
            '--runs' => '10', '--location' => 'drone_dump', '--profile' => 'developed',
        ])->assertExitCode(0);
    }

}
