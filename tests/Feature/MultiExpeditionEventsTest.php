<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Services\ExpeditionEventRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiExpeditionEventsTest extends TestCase
{
    use RefreshDatabase;

    private function expedition(): \App\Models\Expedition
    {
        $robot = User::factory()->create()->robot;
        $location = Location::create([
            'name' => 'Event route',
            'slug' => 'old_substation',
            'difficulty' => 3,
            'battery_cost' => 10,
        ]);

        return $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 9,
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinute(),
        ]);
    }

    public function test_multiple_distinct_events_have_ordered_timestamps(): void
    {
        $expedition = $this->expedition();
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.3', 100);
        config()->set('expedition_event_rules.max_events_by_difficulty.3', 3);
        config()->set('expedition_events', [
            'unknown_signal' => config('expedition_events.unknown_signal'),
            'solar_charger' => array_merge(
                config('expedition_events.solar_charger'),
                ['locations' => ['old_substation']]
            ),
            'electrical_surge' => config('expedition_events.electrical_surge'),
        ]);

        $critical = app(ExpeditionEventRunner::class)->run($expedition);

        $this->assertFalse($critical);
        $logs = $expedition->logs()->where('event_type', 'random_event')->orderBy('event_time')->get();
        $this->assertCount(3, $logs);
        $this->assertTrue($logs[0]->event_time->lt($logs[1]->event_time));
        $this->assertTrue($logs[1]->event_time->lt($logs[2]->event_time));
    }

    public function test_critical_event_stops_remaining_slots_and_removes_future_logs(): void
    {
        $expedition = $this->expedition();
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.3', 100);
        config()->set('expedition_event_rules.max_events_by_difficulty.3', 3);
        config()->set('expedition_events', [
            'critical_emp' => config('expedition_events.critical_emp'),
        ]);

        $expedition->logs()->create([
            'minute' => 9,
            'event_type' => 'finish',
            'message' => 'Экспедиция завершена',
            'event_time' => $expedition->finished_at,
        ]);

        $critical = app(ExpeditionEventRunner::class)->run($expedition);

        $this->assertTrue($critical);
        $this->assertSame(1, $expedition->logs()->where('event_type', 'critical_event')->count());
        $this->assertSame(1, $expedition->logs()->where('event_type', 'emergency_return')->count());
        $this->assertSame(0, $expedition->logs()->where('event_type', 'finish')->count());
        $this->assertSame(0, $expedition->robot->fresh()->battery);
    }
}
