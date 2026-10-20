<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function warehouse(User $user): void
    {
        $user->robot->base->buildings()->create([
            'key' => 'warehouse',
            'name' => 'Склад',
            'level' => 1,
            'status' => 'active',
            'capacity' => 40,
        ]);
    }

    public function test_upgrade_requires_existing_warehouse(): void
    {
        $user = User::factory()->create();
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('warehouse.upgrade'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('construction_projects', 0);
    }

    public function test_upgrade_project_is_created_only_once_with_correct_cost(): void
    {
        $user = User::factory()->create();
        $this->warehouse($user);
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('warehouse.upgrade'))
            ->assertSessionHas('success');
        $this->actingAs($user)->post(route('warehouse.upgrade'))
            ->assertSessionHas('error');

        $project = $user->robot->base->constructionProjects()
            ->where('key', 'warehouse_upgrade_2')->firstOrFail();

        $this->assertSame('in_progress', $project->status);
        $this->assertSame(3, $project->requirements()->count());
        $this->assertSame(
            ['copper' => 8, 'electronics' => 2, 'scrap_metal' => 20],
            $project->requirements()->with('resource')->get()
                ->mapWithKeys(fn ($requirement) => [$requirement->resource->slug => $requirement->required_amount])
                ->sortKeys()->all()
        );
    }

    public function test_delivering_all_materials_upgrades_warehouse_to_level_two(): void
    {
        $user = User::factory()->create();
        $this->warehouse($user);
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('warehouse.upgrade'))
            ->assertSessionHas('success');

        $project = $user->robot->base->constructionProjects()
            ->where('key', 'warehouse_upgrade_2')->firstOrFail();

        foreach ($project->requirements()->with('resource')->get() as $requirement) {
            $user->robot->inventories()->create([
                'resource_id' => $requirement->resource_id,
                'amount' => $requirement->required_amount,
            ]);

            $this->actingAs($user)
                ->post(route('construction.transfer', $requirement), [
                    'amount' => $requirement->required_amount,
                ])->assertSessionHas('success');
        }

        $warehouse = $user->robot->base->buildings()->where('key', 'warehouse')->firstOrFail();

        $this->assertSame(2, $warehouse->level);
        $this->assertSame(80, $warehouse->capacity);
        $this->assertSame('completed', $project->fresh()->status);
        $this->assertDatabaseCount('buildings', 1);
    }

    public function test_upgraded_warehouse_cannot_start_another_level_two_project(): void
    {
        $user = User::factory()->create();
        $this->warehouse($user);
        $user->robot->base->buildings()->where('key', 'warehouse')
            ->update(['level' => 2, 'capacity' => 80]);
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('warehouse.upgrade'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('construction_projects', 0);
    }
}
