<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationUnlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_routes_have_correct_unlock_levels_and_durations(): void
    {
        $this->seed(LocationSeeder::class);
        $this->assertDatabaseHas('locations', ['slug' => 'repair_terminal', 'required_level' => 5, 'base_duration_minutes' => 12]);
        $this->assertDatabaseHas('locations', ['slug' => 'cargo_hub', 'required_level' => 7, 'base_duration_minutes' => 16]);
    }

    public function test_locked_route_cannot_be_started_by_direct_post(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $location = Location::where('slug', 'repair_terminal')->firstOrFail();
        $battery = $user->robot->battery;

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('error');

        $this->assertSame($battery, $user->robot->fresh()->battery);
        $this->assertSame(0, $user->robot->expeditions()->count());
    }

    public function test_route_unlocks_at_required_level(): void
    {
        $this->seed(LocationSeeder::class);
        $user = User::factory()->create();
        $user->robot->update(['level' => 5, 'battery' => 120]);
        $location = Location::where('slug', 'repair_terminal')->firstOrFail();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(12, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }

    public function test_existing_routes_remain_unlocked_at_level_one(): void
    {
        $this->seed(LocationSeeder::class);
        foreach (['drone_dump', 'abandoned_factory', 'old_substation'] as $slug) {
            $this->assertSame(1, Location::where('slug', $slug)->firstOrFail()->required_level);
        }
    }
}
