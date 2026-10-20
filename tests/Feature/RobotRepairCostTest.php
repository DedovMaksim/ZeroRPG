<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotRepairCostTest extends TestCase
{
    use RefreshDatabase;

    private function setupRepair(int $scrap, int $integrity): User
    {
        $user = User::factory()->create();
        $user->robot->update(['integrity' => $integrity]);
        $user->robot->base->buildings()->create([
            'key' => 'repair_bay',
            'name' => 'Ремонтный отсек',
            'level' => 1,
            'status' => 'active',
            'capacity' => 0,
        ]);

        $resource = Resource::create([
            'slug' => 'scrap_metal',
            'name' => 'Металлолом',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);

        if ($scrap > 0) {
            $user->robot->inventories()->create([
                'resource_id' => $resource->id,
                'amount' => $scrap,
            ]);
        }

        return $user;
    }

    public function test_repair_consumes_five_scrap_and_restores_ten_integrity(): void
    {
        $user = $this->setupRepair(20, 65);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('success');

        $this->assertSame(75, $user->robot->fresh()->integrity);
        $this->assertSame(15, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_insufficient_scrap_prevents_repair_and_consumption(): void
    {
        $user = $this->setupRepair(4, 65);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('error');

        $this->assertSame(65, $user->robot->fresh()->integrity);
        $this->assertSame(4, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_exact_scrap_stack_is_deleted_after_repair(): void
    {
        $user = $this->setupRepair(5, 99);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('success');

        $this->assertSame(100, $user->robot->fresh()->integrity);
        $this->assertSame(0, $user->robot->inventories()->count());
    }

    public function test_full_integrity_does_not_spend_materials(): void
    {
        $user = $this->setupRepair(20, 100);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('error');

        $this->assertSame(20, $user->robot->inventories()->firstOrFail()->amount);
    }

    public function test_missing_material_stack_does_not_repair(): void
    {
        $user = $this->setupRepair(0, 65);

        $this->actingAs($user)->post(route('robot.repair'))
            ->assertSessionHas('error');

        $this->assertSame(65, $user->robot->fresh()->integrity);
    }
}
