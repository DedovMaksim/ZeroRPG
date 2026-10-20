<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairBayInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function player(int $integrity, int $scrap): User
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

        $resource = Resource::firstOrCreate(['slug' => 'scrap_metal'], [
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

    public function test_repair_button_is_available_with_damage_and_materials(): void
    {
        $user = $this->player(70, 15);

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertSee('Прочность робота')
            ->assertSee('70 / 100')
            ->assertSee('На SSD: 15')
            ->assertSee('Отремонтировать')
            ->assertSee('action="'.route('robot.repair').'"', false);
    }

    public function test_repair_ui_explains_missing_materials(): void
    {
        $user = $this->player(70, 0);

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertSee('Недостаточно материалов для ремонта.')
            ->assertSee('disabled', false);
    }

    public function test_repair_ui_disables_action_at_full_integrity(): void
    {
        $user = $this->player(100, 15);

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertSee('Робот полностью исправен.')
            ->assertSee('disabled', false);
    }

    public function test_repair_controls_are_not_shown_without_repair_bay(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertDontSee('action="'.route('robot.repair').'"', false);
    }
}
