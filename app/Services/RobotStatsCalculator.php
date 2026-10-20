<?php

namespace App\Services;

use App\Models\Robot;

class RobotStatsCalculator
{
    public function cpu(Robot $robot): int
    {
        return (int) $robot->cpu + $this->equippedBonus($robot, 'cpu_bonus');
    }

    public function ram(Robot $robot): int
    {
        return (int) $robot->ram + $this->equippedBonus($robot, 'ram_bonus');
    }

    public function maxSsd(Robot $robot): int
    {
        return $robot->maxSsd() + $this->equippedBonus($robot, 'ssd_bonus');
    }

    public function lootBonus(Robot $robot): int
    {
        return min(5, intdiv($this->cpu($robot), 10))
            + $this->equippedBonus($robot, 'loot_chance_bonus');
    }

    private function equippedBonus(Robot $robot, string $column): int
    {
        if (! method_exists($robot, 'robotItems')) {
            return 0;
        }

        return (int) $robot->robotItems()
            ->where('equipped', true)
            ->with('item')
            ->get()
            ->sum(fn ($robotItem) => (int) ($robotItem->item?->{$column} ?? 0));
    }
}
