<?php

namespace Tests\Unit;

use Tests\TestCase;

class ExpeditionEventTextsTest extends TestCase
{
    public function test_every_event_has_at_least_eight_unique_narrative_variants(): void
    {
        $events = config('expedition_events');
        $texts = config('expedition_event_texts');

        $this->assertEqualsCanonicalizing(array_keys($events), array_keys($texts));

        foreach ($texts as $key => $variants) {
            $this->assertCount(8, $variants, $key);
            $this->assertCount(count($variants), array_unique($variants), $key);

            foreach ($variants as $variant) {
                $this->assertIsString($variant);
                $this->assertNotSame('', trim($variant));
            }
        }
    }
}
