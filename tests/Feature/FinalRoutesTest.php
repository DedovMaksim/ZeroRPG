<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Services\ExpeditionBalanceSimulator;
use App\Services\LootPrioritySorter;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ResourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinalRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_eleven_routes_and_final_resources_are_seeded(): void
    {
        $this->seed([ResourceSeeder::class, LocationSeeder::class]);

        $this->assertSame(11, Location::count());
        $this->assertDatabaseHas('resources', ['slug' => 'ai_core_fragments', 'storage_size' => 10, 'scrap_value' => 25]);
        $this->assertDatabaseHas('resources', ['slug' => 'archive_modules', 'storage_size' => 8, 'scrap_value' => 20]);
        $this->assertDatabaseHas('locations', ['slug' => 'dead_comms_zone', 'difficulty' => 5, 'required_level' => 17, 'base_duration_minutes' => 65]);
        $this->assertDatabaseHas('locations', ['slug' => 'old_network_node', 'difficulty' => 5, 'required_level' => 19, 'base_duration_minutes' => 85]);
    }

    public function test_dead_comms_zone_requires_level_seventeen(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 16, 'battery' => 185]);
        $location = Location::where('slug', 'dead_comms_zone')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $this->assertSame(185, $user->robot->fresh()->battery);
        $this->assertSame(0, $user->robot->expeditions()->count());

        $user->robot->update(['level' => 17]);
        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(65, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_old_network_node_requires_level_nineteen(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 18, 'battery' => 190]);
        $location = Location::where('slug', 'old_network_node')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $user->robot->update(['level' => 19]);
        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(85, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_final_resources_have_highest_loot_priority(): void
    {
        $sorted = app(LootPrioritySorter::class)->sort([
            ['resource' => 'Электроника', 'amount' => 2],
            ['resource' => 'Архивные модули', 'amount' => 1],
            ['resource' => 'Фрагменты ядра ИИ', 'amount' => 1],
        ]);

        $this->assertSame(['Фрагменты ядра ИИ', 'Архивные модули', 'Электроника'], array_column($sorted, 'resource'));
    }

    public function test_simulator_supports_both_final_routes(): void
    {
        foreach (['dead_comms_zone', 'old_network_node'] as $slug) {
            $result = app(ExpeditionBalanceSimulator::class)->simulate($slug, 20, 42, ['ssd' => 32, 'cpu' => 17]);
            $this->assertSame($slug, $result['location']);
            $this->assertSame(20, $result['runs']);
        }
    }
}
