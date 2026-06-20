<?php

namespace App\Http\Controllers;

use App\Models\ConstructionProject;

class CentralAiController extends Controller
{
    public function index()
    {
        $robot = auth()->user()->robot;

        $projects = ConstructionProject::with('requirements.resource')
            ->where('base_id', $robot->base->id)
            ->get();

        return view('central-ai', [
            'robot' => $robot,
            'projects' => $projects,
        ]);
    }
}