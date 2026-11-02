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
        $this->expectException(\InvalidArgumentException::class);
        app(ExpeditionBalanceSimulator::class)->simulate('drone_dump', 10, 42, ['ssd' => -1]);
    }

    public function test_command_accepts_developed_profile(): void
    {
        $this->artisan('game:simulate-expeditions', [
            '--runs' => '10', '--location' => 'drone_dump', '--profile' => 'developed',
        ])->assertExitCode(0);
    }

    public function test_isolated_upgrade_comparison_command_runs(): void
    {
        $this->artisan('game:compare-upgrades', [
            '--runs' => '30',
            '--seed' => '42',
            '--location' => 'drone_dump',
        ])->assertExitCode(0);
    }

    public function test_isolated_upgrade_comparison_rejects_unknown_location(): void
    {
        $this->artisan('game:compare-upgrades', [
            '--runs' => '10',
            '--location' => 'nonexistent',
        ])->assertExitCode(1);
    }

    public function test_energy_discount_does_not_change_loot(): void
    {
        $simulator = app(ExpeditionBalanceSimulator::class);
        $base = $simulator->simulate('drone_dump', 200, 42, ['ssd' => 8, 'cpu' => 1]);
        $discount = $simulator->simulate('drone_dump', 200, 42, [
            'ssd' => 8, 'cpu' => 1, 'energy_cost_percent' => -20,
        ]);

        $this->assertSame($base['carried_loot_per_run'], $discount['carried_loot_per_run']);
        $this->assertSame(64, $discount['effective_battery_cost']);
    }

    public function test_module_economy_command_runs_for_selected_location(): void
    {
        $this->artisan('game:module-economy', [
            '--runs' => '30',
            '--seed' => '42',
            '--location' => 'drone_dump',
        ])->assertExitCode(0);
    }

    public function test_module_economy_rejects_unknown_location(): void
    {
        $this->artisan('game:module-economy', [
            '--runs' => '10',
            '--location' => 'missing',
        ])->assertExitCode(1);
    }

    public function test_progression_economy_command_runs(): void
    {
        $this->artisan('game:progression-economy', [
            '--location' => 'drone_dump',
            '--expeditions' => '5',
            '--runs' => '100',
            '--seed' => '42',
        ])->assertExitCode(0);
    }

    public function test_progression_economy_rejects_invalid_expedition_count(): void
    {
        $this->artisan('game:progression-economy', [
            '--expeditions' => '0',
        ])->assertExitCode(1);
    }

    public function test_progression_does_not_require_upfront_money_for_ssd(): void
    {
        $this->artisan('game:progression-economy', [
            '--location' => 'drone_dump',
            '--expeditions' => '1',
            '--runs' => '100',
            '--seed' => '42',
        ])->expectsOutputToContain('not affordable')->assertExitCode(0);
    }

    public function test_cpu_experiment_is_deterministic_and_compares_five_levels(): void
    {
        $experiment = app(\App\Services\CpuLootExperiment::class);
        $first = $experiment->compare('drone_dump', 100, 42);
        $this->assertSame($first, $experiment->compare('drone_dump', 100, 42));
        $this->assertSame([1, 5, 10, 15, 20], array_column($first, 'level'));
        foreach ($first as $row) {
            $this->assertGreaterThanOrEqual(0, $row['cpu_gain']);
            $this->assertGreaterThanOrEqual(0, $row['ssd_gain']);
        }
    }

    public function test_cpu_experiment_command_validates_location(): void
    {
        $this->artisan('game:cpu-experiment', [
            '--location' => 'missing',
            '--runs' => '10',
        ])->assertExitCode(1);
    }

    public function test_cpu_experiment_command_runs(): void
    {
        $this->artisan('game:cpu-experiment', [
            '--location' => 'drone_dump',
            '--runs' => '30',
        ])->assertExitCode(0);
    }

    public function test_priority_loading_keeps_electronics_when_ssd_is_limited(): void
    {
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.1', 0);
        config()->set('locations.drone_dump.loot', [
            ['resource' => 'Металлолом', 'chance' => 100, 'min' => 5, 'max' => 5],
            ['resource' => 'Электроника', 'chance' => 100, 'min' => 1, 'max' => 1],
        ]);

        $result = app(ExpeditionBalanceSimulator::class)->simulate('drone_dump', 1, 42, [
            'ssd' => 8, 'cpu' => 1,
        ]);

        $this->assertSame(1.0, $result['carried_loot_per_run']['Электроника']);
        $this->assertArrayNotHasKey('Металлолом', $result['carried_loot_per_run']);
        $this->assertSame(12.0, $result['carried_value_per_run']);
        $this->assertSame(5.0, $result['lost_units_per_run']);
    }

    public function test_priority_loading_allows_partial_lower_priority_loot(): void
    {
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.1', 0);
        config()->set('locations.drone_dump.loot', [
            ['resource' => 'Металлолом', 'chance' => 100, 'min' => 5, 'max' => 5],
            ['resource' => 'Медь', 'chance' => 100, 'min' => 1, 'max' => 1],
        ]);

        $result = app(ExpeditionBalanceSimulator::class)->simulate('drone_dump', 1, 42, [
            'ssd' => 5, 'cpu' => 1,
        ]);

        $this->assertSame(1.0, $result['carried_loot_per_run']['Медь']);
        $this->assertSame(2.0, $result['carried_loot_per_run']['Металлолом']);
        $this->assertSame(3.0, $result['lost_units_per_run']);
    }

}
