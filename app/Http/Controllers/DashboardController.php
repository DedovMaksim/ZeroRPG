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

        return view('dashboard');
    }
}