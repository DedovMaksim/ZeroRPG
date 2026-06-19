<?php

namespace App\Services;

use App\Models\Robot;

class BatteryProcessor
{
    public function process(Robot $robot): void
    {
        $maxBattery = $robot->maxBattery();

        if ($robot->battery >= $maxBattery) {
            return;
        }

        if (! $robot->battery_updated_at) {
            return;
        }

        $minutesPassed = $robot->battery_updated_at
            ->diffInMinutes(now());

        if ($minutesPassed <= 0) {
            return;
        }

        // 100% за 25 минут
        $chargePerMinute = 100 / 25;

        $restoredBattery = floor(
            $minutesPassed * $chargePerMinute
        );

        $newBattery = min(
            $maxBattery,
            $robot->battery + $restoredBattery
        );

        $robot->update([
            'battery' => $newBattery,
            'battery_updated_at' => now(),
        ]);
    }
}