<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\User;
use App\Services\ExpeditionEventApplier;
use App\Services\ExpeditionProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpeditionEventApplierTest extends TestCase
{
    use RefreshDatabase;

    private function expedition(): \App\Models\Expedition
    {
        $robot = User::factory()->create()->robot;
        $location = Location::create([
            'name' => 'Event test zone',
            'slug' => 'drone_dump',
            'difficulty' => 1,
            'battery_cost' => 10,
        ]);

        return $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now()->subMinute(),
        ]);
    }

    public function test_mechanic_event_applies_temporary_ram(): void
    {
        $expedition = $this->expedition();
        $robot = $expedition->robot;
        $base = $robot->ram;

        app(ExpeditionEventApplier::class)->apply($expedition, 'wasteland_mechanic');

        $this->assertSame($base + 2, $robot->stats()->ram($robot));
        $this->assertSame($base, $robot->fresh()->ram);
    }

    public function test_damage_cannot_reduce_integrity_below_zero(): void
    {
        $expedition = $this->expedition();
        $expedition->robot->update(['integrity' => 1]);

        app(ExpeditionEventApplier::class)->apply($expedition, 'falling_debris');

        $this->assertSame(0, $expedition->robot->fresh()->integrity);
    }

    public function test_battery_recharge_never_exceeds_capacity(): void
    {
        $expedition = $this->expedition();
        $robot = $expedition->robot;
        $robot->update(['battery' => $robot->stats()->maxBattery($robot) - 1]);

        app(ExpeditionEventApplier::class)->apply($expedition, 'solar_charger');

        $this->assertSame($robot->stats()->maxBattery($robot), $robot->fresh()->battery);
    }

    public function test_resource_cache_respects_storage_capacity(): void
    {
        $expedition = $this->expedition();
        $robot = $expedition->robot;
        Resource::create([
            'name' => 'Scrap metal',
            'slug' => 'scrap_metal',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);
        $robot->update(['ssd' => 1]);

        app(ExpeditionEventApplier::class)->apply($expedition, 'salvage_cache');

        $this->assertLessThanOrEqual(1, $robot->usedStorage());
    }

    public function test_neutral_event_does_not_change_robot(): void
    {
        $expedition = $this->expedition();
        $robot = $expedition->robot;
        $before = $robot->getAttributes();

        app(ExpeditionEventApplier::class)->apply($expedition, 'unknown_signal');

        $this->assertSame($before, $robot->fresh()->getAttributes());
    }

    public function test_completed_expedition_is_not_rewarded_twice(): void
    {
        $expedition = $this->expedition();
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.1', 0);

        $processor = app(ExpeditionProcessor::class);
        $processor->processCompletedExpeditions($expedition->robot);
        $logs = $expedition->logs()->count();
        $xp = $expedition->robot->fresh()->xp;

        $processor->processCompletedExpeditions($expedition->robot->fresh());

        $this->assertSame($logs, $expedition->logs()->count());
        $this->assertSame($xp, $expedition->robot->fresh()->xp);
    }

    public function test_critical_emp_sets_battery_to_zero(): void
    {
        $expedition = $this->expedition();
        $expedition->robot->update(['battery' => 80]);

        app(ExpeditionEventApplier::class)->apply($expedition, 'critical_emp');

        $this->assertSame(0, $expedition->robot->fresh()->battery);
    }
}
