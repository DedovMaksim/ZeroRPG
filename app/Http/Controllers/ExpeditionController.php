<?php

namespace App\Http\Controllers;

use App\Models\Expedition;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\ExpeditionLogGenerator;

class ExpeditionController extends Controller
{
    public function start(Location $location): RedirectResponse
    {
        $user = Auth::user();
        $robot = $user->robot;

        $activeExpedition = $robot->expeditions()
            ->where('status', 'in_progress')
            ->exists();

        if ($activeExpedition) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Робот уже находится в экспедиции.');
        }

        $durationMinutes = 5;

        $expedition = Expedition::create([
            'robot_id' => $robot->id,
            'location_id' => $location->id,
            'status' => 'in_progress',
            'duration_minutes' => $durationMinutes,
            'started_at' => now(),
            'finished_at' => now()->addMinutes($durationMinutes),
        ]);

        $generator = new ExpeditionLogGenerator();

        $generator->generate($expedition);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Экспедиция началась. Робот покинул базу.');
    }
}