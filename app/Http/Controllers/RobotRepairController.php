<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RobotRepairController extends Controller
{
    public function store(): RedirectResponse
    {
        $robot = auth()->user()->robot;

        $result = DB::transaction(function () use ($robot): string {
            $robot = $robot->newQuery()->whereKey($robot->id)->lockForUpdate()->firstOrFail();

            $repairBay = $robot->base->buildings()
                ->where('key', 'repair_bay')
                ->where('status', 'active')
                ->first();

            if (! $repairBay) {
                return 'unavailable';
            }

            if ($robot->expeditions()->where('status', 'in_progress')->exists()) {
                return 'expedition';
            }

            $missing = $robot->maxIntegrity() - $robot->integrity;

            if ($missing <= 0) {
                return 'full';
            }

            $cost = (int) config('repair.cost');
            $recovery = (int) config('repair.integrity_per_repair');

            if ($cost <= 0 || $recovery <= 0) {
                return 'misconfigured';
            }

            $resource = Resource::where('slug', config('repair.resource'))->first();

            if (! $resource) {
                return 'resource_missing';
            }

            $inventory = $robot->inventories()
                ->where('resource_id', $resource->id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || $inventory->amount < $cost) {
                return 'insufficient';
            }

            if ($inventory->amount === $cost) {
                $inventory->delete();
            } else {
                $inventory->decrement('amount', $cost);
            }

            $robot->increment('integrity', min($recovery, $missing));

            return 'repaired';
        });

        return match ($result) {
            'repaired' => back()->with('success', 'Робот отремонтирован: прочность восстановлена до 10 единиц.'),
            'unavailable' => back()->with('error', 'Сначала постройте ремонтный отсек.'),
            'expedition' => back()->with('error', 'Нельзя ремонтировать робота во время экспедиции.'),
            'insufficient' => back()->with('error', 'Недостаточно металлолома для ремонта.'),
            'misconfigured' => back()->with('error', 'Конфигурация ремонта недоступна.'),
            'resource_missing' => back()->with('error', 'Ресурс для ремонта не найден.'),
            default => back()->with('error', 'Прочность робота уже максимальна.'),
        };
    }
}
