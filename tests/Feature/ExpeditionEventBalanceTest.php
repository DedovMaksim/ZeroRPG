<?php

namespace Tests\Feature;

use App\Services\ExpeditionEventRunner;
use Tests\TestCase;

class ExpeditionEventBalanceTest extends TestCase
{
    public function test_disabled_category_is_never_selected(): void
    {
        config()->set('expedition_event_rules.category_weights', [
            'positive' => 100,
            'negative' => 0,
            'neutral' => 0,
        ]);

        $pool = [
            'bonus' => ['category' => 'positive', 'weight' => 1],
            'damage' => ['category' => 'negative', 'weight' => 1000],
            'signal' => ['category' => 'neutral', 'weight' => 1000],
        ];

        $runner = app(ExpeditionEventRunner::class);
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame('bonus', $runner->selectEventKey($pool));
        }
    }

    public function test_missing_categories_are_excluded_from_selection(): void
    {
        config()->set('expedition_event_rules.category_weights', [
            'positive' => 40,
            'negative' => 30,
            'neutral' => 30,
        ]);

        $pool = ['signal' => ['category' => 'neutral', 'weight' => 15]];

        $this->assertSame('signal', app(ExpeditionEventRunner::class)->selectEventKey($pool));
    }

    public function test_no_positive_weights_produce_no_event(): void
    {
        config()->set('expedition_event_rules.category_weights', [
            'positive' => 0,
            'negative' => 0,
            'neutral' => 0,
        ]);

        $pool = ['signal' => ['category' => 'neutral', 'weight' => 15]];

        $this->assertNull(app(ExpeditionEventRunner::class)->selectEventKey($pool));
    }

    public function test_zero_weight_events_are_not_selected(): void
    {
        config()->set('expedition_event_rules.category_weights', [
            'positive' => 100,
            'negative' => 0,
            'neutral' => 0,
        ]);

        $pool = [
            'enabled' => ['category' => 'positive', 'weight' => 1],
            'disabled' => ['category' => 'positive', 'weight' => 0],
        ];

        $this->assertSame('enabled', app(ExpeditionEventRunner::class)->selectEventKey($pool));
    }
}
