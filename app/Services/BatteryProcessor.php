<?php

namespace App\Services;

use App\Models\Robot;

class BatteryProcessor
{
    public function process(Robot $robot): void
    {
        $maxBattery = $robot->stats()->maxBattery($robot);

        if ($robot->battery >= $maxBattery) {
            return;
        }

        if (! $robot->battery_updated_at) {
            return;
        }

        $now = now();
        $secondsPassed = max(0, $robot->battery_updated_at->diffInSeconds($now, false));

        if ($secondsPassed <= 0) {
            return;
        }

        // 100% за 25 минут
        $chargePerSecond = (100 / (25 * 60)) * max(0, 100 + $robot->stats()->chargeRatePercent($robot)) / 100;

        if ($chargePerSecond <= 0) {
            return;
        }

        $restoredBattery = (int) floor($secondsPassed * $chargePerSecond);

        if ($restoredBattery < 1) {
            return;
        }

        $newBattery = min(
            $maxBattery,
            $robot->battery + $restoredBattery
        );

        $robot->update([
            'battery' => $newBattery,
            // Retain unused elapsed time between page visits.
            'battery_updated_at' => $newBattery >= $maxBattery
                ? $now
                : $robot->battery_updated_at->copy()->addSeconds((int) ceil($restoredBattery / $chargePerSecond)),
        ]);
    }
}