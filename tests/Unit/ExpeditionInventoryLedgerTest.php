<?php

namespace Tests\Unit;

use App\Services\ExpeditionInventoryLedger;
use App\Services\ExpeditionResourcePolicy;
use Tests\TestCase;

class ExpeditionInventoryLedgerTest extends TestCase
{
    public function test_inventory_persists_and_respects_resource_storage_sizes(): void
    {
        $ledger = new ExpeditionInventoryLedger(10);

        $this->assertSame(2, $ledger->add('copper', 2));
        $this->assertSame(4, $ledger->add('scrap_metal', 7));
        $this->assertSame(0, $ledger->freeStorage());
        $this->assertSame(0, $ledger->add('electronics', 1));
        $this->assertSame(['copper' => 2, 'scrap_metal' => 4], $ledger->items());
    }

    public function test_unload_preserves_repair_reserve_and_frees_ssd(): void
    {
        $ledger = new ExpeditionInventoryLedger(32);
        $ledger->add('scrap_metal', 13);
        $ledger->add('copper', 4);

        $removed = $ledger->unload(app(ExpeditionResourcePolicy::class));
        $this->assertSame(['scrap_metal' => 3, 'copper' => 4], $removed);
        $this->assertSame(['scrap_metal' => 10], $ledger->items());
        $this->assertSame(22, $ledger->freeStorage());
        $this->assertTrue($ledger->consume('scrap_metal', 5));
        $this->assertFalse($ledger->consume('scrap_metal', 6));
        $this->assertSame(5, $ledger->items()['scrap_metal']);
    }

    public function test_invalid_resources_are_rejected(): void
    {
        $ledger = new ExpeditionInventoryLedger(10);
        $this->expectException(\InvalidArgumentException::class);
        $ledger->add('nonexistent', 1);
    }
}
