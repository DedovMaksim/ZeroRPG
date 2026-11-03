<?php

namespace App\Services;

/**
 * In-memory physical inventory for expedition simulations.
 * Resource quantities are integers and capacity is measured in SSD units.
 */
class ExpeditionInventoryLedger
{
    /** @var array<string, int> */
    private array $items = [];

    public function __construct(private int $capacity)
    {
        if ($capacity < 0) {
            throw new \InvalidArgumentException('SSD capacity must be non-negative.');
        }
    }

    public function items(): array
    {
        return $this->items;
    }

    public function usedStorage(): int
    {
        $used = 0;
        foreach ($this->items as $slug => $amount) {
            $used += $amount * $this->size($slug);
        }

        return $used;
    }

    public function freeStorage(): int
    {
        return $this->capacity - $this->usedStorage();
    }

    public function add(string $slug, int $amount): int
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Loot quantity must be non-negative.');
        }

        $size = $this->size($slug);
        $accepted = min($amount, intdiv($this->freeStorage(), $size));
        if ($accepted > 0) {
            $this->items[$slug] = ($this->items[$slug] ?? 0) + $accepted;
        }

        return $accepted;
    }

    public function consume(string $slug, int $amount): bool
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Consumption must be non-negative.');
        }
        if (($this->items[$slug] ?? 0) < $amount) {
            return false;
        }

        $remaining = $this->items[$slug] - $amount;
        if ($remaining === 0) {
            unset($this->items[$slug]);
        } else {
            $this->items[$slug] = $remaining;
        }

        return true;
    }

    /**
     * Simulate selling or depositing surplus, preserving repair reserves.
     * Returns removed quantities; does not credit currency or warehouse stock.
     */
    public function unload(ExpeditionResourcePolicy $policy, int $repairsAhead = 2): array
    {
        $removed = $policy->releasableInventory($this->items, $repairsAhead);
        foreach ($removed as $slug => $amount) {
            $this->consume($slug, $amount);
        }

        return $removed;
    }

    private function size(string $slug): int
    {
        $size = config('resources.'.$slug.'.storage_size');
        if (! is_numeric($size) || (int) $size <= 0) {
            throw new \InvalidArgumentException('Unknown resource or invalid storage size: '.$slug);
        }

        return (int) $size;
    }
}
