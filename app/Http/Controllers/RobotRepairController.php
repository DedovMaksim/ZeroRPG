<?php

namespace App\Http\Controllers;

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

            $robot->increment('integrity', min(10, $missing));

            return 'repaired';
        });

        return match ($result) {
            'repaired' => back()->with('success', 'Робот отремонтирован: прочность восстановлена до 10 единиц.'),
            'unavailable' => back()->with('error', 'Сначала постройте ремонтный отсек.'),
            'expedition' => back()->with('error', 'Нельзя ремонтировать робота во время экспедиции.'),
            default => back()->with('error', 'Прочность робота уже максимальна.'),
        };
    }
}
