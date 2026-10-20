<?php

namespace App\Http\Controllers;

use App\Services\BatteryProcessor;
use App\Services\ExpeditionProcessor;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        ExpeditionProcessor $processor,
        BatteryProcessor $batteryProcessor
    ): View {
        $robot = Auth::user()->robot;

        $processor->processCompletedExpeditions($robot);
        $robot->refresh();

        $batteryProcessor->process($robot);

        $robot->refresh();

        $activeExpedition = $robot->expeditions()
            ->with(['location', 'logs' => fn ($q) => $q->orderBy('event_time')->orderBy('id')])
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        $lastCompletedExpedition = $robot->expeditions()
            ->with(['location', 'logs' => fn ($q) => $q->orderBy('event_time')->orderBy('id')])
            ->whereIn('status', ['completed', 'aborted'])
            ->latest()
            ->first();

        $usedStorage = $robot->inventories()
            ->with('resource')
            ->get()
            ->sum(function ($inventory) {
                return $inventory->amount * $inventory->resource->storage_size;
            });    

        return view('dashboard', [
            'robot' => $robot,
            'activeExpedition' => $activeExpedition,
            'lastCompletedExpedition' => $lastCompletedExpedition,
            'usedStorage' => $usedStorage,
        ]);
    }
}