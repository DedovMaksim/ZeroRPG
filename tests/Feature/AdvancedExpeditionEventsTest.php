<?php

namespace Tests\Feature;

use App\Services\ExpeditionBalanceSimulator;
use App\Services\ExpeditionEventCatalog;
use Tests\TestCase;

class AdvancedExpeditionEventsTest extends TestCase
{
    public function test_every_configured_location_has_positive_negative_and_neutral_events(): void
    {
        $catalog = app(ExpeditionEventCatalog::class);

        foreach (config('locations') as $slug => $location) {
            $events = $catalog->eligible($slug, (int) $location['difficulty']);
            $this->assertNotEmpty($events, 'No eligible events for '.$slug);
            $categories = array_unique(array_column($events, 'category'));
            foreach (['positive', 'negative', 'neutral'] as $category) {
                $this->assertContains($category, $categories, $slug.' lacks '.$category.' events');
            }
        }
    }

    public function test_advanced_routes_generate_events_and_damage(): void
    {
        $simulator = app(ExpeditionBalanceSimulator::class);
        $routes = [
            'repair_terminal', 'cargo_hub', 'research_sector', 'data_archive',
            'security_perimeter', 'assembly_plant', 'dead_comms_zone', 'old_network_node',
        ];

        foreach ($routes as $slug) {
            $result = $simulator->simulate($slug, 1000, 42, ['ssd' => 32, 'cpu' => 1]);
            $this->assertGreaterThan(0, $result['events_per_run'], $slug.' generated no events');
            $this->assertGreaterThan(0, $result['integrity_loss_per_run'], $slug.' generated no damage');
            $this->assertNotEmpty($result['event_counts']);
        }
    }

    public function test_final_routes_can_trigger_critical_abort(): void
    {
        foreach (['dead_comms_zone', 'old_network_node'] as $slug) {
            $result = app(ExpeditionBalanceSimulator::class)->simulate($slug, 1000, 42, ['ssd' => 32, 'cpu' => 1]);
            $this->assertGreaterThan(0, $result['abort_percent'], $slug.' never aborted');
        }
    }

    public function test_high_difficulty_routes_do_not_force_all_events(): void
    {
        $catalog = app(ExpeditionEventCatalog::class);
        $this->assertSame(2, $catalog->maxEvents(4));
        $this->assertSame(2, $catalog->maxEvents(5));

        foreach (['security_perimeter', 'assembly_plant', 'dead_comms_zone', 'old_network_node'] as $slug) {
            $difficulty = (int) config('locations.'.$slug.'.difficulty');
            $this->assertLessThan(
                count($catalog->eligible($slug, $difficulty)),
                $catalog->maxEvents($difficulty),
                $slug.' should not exhaust its entire event pool'
            );
        }
    }

    public function test_final_route_abort_rate_stays_within_playable_range(): void
    {
        foreach (['dead_comms_zone', 'old_network_node'] as $slug) {
            $result = app(ExpeditionBalanceSimulator::class)->simulate($slug, 10000, 42, ['ssd' => 32, 'cpu' => 1]);
            $this->assertGreaterThanOrEqual(5, $result['abort_percent'], $slug.' should remain risky');
            $this->assertLessThanOrEqual(20, $result['abort_percent'], $slug.' should remain playable');
        }
    }

    public function test_all_event_resources_exist_in_resource_config(): void
    {
        foreach (config('expedition_events') as $key => $event) {
            if (($event['effect']['type'] ?? null) === 'resource') {
                $this->assertArrayHasKey($event['effect']['resource'], config('resources'), $key);
            }
        }
    }
}
