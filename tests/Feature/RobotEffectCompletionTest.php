<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Services\ExpeditionProcessor;
use App\Services\RobotEffectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotEffectCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_bonus_changes_storage_without_changing_base_level(): void
    {
        $robot = User::factory()->create()->robot;
        $baseStorage = $robot->totalStorage();
        $baseLevel = $robot->logistics_level;

        app(RobotEffectService::class)->apply($robot, 'route_optimizer', 'logistics', 2, 60);

        $this->assertSame($baseStorage + 4, $robot->totalStorage());
        $this->assertSame($baseLevel, $robot->fresh()->logistics_level);
    }

    public function test_paid_energy_cost_is_recorded_and_reported_correctly(): void
    {
        $user = User::factory()->create();
        $robot = $user->robot;
        $robot->update(['battery' => 100]);

        $location = Location::create([
            'name' => 'Energy test',
            'slug' => 'energy_test',
            'difficulty' => 1,
            'battery_cost' => 20,
        ]);

        app(RobotEffectService::class)->apply($robot, 'efficient', 'energy_cost_percent', -50, 60);

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $expedition = $robot->expeditions()->firstOrFail();

        $this->assertSame(10, $expedition->battery_cost_paid);
        $this->assertSame(90, $robot->fresh()->battery);

        $expedition->update(['finished_at' => now()->subSecond()]);
        app(ExpeditionProcessor::class)->processCompletedExpeditions($robot->fresh());

        $this->assertDatabaseHas('expedition_logs', [
            'expedition_id' => $expedition->id,
            'event_type' => 'battery_cost',
            'message' => 'Потрачено энергии на маршрут: Battery -10%',
        ]);
    }
}
