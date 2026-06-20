<?php

namespace App\Http\Controllers;

use App\Models\ConstructionRequirement;
use Illuminate\Http\Request;

class ConstructionController extends Controller
{
    public function transfer(Request $request, ConstructionRequirement $requirement)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $robot = auth()->user()->robot;

        $inventory = $robot->inventories()
            ->where('resource_id', $requirement->resource_id)
            ->first();

        if (! $inventory) {
            return back()->with('error', 'У робота нет этого ресурса.');
        }

        $remainingAmount = $requirement->remainingAmount();

        $amount = min(
            $data['amount'],
            $inventory->amount,
            $remainingAmount
        );

        if ($amount <= 0) {
            return back()->with('error', 'Этот ресурс больше не требуется.');
        }

        $inventory->decrement('amount', $amount);

        if ($inventory->fresh()->amount <= 0) {
            $inventory->delete();
        }

        $requirement->increment('delivered_amount', $amount);

        $project = $requirement->project()->with('requirements')->first();

        if ($project->isCompleted()) {
            $project->update([
                'status' => 'completed',
            ]);

            if ($project->key === 'warehouse') {
                $project->base->buildings()->firstOrCreate(
                    [
                        'key' => 'warehouse',
                    ],
                    [
                        'name' => 'Склад',
                        'level' => 1,
                        'status' => 'active',
                        'capacity' => 40,
                    ]
                );
            }
        }

        return back()->with('success', 'Ресурсы переданы в строительство.');
    }
}