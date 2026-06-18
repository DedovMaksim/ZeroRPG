<?php

namespace App\Services;

use App\Models\Robot;

class BatteryProcessor
{
    public function process(Robot $robot): void
    {
        if ($robot->battery >= 100) {
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

        // 100% за 32 минут
        $chargePerMinute = 100 / 25;

        $restoredBattery = floor(
            $minutesPassed * $chargePerMinute
        );

        $newBattery = min(
            100,
            $robot->battery + $restoredBattery
        );

        $robot->update([
            'battery' => $newBattery,
            'battery_updated_at' => now(),
        ]);
    }
}