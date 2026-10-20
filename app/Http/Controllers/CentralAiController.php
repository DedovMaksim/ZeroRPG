<?php

namespace App\Http\Controllers;

use App\Models\ConstructionProject;

class CentralAiController extends Controller
{
    public function index()
    {
        $robot = auth()->user()->robot;

        $base = $robot->base->load('buildings');
        $robot->load('inventories');

        $projects = ConstructionProject::with('requirements.resource')
            ->where('base_id', $robot->base->id)
            ->get();

        return view('central-ai', [
            'robot' => $robot,
            'projects' => $projects,
            'base' => $base,
            'buildings' => $base->buildings->keyBy('key'),
            'buildingCatalog' => config('buildings', []),
        ]);
    }
}