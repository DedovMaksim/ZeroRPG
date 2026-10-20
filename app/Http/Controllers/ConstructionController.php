<?php

namespace App\Http\Controllers;

use App\Models\ConstructionRequirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConstructionController extends Controller
{
    public function transfer(Request $request, ConstructionRequirement $requirement)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $robot = auth()->user()->robot;

        abort_unless(
            $requirement->project()->where('base_id', $robot->base->id)->exists(),
            403
        );

        $result = DB::transaction(function () use ($robot, $requirement, $data): string {
            $project = $requirement->project()->lockForUpdate()->firstOrFail();

            if ($project->status === 'completed') {
                return 'completed';
            }

            $lockedRequirement = $project->requirements()
                ->whereKey($requirement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $remaining = $lockedRequirement->remainingAmount();

            if ($remaining <= 0) {
                return 'completed';
            }

            $inventory = $robot->inventories()
                ->where('resource_id', $lockedRequirement->resource_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory || $inventory->amount <= 0) {
                return 'missing';
            }

            $amount = min((int) $data['amount'], (int) $inventory->amount, $remaining);

            if ($amount <= 0) {
                return 'completed';
            }

            if ($inventory->amount === $amount) {
                $inventory->delete();
            } else {
                $inventory->decrement('amount', $amount);
            }

            $lockedRequirement->increment('delivered_amount', $amount);

            $project->load('requirements');

            if ($project->isCompleted()) {
                $project->update(['status' => 'completed']);

                $buildingDefinition = config("buildings.{$project->key}");
                if (is_array($buildingDefinition)) {
                    $project->base->buildings()->firstOrCreate(
                        ['key' => $project->key],
                        $buildingDefinition['building']
                    );
                }

                if ($project->key === 'warehouse_upgrade_2') {
                    $warehouse = $project->base->buildings()->where('key', 'warehouse')->lockForUpdate()->firstOrFail();
                    if ($warehouse->level === 1) {
                        $warehouse->update(['level' => 2, 'capacity' => 80]);
                    }
                }

                if ($project->key === 'warehouse') {
                    $project->base->buildings()->firstOrCreate(
                        ['key' => 'warehouse'],
                        [
                            'name' => 'Склад',
                            'level' => 1,
                            'status' => 'active',
                            'capacity' => 40,
                        ]
                    );
                }
            }

            return 'success';
        });

        return match ($result) {
            'missing' => back()->with('error', 'У робота нет этого ресурса.'),
            'completed' => back()->with('error', 'Этот ресурс больше не требуется.'),
            default => back()->with('success', 'Ресурсы переданы в строительство.'),
        };
    }
}
