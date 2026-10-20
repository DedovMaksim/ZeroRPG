<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\Resource;
use InvalidArgumentException;

class ExpeditionEventApplier
{
    public function __construct(private RobotEffectService $effects) {}

    /**
     * Apply a configured event and return a truthful consequence for the journal.
     * Critical events are handled separately in Sprint 17.4.
     */
    public function apply(Expedition $expedition, string $key): string
    {
        $event = config('expedition_events.'.$key);

        if (! is_array($event)) {
            throw new InvalidArgumentException('Unknown expedition event.');
        }

        $robot = $expedition->robot;
        $effect = $event['effect'];
        $type = $effect['type'];

        if ($type === 'none') {
            return 'Без изменений характеристик.';
        }

        if ($type === 'critical_battery') {
            $robot->update(['battery' => 0, 'battery_updated_at' => now()]);

            return 'Battery 0. Аварийное возвращение на базу.';
        }

        if ($type === 'temporary_stat') {
            $this->effects->apply(
                $robot,
                $key,
                $effect['stat'],
                (int) $effect['modifier'],
                (int) $effect['duration_minutes']
            );

            return sprintf(
                '%s %+d на %d мин.',
                strtoupper($effect['stat']),
                $effect['modifier'],
                $effect['duration_minutes']
            );
        }

        if ($type === 'battery' || $type === 'integrity') {
            $delta = random_int((int) $effect['min'], (int) $effect['max']);
            $maximum = $type === 'battery'
                ? $robot->stats()->maxBattery($robot)
                : $robot->stats()->maxIntegrity($robot);

            $previous = (int) $robot->{$type};
            $updated = max(0, min($maximum, $previous + $delta));
            $robot->{$type} = $updated;

            if ($type === 'battery' && $updated !== $previous) {
                $robot->battery_updated_at = now();
            }

            $robot->save();

            return sprintf('%s %+d (теперь %d).', ucfirst($type), $updated - $previous, $updated);
        }

        if ($type === 'resource') {
            $resource = Resource::where('slug', $effect['resource'])->firstOrFail();
            $amount = random_int((int) $effect['min'], (int) $effect['max']);
            $free = max(0, $robot->totalStorage() - $robot->usedStorage());
            $capacity = $resource->storage_size > 0
                ? intdiv($free, (int) $resource->storage_size)
                : $amount;
            $received = min($amount, $capacity);

            if ($received > 0) {
                $inventory = $robot->inventories()->firstOrCreate(
                    ['resource_id' => $resource->id],
                    ['amount' => 0]
                );
                $inventory->increment('amount', $received);
            }

            return sprintf('%s +%d (найдено %d; остальное не поместилось).', $resource->name, $received, $amount);
        }

        if ($type === 'xp') {
            $xp = random_int((int) $effect['min'], (int) $effect['max']);
            $xp = max(0, (int) round($xp * (100 + $robot->stats()->xpPercent($robot)) / 100));
            $robot->xp += $xp;

            while ($robot->xp >= $robot->xpForNextLevel()) {
                $robot->xp -= $robot->xpForNextLevel();
                $robot->level++;
                $robot->cpu++;
                $robot->ram = 4 * (2 ** ($robot->level - 1));
                $robot->ssd++;
                $robot->battery += 5;
                $robot->integrity += 5;
            }

            $robot->save();

            return 'Получено опыта: +'.$xp.' XP (уровень '.$robot->level.').';
        }

        throw new InvalidArgumentException('Unsupported expedition effect type.');
    }
}
