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
}
