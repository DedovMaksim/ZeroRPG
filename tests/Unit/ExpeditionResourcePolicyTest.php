<?php

namespace Tests\Unit;

use App\Services\ExpeditionResourcePolicy;
use Tests\TestCase;

class ExpeditionResourcePolicyTest extends TestCase
{
    public function test_keeps_physical_scrap_metal_reserved_for_repairs(): void
    {
        $policy = app(ExpeditionResourcePolicy::class);

        $this->assertSame(10, $policy->reserve(2));
        $this->assertSame(
            ['scrap_metal' => 3, 'copper' => 4, 'electronics' => 1],
            $policy->releasableInventory(['scrap_metal' => 13, 'copper' => 4, 'electronics' => 1])
        );
        $this->assertSame(['copper' => 2], $policy->releasableInventory(['scrap_metal' => 4, 'copper' => 2]));
        $this->assertTrue($policy->needsFarming(9));
        $this->assertFalse($policy->needsFarming(10));
    }

    public function test_selects_accessible_route_by_scrap_per_minute(): void
    {
        $policy = app(ExpeditionResourcePolicy::class);
        $routes = [
            'safe' => ['required_level' => 1, 'battery_cost' => 80, 'scrap_metal' => 4.0, 'duration' => 5.0],
            'rich' => ['required_level' => 7, 'battery_cost' => 105, 'scrap_metal' => 10.0, 'duration' => 8.0],
            'empty' => ['required_level' => 1, 'battery_cost' => 90, 'scrap_metal' => 0.0, 'duration' => 2.0],
        ];

        $this->assertSame('safe', $policy->farmingRoute($routes, 1, 100));
        $this->assertSame('rich', $policy->farmingRoute($routes, 7, 110));
        $this->assertNull($policy->farmingRoute($routes, 1, 50));
    }

    public function test_rejects_invalid_inventory_or_reserve(): void
    {
        $policy = app(ExpeditionResourcePolicy::class);
        $this->expectException(\InvalidArgumentException::class);
        $policy->releasableInventory(['scrap_metal' => -1]);
    }
}
