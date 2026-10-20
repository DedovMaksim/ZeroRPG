<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Services\ExpeditionLogGenerator;
use App\Services\ExpeditionProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpeditionJournalTest extends TestCase
{
    use RefreshDatabase;

    private function expedition(): \App\Models\Expedition
    {
        $robot = User::factory()->create()->robot;
        $location = Location::create([
            'name' => 'Journal test zone',
            'slug' => 'drone_dump',
            'difficulty' => 1,
            'battery_cost' => 10,
        ]);

        return $robot->expeditions()->create([
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => 5,
            'started_at' => now()->subMinutes(6),
            'finished_at' => now()->subMinute(),
        ]);
    }

    public function test_initial_journal_does_not_claim_unearned_loot_or_completion(): void
    {
        $expedition = $this->expedition();
        app(ExpeditionLogGenerator::class)->generate($expedition);

        $this->assertSame(0, $expedition->logs()->where('event_type', 'finish')->count());
        $this->assertSame(0, $expedition->logs()->where('event_type', 'loot')->count());
        $this->assertTrue(
            $expedition->logs()->orderBy('event_time')->first()->event_time
                ->lte($expedition->logs()->orderByDesc('event_time')->first()->event_time)
        );
    }

    public function test_successful_completion_writes_one_final_log(): void
    {
        $expedition = $this->expedition();
        config()->set('expedition_event_rules.trigger_chance_by_difficulty.1', 0);
        app(ExpeditionLogGenerator::class)->generate($expedition);

        $processor = app(ExpeditionProcessor::class);
        $processor->processCompletedExpeditions($expedition->robot);
        $processor->processCompletedExpeditions($expedition->robot->fresh());

        $this->assertSame('completed', $expedition->fresh()->status);
        $this->assertSame(1, $expedition->logs()->where('event_type', 'finish')->count());
        $this->assertSame(1, $expedition->logs()->where('event_type', 'battery_cost')->count());
    }
}
