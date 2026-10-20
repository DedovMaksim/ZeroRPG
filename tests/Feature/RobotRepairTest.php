<?php

namespace Tests\Feature;

use App\Models\Expedition;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotRepairTest extends TestCase
{
    use RefreshDatabase;

    private function repairBay(User $user): void
    {
        $user->robot->base->buildings()->create([
            'key' => 'repair_bay',
            'name' => 'Ремонтный отсек',
            'level' => 1,
            'status' => 'active',
            'capacity' => 0,
        ]);
    }

    public function test_repair_requires_active_repair_bay(): void
    {
        $user = User::factory()->create();
        $user->robot->update(['integrity' => 60]);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('error');

        $this->assertSame(60, $user->robot->fresh()->integrity);
    }

    public function test_repair_restores_ten_integrity(): void
    {
        $user = User::factory()->create();
        $this->repairBay($user);
        $user->robot->update(['integrity' => 60]);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('success');

        $this->assertSame(70, $user->robot->fresh()->integrity);
    }

    public function test_repair_does_not_exceed_maximum_integrity(): void
    {
        $user = User::factory()->create();
        $this->repairBay($user);
        $user->robot->update(['integrity' => 98]);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('success');

        $this->assertSame(100, $user->robot->fresh()->integrity);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('error');
        $this->assertSame(100, $user->robot->fresh()->integrity);
    }

    public function test_maximum_integrity_scales_with_robot_level(): void
    {
        $user = User::factory()->create();
        $this->repairBay($user);
        $user->robot->update(['level' => 3, 'integrity' => 105]);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('success');

        $this->assertSame(110, $user->robot->fresh()->integrity);
    }

    public function test_repair_is_blocked_during_expedition(): void
    {
        $user = User::factory()->create();
        $this->repairBay($user);
        $user->robot->update(['integrity' => 50]);

        $location = Location::create([
            'name' => 'Repair test location',
            'slug' => 'repair_test_location',
            'difficulty' => 1,
            'battery_cost' => 10,
        ]);

        Expedition::create([
            'robot_id' => $user->robot->id,
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 5,
            'started_at' => now(),
            'finished_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('error');

        $this->assertSame(50, $user->robot->fresh()->integrity);
    }

    public function test_repair_does_not_affect_other_users_robot(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->repairBay($owner);
        $owner->robot->update(['integrity' => 40]);
        $other->robot->update(['integrity' => 30]);

        $this->actingAs($owner)->post(route('robot.repair'))
            ->assertSessionHas('success');

        $this->assertSame(50, $owner->robot->fresh()->integrity);
        $this->assertSame(30, $other->robot->fresh()->integrity);
    }
}
