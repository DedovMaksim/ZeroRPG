<?php

namespace App\Http\Controllers;

use App\Models\Expedition;
use App\Models\Location;
use App\Services\ExpeditionLogGenerator;
use Illuminate\Http\RedirectResponse;

class ExpeditionController extends Controller
{
    public function start(
        Location $location,
        ExpeditionLogGenerator $logGenerator
    ): RedirectResponse {
        $robot = auth()->user()->robot;

        $activeExpedition = $robot->expeditions()
            ->where('status', 'in_progress')
            ->first();

        if ($activeExpedition) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Робот уже находится в экспедиции.');
        }

        if ($robot->battery < $location->battery_cost) {
            return redirect()
                ->route('dashboard')
                ->with(
                    'error',
                    'Недостаточно энергии. Требуется: '
                    . $location->battery_cost
                    . '%, доступно: '
                    . $robot->battery
                    . '%.'
                );
        }

        $robot->update([
            'battery' => max(
                0,
                $robot->battery - $location->battery_cost
            ),
            'battery_updated_at' => now(),
        ]);

        $baseDuration = 3 + ($location->difficulty * 2);

        $ramTier = log($robot->ram / 4, 2);

        $ramReduction = min(
            50,
            $ramTier * 1.1
        );

        $durationSeconds = (int) round(
            $baseDuration * 60 * (1 - $ramReduction / 100)
        );

        $durationSeconds = max(60, $durationSeconds);

        $durationMinutes = (int) ceil($durationSeconds / 60);

        $expedition = Expedition::create([
            'robot_id' => $robot->id,
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => $durationMinutes,
            'started_at' => now(),
            'finished_at' => now()->addSeconds($durationSeconds),
        ]);

        $logGenerator->generate($expedition);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Робот отправлен в экспедицию.');
    }
}