<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConstructionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function setupProject(User $user, int $required = 5): array
    {
        $resource = Resource::create([
            'name' => 'Construction test metal',
            'slug' => 'construction-test-metal',
            'storage_size' => 1,
            'scrap_value' => 1,
        ]);

        $project = $user->robot->base->constructionProjects()->create([
            'key' => 'warehouse',
            'name' => 'Test warehouse',
            'description' => 'Test construction',
            'status' => 'in_progress',
        ]);

        $requirement = $project->requirements()->create([
            'resource_id' => $resource->id,
            'required_amount' => $required,
            'delivered_amount' => 0,
        ]);

        return [$resource, $project, $requirement];
    }

    public function test_other_players_cannot_transfer_resources_to_a_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$resource, $project, $requirement] = $this->setupProject($owner);
        $inventory = $stranger->robot->inventories()->create([
            'resource_id' => $resource->id,
            'amount' => 5,
        ]);

        $this->actingAs($stranger)
            ->post(route('construction.transfer', $requirement), ['amount' => 3])
            ->assertForbidden();

        $this->assertSame(5, $inventory->fresh()->amount);
        $this->assertSame(0, $requirement->fresh()->delivered_amount);
        $this->assertSame('in_progress', $project->fresh()->status);
    }

    public function test_completed_projects_cannot_consume_more_resources(): void
    {
        $user = User::factory()->create();
        [$resource, $project, $requirement] = $this->setupProject($user);
        $project->update(['status' => 'completed']);
        $inventory = $user->robot->inventories()->create([
            'resource_id' => $resource->id,
            'amount' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('construction.transfer', $requirement), ['amount' => 3])
            ->assertSessionHas('error');

        $this->assertSame(5, $inventory->fresh()->amount);
        $this->assertSame(0, $requirement->fresh()->delivered_amount);
    }

    public function test_transfer_is_limited_by_remaining_requirement_and_finishes_warehouse(): void
    {
        $user = User::factory()->create();
        [$resource, $project, $requirement] = $this->setupProject($user, 3);
        $inventory = $user->robot->inventories()->create([
            'resource_id' => $resource->id,
            'amount' => 8,
        ]);

        $this->actingAs($user)
            ->post(route('construction.transfer', $requirement), ['amount' => 8])
            ->assertSessionHas('success');

        $this->assertSame(5, $inventory->fresh()->amount);
        $this->assertSame(3, $requirement->fresh()->delivered_amount);
        $this->assertSame('completed', $project->fresh()->status);
        $this->assertDatabaseHas('buildings', [
            'base_id' => $user->robot->base->id,
            'key' => 'warehouse',
            'capacity' => 40,
        ]);

        $this->actingAs($user)
            ->post(route('construction.transfer', $requirement), ['amount' => 1])
            ->assertSessionHas('error');

        $this->assertSame(5, $inventory->fresh()->amount);
        $this->assertDatabaseCount('buildings', 1);
    }

    public function test_transfer_requires_positive_integer_amount(): void
    {
        $user = User::factory()->create();
        [$resource, , $requirement] = $this->setupProject($user);
        $inventory = $user->robot->inventories()->create([
            'resource_id' => $resource->id,
            'amount' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('construction.transfer', $requirement), ['amount' => -1])
            ->assertSessionHasErrors('amount');

        $this->assertSame(5, $inventory->fresh()->amount);
        $this->assertSame(0, $requirement->fresh()->delivered_amount);
    }
}
