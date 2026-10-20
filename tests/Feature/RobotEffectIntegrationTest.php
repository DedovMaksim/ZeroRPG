<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\User;
use App\Services\BatteryProcessor;
use App\Services\ExpeditionLootGenerator;
use App\Services\ExpeditionProcessor;
use App\Services\RobotEffectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotEffectIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function location(): Location
    {
        return Location::create([
            'name' => 'Modifier test zone',
            'slug' => 'modifier_test_zone',
            'difficulty' => 1,
            'battery_cost' => 20,
        ]);
    }

    public function test_energy_cost_modifier_changes_actual_expedition_cost(): void
    {
        $user = User::factory()->create();
        $robot = $user->robot;
        $robot->update(['battery' => 100]);
        app(RobotEffectService::class)->apply($robot, 'efficient', 'energy_cost_percent', -50, 60);

        $this->actingAs($user)->post(route('expeditions.start', $this->location()))
            ->assertSessionHas('success');

        $this->assertSame(90, $robot->fresh()->battery);
    }

    public function test_duration_modifier_changes_expedition_time(): void
    {
        $location = $this->location();
        $normal = User::factory()->create();
        $boosted = User::factory()->create();

        app(RobotEffectService::class)->apply($boosted->robot, 'fast', 'duration_percent', -50, 60);

        $this->actingAs($normal)->post(route('expeditions.start', $location));
        $this->actingAs($boosted)->post(route('expeditions.start', $location));

        $normalExpedition = $normal->robot->expeditions()->firstOrFail();
        $boostedExpedition = $boosted->robot->expeditions()->firstOrFail();

        $this->assertLessThan(
            $normalExpedition->started_at->diffInSeconds($normalExpedition->finished_at),
            $boostedExpedition->started_at->diffInSeconds($boostedExpedition->finished_at)
        );
    }

    public function test_charge_rate_modifier_changes_recovered_battery(): void
    {
        $normal = User::factory()->create()->robot;
        $boosted = User::factory()->create()->robot;

        foreach ([$normal, $boosted] as $robot) {
            $robot->update(['battery' => 0, 'battery_updated_at' => now()->subMinutes(5)]);
        }

        app(RobotEffectService::class)->apply($boosted, 'rapid_charge', 'charge_rate_percent', 50, 60);

        app(BatteryProcessor::class)->process($normal);
        app(BatteryProcessor::class)->process($boosted);

        $this->assertSame(20, $normal->fresh()->battery);
        $this->assertSame(30, $boosted->fresh()->battery);
    }

    public function test_loot_penalty_does_not_create_negative_inventory(): void
    {
        $user = User::factory()->create();
        $robot = $user->robot;
        $location = $this->location();
        $resource = Resource::create([
            'name' => 'Modifier metal',
            'slug' => 'modifier-metal',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);
        config()->set('locations.modifier_test_zone.loot', [[
            'resource' => $resource->name,
            'chance' => 100,
            'min' => 5,
            'max' => 5,
        ]]);

        app(RobotEffectService::class)->apply($robot, 'broken_scanner', 'loot_percent', -100, 60);

        $expedition = $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'completed',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);

        $this->assertSame([], app(ExpeditionLootGenerator::class)->generate($expedition));
        $this->assertSame(0, $robot->inventories()->count());
    }

    public function test_xp_modifier_applies_to_completed_expedition(): void
    {
        $robot = User::factory()->create()->robot;
        $location = $this->location();
        config()->set('locations.modifier_test_zone.xp', ['min' => 10, 'max' => 10]);
        app(RobotEffectService::class)->apply($robot, 'learning', 'xp_percent', 50, 60);

        $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now()->subMinute(),
        ]);

        app(ExpeditionProcessor::class)->processCompletedExpeditions($robot);

        $this->assertSame(15, $robot->fresh()->xp);
    }

    public function test_temporary_ssd_penalty_never_deletes_existing_inventory(): void
    {
        $robot = User::factory()->create()->robot;
        $resource = Resource::create([
            'name' => 'Existing parts',
            'slug' => 'existing-parts',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);
        $robot->inventories()->create(['resource_id' => $resource->id, 'amount' => 8]);
        app(RobotEffectService::class)->apply($robot, 'damaged_ssd', 'ssd', -6, 60);

        $this->assertSame(2, $robot->totalStorage());
        $this->assertSame(8, $robot->usedStorage());
        $this->assertDatabaseHas('inventories', ['robot_id' => $robot->id, 'resource_id' => $resource->id, 'amount' => 8]);
    }
}
