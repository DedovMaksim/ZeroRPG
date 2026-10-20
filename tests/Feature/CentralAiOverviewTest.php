<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralAiOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_overview_shows_missing_buildings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertSee('Состояние базы')
            ->assertSee('Постройки базы')
            ->assertSee('Не построен')
            ->assertSee('Аварийный режим');
    }

    public function test_base_overview_shows_warehouse_and_upgrade_action(): void
    {
        $user = User::factory()->create();
        $user->robot->base->buildings()->create([
            'key' => 'warehouse',
            'name' => 'Склад',
            'level' => 1,
            'status' => 'active',
            'capacity' => 40,
        ]);

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertSee('Уровень 1')
            ->assertSee('Улучшить склад')
            ->assertSee('Начать строительство');
    }

    public function test_base_overview_shows_finished_warehouse_and_repair_bay(): void
    {
        $user = User::factory()->create();
        $base = $user->robot->base;

        $base->buildings()->create([
            'key' => 'warehouse',
            'name' => 'Склад',
            'level' => 2,
            'status' => 'active',
            'capacity' => 80,
        ]);

        $base->buildings()->create([
            'key' => 'repair_bay',
            'name' => 'Ремонтный отсек',
            'level' => 1,
            'status' => 'active',
            'capacity' => 0,
        ]);

        $this->actingAs($user)->get(route('central-ai'))
            ->assertOk()
            ->assertSee('Вместимость 0 / 80')
            ->assertSee('Максимальный доступный уровень достигнут')
            ->assertSee('Прочность робота')
            ->assertSee('Отремонтировать')
            ->assertSee('Робот полностью исправен')
            ->assertDontSee('Улучшить склад');
    }

    public function test_base_overview_does_not_show_other_players_projects(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $owner->robot->base->constructionProjects()->create([
            'key' => 'repair_bay',
            'name' => 'Private construction project',
            'description' => 'Private details',
            'status' => 'in_progress',
        ]);

        $this->actingAs($viewer)->get(route('central-ai'))
            ->assertOk()
            ->assertDontSee('Private construction project');
    }
}
