<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ResourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingConstructionTest extends TestCase
{
    use RefreshDatabase;

    private function addWarehouse(User $user): void
    {
        $user->robot->base->buildings()->create([
            'key' => 'warehouse',
            'name' => 'Склад',
            'level' => 1,
            'status' => 'active',
            'capacity' => 40,
        ]);
    }

    public function test_unknown_building_is_not_available(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('buildings.construct', 'unknown'))
            ->assertNotFound();
    }

    public function test_repair_bay_requires_warehouse(): void
    {
        $user = User::factory()->create();
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('buildings.construct', 'repair_bay'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('construction_projects', 0);
    }

    public function test_repair_bay_project_cannot_be_created_twice(): void
    {
        $user = User::factory()->create();
        $this->addWarehouse($user);
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('buildings.construct', 'repair_bay'))
            ->assertSessionHas('success');
        $this->actingAs($user)->post(route('buildings.construct', 'repair_bay'))
            ->assertSessionHas('error');

        $project = $user->robot->base->constructionProjects()
            ->where('key', 'repair_bay')->firstOrFail();

        $this->assertSame(3, $project->requirements()->count());
        $this->assertSame(
            ['copper' => 5, 'electronics' => 2, 'scrap_metal' => 15],
            $project->requirements()->with('resource')->get()
                ->mapWithKeys(fn ($requirement) => [$requirement->resource->slug => $requirement->required_amount])
                ->sortKeys()->all()
        );
    }

    public function test_delivering_resources_completes_repair_bay(): void
    {
        $user = User::factory()->create();
        $this->addWarehouse($user);
        $this->seed(ResourceSeeder::class);

        $this->actingAs($user)->post(route('buildings.construct', 'repair_bay'))
            ->assertSessionHas('success');

        $project = $user->robot->base->constructionProjects()
            ->where('key', 'repair_bay')->firstOrFail();

        foreach ($project->requirements()->get() as $requirement) {
            $user->robot->inventories()->create([
                'resource_id' => $requirement->resource_id,
                'amount' => $requirement->required_amount,
            ]);

            $this->actingAs($user)
                ->post(route('construction.transfer', $requirement), [
                    'amount' => $requirement->required_amount,
                ])->assertSessionHas('success');
        }

        $this->assertSame('completed', $project->fresh()->status);
        $this->assertDatabaseHas('buildings', [
            'base_id' => $user->robot->base->id,
            'key' => 'repair_bay',
            'level' => 1,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('buildings', 2);
    }
}
