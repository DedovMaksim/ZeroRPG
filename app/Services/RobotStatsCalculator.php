<?php

namespace App\Services;

use App\Models\Robot;

class RobotStatsCalculator
{
    public function __construct(private RobotEffectService $effects) {}

    public function cpu(Robot $robot): int
    {
        return $this->effects->effective($robot, 'cpu', (int) $robot->cpu + $this->equippedBonus($robot, 'cpu_bonus'));
    }

    public function ram(Robot $robot): int
    {
        return $this->effects->effective($robot, 'ram', (int) $robot->ram + $this->equippedBonus($robot, 'ram_bonus'));
    }

    public function maxSsd(Robot $robot): int
    {
        return $this->effects->effective($robot, 'ssd', $robot->maxSsd() + $this->equippedBonus($robot, 'ssd_bonus'));
    }

    public function maxBattery(Robot $robot): int
    {
        return $this->effects->effective($robot, 'max_battery', $robot->maxBattery());
    }

    public function maxIntegrity(Robot $robot): int
    {
        return $this->effects->effective($robot, 'max_integrity', $robot->maxIntegrity());
    }

    public function logistics(Robot $robot): int
    {
        return $this->effects->effective($robot, 'logistics', (int) $robot->logistics_level);
    }

    public function chargeRatePercent(Robot $robot): int
    {
        return $this->effects->effective($robot, 'charge_rate_percent', 0);
    }

    public function energyCostPercent(Robot $robot): int
    {
        return $this->effects->effective($robot, 'energy_cost_percent', 0);
    }

    public function lootPercent(Robot $robot): int
    {
        return $this->effects->effective($robot, 'loot_percent', 0);
    }

    public function xpPercent(Robot $robot): int
    {
        return $this->effects->effective($robot, 'xp_percent', 0);
    }

    public function durationPercent(Robot $robot): int
    {
        return $this->effects->effective($robot, 'duration_percent', 0);
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
