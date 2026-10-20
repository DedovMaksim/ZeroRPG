<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotRepairSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function player(int $integrity = 60, int $scrap = 20): User
    {
        $user = User::factory()->create();
        $user->robot->update(['integrity' => $integrity]);

        $resource = Resource::firstOrCreate(['slug' => 'scrap_metal'], [
            'name' => 'Металлолом',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);

        $user->robot->inventories()->create([
            'resource_id' => $resource->id,
            'amount' => $scrap,
        ]);

        return $user;
    }

    private function bay(User $user, string $status = 'active'): void
    {
        $user->robot->base->buildings()->create([
            'key' => 'repair_bay',
            'name' => 'Ремонтный отсек',
            'level' => 1,
            'status' => $status,
            'capacity' => 0,
        ]);
    }

    public function test_guest_cannot_repair(): void
    {
        $this->post(route('robot.repair'))->assertRedirect(route('login'));
    }

    public function test_inactive_repair_bay_does_not_allow_repair_or_spend(): void
    {
        $user = $this->player();
        $this->bay($user, 'inactive');

        $this->actingAs($user)->post(route('robot.repair'))->assertSessionHas('error');

        $this->assertSame(60, $user->robot->fresh()->integrity);
        $this->assertSame(20, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_other_players_repair_bay_does_not_grant_access(): void
    {
        $owner = $this->player();
        $visitor = $this->player();
        $this->bay($owner);

        $this->actingAs($visitor)->post(route('robot.repair'))->assertSessionHas('error');

        $this->assertSame(60, $visitor->robot->fresh()->integrity);
        $this->assertSame(20, $visitor->robot->inventories()->firstOrFail()->amount);
    }

    public function test_invalid_cost_does_not_grant_free_repair(): void
    {
        $user = $this->player();
        $this->bay($user);
        config()->set('repair.cost', 0);

        $this->actingAs($user)->post(route('robot.repair'))->assertSessionHas('error');

        $this->assertSame(60, $user->robot->fresh()->integrity);
        $this->assertSame(20, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_invalid_recovery_does_not_spend_materials(): void
    {
        $user = $this->player();
        $this->bay($user);
        config()->set('repair.integrity_per_repair', -10);

        $this->actingAs($user)->post(route('robot.repair'))->assertSessionHas('error');

        $this->assertSame(60, $user->robot->fresh()->integrity);
        $this->assertSame(20, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_missing_resource_definition_does_not_change_robot(): void
    {
        $user = $this->player();
        $this->bay($user);
        config()->set('repair.resource', 'missing_repair_material');

        $this->actingAs($user)->post(route('robot.repair'))->assertSessionHas('error');

        $this->assertSame(60, $user->robot->fresh()->integrity);
        $this->assertSame(20, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_repair_ignores_client_supplied_cost_and_amount(): void
    {
        $user = $this->player();
        $this->bay($user);

        $this->actingAs($user)->post(route('robot.repair'), [
            'cost' => 0,
            'integrity_per_repair' => 999,
            'robot_id' => 999,
        ])->assertSessionHas('success');

        $this->assertSame(70, $user->robot->fresh()->integrity);
        $this->assertSame(15, $user->robot->inventories()->firstOrFail()->amount);
    }
}
