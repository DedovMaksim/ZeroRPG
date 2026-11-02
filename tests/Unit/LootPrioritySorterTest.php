<?php

namespace Tests\Unit;

use App\Services\LootPrioritySorter;
use Tests\TestCase;

class LootPrioritySorterTest extends TestCase
{
    public function test_rare_resources_are_loaded_before_bulk_materials(): void
    {
        $loot = [
            ['resource' => 'Металлолом', 'amount' => 5],
            ['resource' => 'Медь', 'amount' => 2],
            ['resource' => 'Электроника', 'amount' => 1],
        ];

        $sorted = app(LootPrioritySorter::class)->sort($loot);

        $this->assertSame(['Электроника', 'Медь', 'Металлолом'], array_column($sorted, 'resource'));
    }

    public function test_priority_order_does_not_depend_on_input_order(): void
    {
        $loot = [
            ['resource' => 'Медь', 'amount' => 1],
            ['resource' => 'Металлолом', 'amount' => 3],
            ['resource' => 'Электроника', 'amount' => 1],
        ];

        $sorter = app(LootPrioritySorter::class);
        $this->assertSame($sorter->sort($loot), $sorter->sort(array_reverse($loot)));
    }

    public function test_custom_story_resource_can_take_priority(): void
    {
        config()->set('resources.ai_core', [
            'name' => 'Ядро ИИ',
            'storage_size' => 10,
            'scrap_value' => 0,
        ]);
        config()->set('loot_priorities.ai_core', 1);

        $sorted = app(LootPrioritySorter::class)->sort([
            ['resource' => 'Металлолом', 'amount' => 5],
            ['resource' => 'Ядро ИИ', 'amount' => 1],
        ]);

        $this->assertSame('Ядро ИИ', $sorted[0]['resource']);
    }
}
