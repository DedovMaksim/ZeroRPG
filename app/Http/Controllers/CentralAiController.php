<?php

namespace App\Http\Controllers;

use App\Models\ConstructionProject;
use App\Models\Resource;

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

        $repairResource = Resource::where('slug', config('repair.resource'))->first();
        $repairInventory = $repairResource
            ? $robot->inventories->firstWhere('resource_id', $repairResource->id)
            : null;

        return view('central-ai', [
            'robot' => $robot,
            'projects' => $projects,
            'base' => $base,
            'buildings' => $base->buildings->keyBy('key'),
            'buildingCatalog' => config('buildings', []),
            'repairCost' => (int) config('repair.cost'),
            'repairRecovery' => (int) config('repair.integrity_per_repair'),
            'repairResourceName' => $repairResource?->name ?? config('resources.'.config('repair.resource').'.name', 'Материал'),
            'repairResourceAvailable' => $repairInventory?->amount ?? 0,
            'repairInExpedition' => $robot->expeditions()->where('status', 'in_progress')->exists(),
        ]);
    }
}