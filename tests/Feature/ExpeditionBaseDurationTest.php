<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpeditionBaseDurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_routes_keep_original_base_durations(): void
    {
        $this->seed(LocationSeeder::class);

        foreach (['drone_dump' => 5, 'abandoned_factory' => 7, 'old_substation' => 9] as $slug => $minutes) {
            $this->assertSame($minutes, Location::where('slug', $slug)->firstOrFail()->base_duration_minutes);
        }
    }

    public function test_duration_is_independent_from_difficulty(): void
    {
        $location = Location::create([
            'name' => 'Long easy route',
            'slug' => 'long_easy_route',
            'difficulty' => 1,
            'battery_cost' => 10,
            'base_duration_minutes' => 22,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $expedition = $user->robot->expeditions()->firstOrFail();
        $this->assertSame(22, $expedition->duration_minutes);
        $this->assertSame(22 * 60, (int) $expedition->started_at->diffInSeconds($expedition->finished_at));
    }

    public function test_different_difficulties_can_share_same_duration(): void
    {
        $location = Location::create([
            'name' => 'Short dangerous route',
            'slug' => 'short_dangerous_route',
            'difficulty' => 3,
            'battery_cost' => 10,
            'base_duration_minutes' => 5,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('expeditions.start', $location))
            ->assertSessionHas('success');

        $this->assertSame(5, $user->robot->expeditions()->firstOrFail()->duration_minutes);
    }
}
