<?php

namespace App\Services;

use App\Models\Robot;
use App\Models\RobotEffect;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RobotEffectService
{
    public function apply(Robot $robot, string $key, string $stat, int $modifier, int $durationMinutes): RobotEffect
    {
        if (! array_key_exists($stat, config('robot_effects.stats', []))) {
            throw new InvalidArgumentException('Unknown robot effect stat.');
        }

        if ($key === '' || $durationMinutes <= 0 || $modifier === 0) {
            throw new InvalidArgumentException('Invalid robot effect parameters.');
        }

        return DB::transaction(function () use ($robot, $key, $stat, $modifier, $durationMinutes): RobotEffect {
            return $robot->effects()->updateOrCreate(
                ['key' => $key],
                [
                    'stat' => $stat,
                    'modifier' => $modifier,
                    'expires_at' => now()->addMinutes($durationMinutes),
                ]
            );
        });
    }

    public function modifier(Robot $robot, string $stat): int
    {
        if (! array_key_exists($stat, config('robot_effects.stats', []))) {
            throw new InvalidArgumentException('Unknown robot effect stat.');
        }

        return (int) $robot->effects()
            ->where('stat', $stat)
            ->where('expires_at', '>', now())
            ->sum('modifier');
    }

    public function effective(Robot $robot, string $stat, int $base): int
    {
        $rules = config('robot_effects.stats.'.$stat);

        if (! is_array($rules)) {
            throw new InvalidArgumentException('Unknown robot effect stat.');
        }

        $value = $base + $this->modifier($robot, $stat);

        return max($rules['min'] ?? PHP_INT_MIN, min($rules['max'] ?? PHP_INT_MAX, $value));
    }
}
