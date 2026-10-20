<?php

namespace Tests\Unit;

use App\Services\ExpeditionEventCatalog;
use Tests\TestCase;

class ExpeditionEventCatalogTest extends TestCase
{
    public function test_event_catalog_contains_all_three_categories(): void
    {
        $categories = array_column(config('expedition_events'), 'category');

        $this->assertContains('positive', $categories);
        $this->assertContains('negative', $categories);
        $this->assertContains('neutral', $categories);
    }

    public function test_events_are_restricted_to_their_locations(): void
    {
        $catalog = app(ExpeditionEventCatalog::class);

        $this->assertArrayHasKey('wasteland_mechanic', $catalog->eligible('drone_dump', 1));
        $this->assertArrayNotHasKey('wasteland_mechanic', $catalog->eligible('old_substation', 3));
        $this->assertArrayHasKey('electrical_surge', $catalog->eligible('old_substation', 3));
        $this->assertArrayNotHasKey('electrical_surge', $catalog->eligible('abandoned_factory', 3));
    }

    public function test_difficulty_filters_critical_events(): void
    {
        $catalog = app(ExpeditionEventCatalog::class);

        $this->assertArrayNotHasKey('critical_emp', $catalog->eligible('old_substation', 2));
        $this->assertArrayHasKey('critical_emp', $catalog->eligible('old_substation', 3));
    }

    public function test_chance_and_event_limits_scale_with_difficulty(): void
    {
        $catalog = app(ExpeditionEventCatalog::class);

        $this->assertSame(20, $catalog->triggerChance(1));
        $this->assertSame(50, $catalog->triggerChance(3));
        $this->assertSame(1, $catalog->maxEvents(1));
        $this->assertSame(3, $catalog->maxEvents(3));
    }

    public function test_catalog_entries_have_valid_weights_and_effect_types(): void
    {
        $allowed = ['temporary_stat', 'battery', 'resource', 'xp', 'integrity', 'critical_battery', 'none'];

        foreach (config('expedition_events') as $key => $event) {
            $this->assertGreaterThan(0, $event['weight'], $key);
            $this->assertContains($event['effect']['type'], $allowed, $key);
            $this->assertNotEmpty($event['locations'], $key);
        }
    }
}
