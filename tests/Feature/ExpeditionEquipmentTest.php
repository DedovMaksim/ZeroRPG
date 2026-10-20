<?php

namespace Tests\Feature;

use App\Models\Expedition;
use App\Models\Item;
use App\Models\Location;
use App\Models\Resource;
use App\Models\User;
use App\Services\ExpeditionLootGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpeditionEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function module(User $user, string $type, array $bonuses): void
    {
        $item = Item::create(array_merge([
            'name' => $type,
            'slug' => $type,
            'type' => $type,
            'price' => 10,
        ], $bonuses));

        $user->robot->robotItems()->create([
            'item_id' => $item->id,
            'equipped' => true,
        ]);
    }

    private function location(string $slug = 'test_zone'): Location
    {
        return Location::create([
            'name' => 'Test zone',
            'slug' => $slug,
            'difficulty' => 1,
            'battery_cost' => 10,
        ]);
    }

    private function expedition(User $user, Location $location): Expedition
    {
        return Expedition::create([
            'robot_id' => $user->robot->id,
            'location_id' => $location->id,
            'status' => 'completed',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now(),
        ]);
    }

    private function fixedLoot(string $slug, string $resource, int $amount): void
    {
        config()->set("locations.{$slug}.loot", [[
            'resource' => $resource,
            'chance' => 100,
            'min' => $amount,
            'max' => $amount,
        ]]);
    }

    public function test_ram_module_reduces_expedition_duration(): void
    {
        $location = $this->location();
        $normal = User::factory()->create();
        $upgraded = User::factory()->create();

        $this->module($upgraded, 'ram_module', ['ram_bonus' => 12]);

        $this->actingAs($normal)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');
        $this->actingAs($upgraded)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $normalExpedition = $normal->robot->expeditions()->firstOrFail();
        $upgradedExpedition = $upgraded->robot->expeditions()->firstOrFail();

        $normalDuration = $normalExpedition->started_at->diffInSeconds($normalExpedition->finished_at);
        $upgradedDuration = $upgradedExpedition->started_at->diffInSeconds($upgradedExpedition->finished_at);

        $this->assertLessThan($normalDuration, $upgradedDuration);
    }

    public function test_cpu_module_increases_deterministic_loot_amount(): void
    {
        $user = User::factory()->create();
        $location = $this->location();
        $resource = Resource::create([
            'name' => 'Test metal',
            'slug' => 'test-metal',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);
        $this->fixedLoot($location->slug, $resource->name, 1);
        $user->robot->update(['ssd' => 30]);

        $this->module($user, 'cpu_module', ['cpu_bonus' => 10]);

        $report = app(ExpeditionLootGenerator::class)->generate($this->expedition($user, $location));

        $this->assertSame([['resource' => 'Test metal', 'amount' => 2]], $report);
        $this->assertDatabaseHas('inventories', [
            'robot_id' => $user->robot->id,
            'resource_id' => $resource->id,
            'amount' => 2,
        ]);
    }

    public function test_ssd_module_increases_available_loot_capacity(): void
    {
        $user = User::factory()->create();
        $location = $this->location();
        $resource = Resource::create([
            'name' => 'Test copper',
            'slug' => 'test-copper',
            'storage_size' => 2,
            'scrap_value' => 1,
        ]);
        $this->fixedLoot($location->slug, $resource->name, 6);
        $this->module($user, 'ssd_module', ['ssd_bonus' => 8]);

        $report = app(ExpeditionLootGenerator::class)->generate($this->expedition($user, $location));

        $this->assertSame([['resource' => 'Test copper', 'amount' => 6]], $report);
        $this->assertSame(12, $user->robot->usedStorage());
        $this->assertSame(16, $user->robot->totalStorage());
    }

    public function test_loot_is_capped_by_free_storage_and_logs_partial_pickup(): void
    {
        $user = User::factory()->create();
        $location = $this->location();
        $resource = Resource::create([
            'name' => 'Test scrap',
            'slug' => 'test-scrap',
            'storage_size' => 2,
            'scrap_value' => 1,
        ]);
        $this->fixedLoot($location->slug, $resource->name, 6);

        $report = app(ExpeditionLootGenerator::class)->generate($this->expedition($user, $location));

        $this->assertSame([['resource' => 'Test scrap', 'amount' => 4]], $report);
        $this->assertSame(8, $user->robot->usedStorage());
        $this->assertDatabaseHas('expedition_logs', [
            'event_type' => 'loot_partial',
        ]);
    }

    public function test_full_ssd_prevents_additional_loot(): void
    {
        $user = User::factory()->create();
        $location = $this->location();
        $resource = Resource::create([
            'name' => 'Test wires',
            'slug' => 'test-wires',
            'storage_size' => 2,
            'scrap_value' => 1,
        ]);
        $user->robot->inventories()->create([
            'resource_id' => $resource->id,
            'amount' => 4,
        ]);
        $this->fixedLoot($location->slug, $resource->name, 3);

        $report = app(ExpeditionLootGenerator::class)->generate($this->expedition($user, $location));

        $this->assertSame([], $report);
        $this->assertSame(8, $user->robot->usedStorage());
        $this->assertDatabaseHas('expedition_logs', [
            'event_type' => 'storage_full',
        ]);
    }
}
