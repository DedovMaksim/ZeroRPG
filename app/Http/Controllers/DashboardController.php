<?php

namespace App\Http\Controllers;

use App\Services\ExpeditionProcessor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(ExpeditionProcessor $processor): View
    {
        $robot = Auth::user()->robot;

        $processor->processCompletedExpeditions($robot);

        $activeExpedition = $robot->expeditions()
            ->with(['location', 'logs'])
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        $lastCompletedExpedition = $robot->expeditions()
            ->with(['location', 'logs'])
            ->where('status', 'completed')
            ->latest()
            ->first();

        return view('dashboard', [
            'robot' => $robot,
            'activeExpedition' => $activeExpedition,
            'lastCompletedExpedition' => $lastCompletedExpedition,
        ]);
    }
}