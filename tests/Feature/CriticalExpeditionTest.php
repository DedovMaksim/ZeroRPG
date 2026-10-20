<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Services\ExpeditionProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriticalExpeditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_emp_aborts_expedition_without_regular_rewards_and_is_idempotent(): void
    {
        $robot = User::factory()->create()->robot;
        $location = Location::create([
            'name' => 'Critical test substation',
            'slug' => 'old_substation',
            'difficulty' => 3,
            'battery_cost' => 10,
        ]);
        $expedition = $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now()->subMinute(),
        ]);

        config()->set('expedition_event_rules.trigger_chance_by_difficulty.3', 100);
        config()->set('expedition_events', [
            'critical_emp' => config('expedition_events.critical_emp'),
        ]);

        $processor = app(ExpeditionProcessor::class);
        $processor->processCompletedExpeditions($robot);

        $this->assertSame('aborted', $expedition->fresh()->status);
        $this->assertSame(0, $robot->fresh()->battery);
        $this->assertSame(0, $robot->fresh()->xp);
        $this->assertDatabaseCount('expedition_reports', 0);
        $this->assertSame(1, $expedition->logs()->where('event_type', 'critical_event')->count());

        $logCount = $expedition->logs()->count();
        $processor->processCompletedExpeditions($robot->fresh());

        $this->assertSame($logCount, $expedition->logs()->count());
        $this->assertDatabaseCount('expedition_reports', 0);
    }

    public function test_critical_event_is_not_selected_when_location_is_ineligible(): void
    {
        $robot = User::factory()->create()->robot;
        $location = Location::create([
            'name' => 'Safe test zone',
            'slug' => 'drone_dump',
            'difficulty' => 1,
            'battery_cost' => 10,
        ]);
        $expedition = $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now()->subMinute(),
        ]);
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.1', 100);
        config()->set('expedition_events', [
            'critical_emp' => config('expedition_events.critical_emp'),
        ]);

        app(ExpeditionProcessor::class)->processCompletedExpeditions($robot);

        $this->assertSame('completed', $expedition->fresh()->status);
        $this->assertSame(0, $expedition->logs()->where('event_type', 'critical_event')->count());
    }
}
