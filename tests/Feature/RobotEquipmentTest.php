<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function module(string $slug, string $type, int $price = 25, array $bonuses = []): Item
    {
        return Item::create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'type' => $type,
            'price' => $price,
        ], $bonuses));
    }

    public function test_robot_can_buy_a_module(): void
    {
        $user = User::factory()->create();
        $user->robot->update(['scraps' => 50]);
        $item = $this->module('cpu-mk1', 'cpu_module');

        $this->actingAs($user)->post(route('items.buy', $item))
            ->assertSessionHas('success');

        $this->assertSame(25, $user->robot->fresh()->scraps);
        $this->assertDatabaseHas('robot_items', [
            'robot_id' => $user->robot->id,
            'item_id' => $item->id,
            'equipped' => 0,
        ]);
    }

    public function test_insufficient_scraps_prevents_purchase(): void
    {
        $user = User::factory()->create();
        $user->robot->update(['scraps' => 10]);
        $item = $this->module('cpu-mk1', 'cpu_module');

        $this->actingAs($user)->post(route('items.buy', $item))
            ->assertSessionHas('error');

        $this->assertSame(10, $user->robot->fresh()->scraps);
        $this->assertDatabaseCount('robot_items', 0);
    }

    public function test_equipping_module_replaces_only_same_type(): void
    {
        $user = User::factory()->create();
        $first = $user->robot->robotItems()->create([
            'item_id' => $this->module('cpu-mk1', 'cpu_module')->id,
            'equipped' => true,
        ]);
        $second = $user->robot->robotItems()->create([
            'item_id' => $this->module('cpu-mk2', 'cpu_module')->id,
            'equipped' => false,
        ]);
        $ram = $user->robot->robotItems()->create([
            'item_id' => $this->module('ram-mk1', 'ram_module')->id,
            'equipped' => true,
        ]);

        $this->actingAs($user)->post(route('robot-items.equip', $second))
            ->assertSessionHas('success');

        $this->assertFalse($first->fresh()->equipped);
        $this->assertTrue($second->fresh()->equipped);
        $this->assertTrue($ram->fresh()->equipped);
    }

    public function test_other_users_cannot_change_equipment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $module = $owner->robot->robotItems()->create([
            'item_id' => $this->module('cpu-mk1', 'cpu_module')->id,
            'equipped' => false,
        ]);

        $this->actingAs($other)
            ->post(route('robot-items.equip', $module))
            ->assertForbidden();

        $module->update(['equipped' => true]);

        $this->actingAs($other)
            ->post(route('robot-items.unequip', $module))
            ->assertForbidden();

        $this->assertTrue($module->fresh()->equipped);
    }

    public function test_equipped_bonuses_change_stats_and_unequip_restores_storage(): void
    {
        $user = User::factory()->create();
        $robot = $user->robot;
        $robot->robotItems()->create([
            'item_id' => $this->module('cpu-mk1', 'cpu_module', 25, ['cpu_bonus' => 10])->id,
            'equipped' => true,
        ]);
        $robot->robotItems()->create([
            'item_id' => $this->module('ram-mk1', 'ram_module', 30, ['ram_bonus' => 4])->id,
            'equipped' => true,
        ]);
        $ssd = $robot->robotItems()->create([
            'item_id' => $this->module('ssd-mk1', 'ssd_module', 40, ['ssd_bonus' => 8])->id,
            'equipped' => true,
        ]);

        $this->assertSame(11, $robot->stats()->cpu($robot));
        $this->assertSame(8, $robot->stats()->ram($robot));
        $this->assertSame(16, $robot->totalStorage());
        $this->assertSame(1, $robot->stats()->lootBonus($robot));

        $this->actingAs($user)->post(route('robot-items.unequip', $ssd))
            ->assertSessionHas('success');

        $this->assertSame(8, $robot->totalStorage());
    }
}
