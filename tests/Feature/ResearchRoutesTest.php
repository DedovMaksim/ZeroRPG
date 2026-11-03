<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Resource;
use App\Models\User;
use App\Services\ExpeditionBalanceSimulator;
use App\Services\LootPrioritySorter;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ResourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_resources_and_routes_are_seeded(): void
    {
        $this->seed([ResourceSeeder::class, LocationSeeder::class]);

        $this->assertDatabaseHas('resources', ['slug' => 'microchips', 'storage_size' => 5, 'scrap_value' => 10]);
        $this->assertDatabaseHas('resources', ['slug' => 'data_storage', 'storage_size' => 7, 'scrap_value' => 14]);
        $this->assertDatabaseHas('locations', ['slug' => 'research_sector', 'required_level' => 9, 'base_duration_minutes' => 22]);
        $this->assertDatabaseHas('locations', ['slug' => 'data_archive', 'required_level' => 11, 'base_duration_minutes' => 28]);
    }

    public function test_research_route_is_locked_before_level_nine(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 8, 'battery' => 140]);
        $location = Location::where('slug', 'research_sector')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $this->assertSame(0, $user->robot->expeditions()->count());
        $this->assertSame(140, $user->robot->fresh()->battery);
    }

    public function test_research_route_can_start_at_level_nine(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 9, 'battery' => 140]);
        $location = Location::where('slug', 'research_sector')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(22, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_archive_unlocks_at_level_eleven(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $location = Location::where('slug', 'data_archive')->firstOrFail();
        $user->robot->update(['level' => 10, 'battery' => 150]);

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $user->robot->update(['level' => 11]);
        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');
        $this->assertSame(28, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_research_resources_are_prioritized_before_scrap(): void
    {
        $sorted = app(LootPrioritySorter::class)->sort([
            ['resource' => 'Металлолом', 'amount' => 2],
            ['resource' => 'Накопители данных', 'amount' => 1],
            ['resource' => 'Микрочипы', 'amount' => 1],
        ]);

        $this->assertSame(['Микрочипы', 'Накопители данных', 'Металлолом'], array_column($sorted, 'resource'));
    }

    public function test_simulator_recognizes_both_research_routes(): void
    {
        foreach (['research_sector', 'data_archive'] as $slug) {
            $result = app(ExpeditionBalanceSimulator::class)->simulate($slug, 20, 42, ['ssd' => 32, 'cpu' => 9]);
            $this->assertSame($slug, $result['location']);
            $this->assertSame(20, $result['runs']);
        }
    }
}
