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

class IndustrialRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_industrial_routes_and_resources_are_seeded(): void
    {
        $this->seed([ResourceSeeder::class, LocationSeeder::class]);

        $this->assertDatabaseHas('resources', ['slug' => 'servos', 'storage_size' => 8, 'scrap_value' => 18]);
        $this->assertDatabaseHas('resources', ['slug' => 'composite_plates', 'storage_size' => 6, 'scrap_value' => 15]);
        $this->assertDatabaseHas('locations', ['slug' => 'security_perimeter', 'difficulty' => 4, 'required_level' => 13, 'base_duration_minutes' => 38]);
        $this->assertDatabaseHas('locations', ['slug' => 'assembly_plant', 'difficulty' => 4, 'required_level' => 15, 'base_duration_minutes' => 48]);
    }

    public function test_security_perimeter_is_locked_before_level_thirteen(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 12, 'battery' => 160]);
        $location = Location::where('slug', 'security_perimeter')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $this->assertSame(160, $user->robot->fresh()->battery);
        $this->assertSame(0, $user->robot->expeditions()->count());
    }

    public function test_security_perimeter_unlocks_at_level_thirteen(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 13, 'battery' => 160]);
        $location = Location::where('slug', 'security_perimeter')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(38, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_assembly_plant_unlocks_at_level_fifteen(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 14, 'battery' => 170]);
        $location = Location::where('slug', 'assembly_plant')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $user->robot->update(['level' => 15]);
        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(48, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_industrial_resources_take_priority_over_scrap(): void
    {
        $sorted = app(LootPrioritySorter::class)->sort([
            ['resource' => 'Металлолом', 'amount' => 5],
            ['resource' => 'Композитные пластины', 'amount' => 1],
            ['resource' => 'Сервоприводы', 'amount' => 1],
        ]);

        $this->assertSame(['Сервоприводы', 'Композитные пластины', 'Металлолом'], array_column($sorted, 'resource'));
    }

    public function test_simulator_handles_industrial_routes(): void
    {
        foreach (['security_perimeter', 'assembly_plant'] as $slug) {
            $result = app(ExpeditionBalanceSimulator::class)->simulate($slug, 20, 42, ['ssd' => 32, 'cpu' => 13]);
            $this->assertSame($slug, $result['location']);
            $this->assertSame(20, $result['runs']);
        }
    }
}
