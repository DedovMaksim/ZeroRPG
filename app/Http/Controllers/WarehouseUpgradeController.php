<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class WarehouseUpgradeController extends Controller
{
    public function store(): RedirectResponse
    {
        $robot = auth()->user()->robot;
        $result = DB::transaction(function () use ($robot): string {
            $base = $robot->base;
            $warehouse = $base->buildings()
                ->where('key', 'warehouse')
                ->lockForUpdate()
                ->first();

            if (! $warehouse || $warehouse->status !== 'active') {
                return 'missing';
            }

            if ($warehouse->level !== 1) {
                return 'upgraded';
            }

            if ($base->constructionProjects()->where('key', 'warehouse_upgrade_2')->exists()) {
                return 'exists';
            }

            $cost = ['scrap_metal' => 20, 'copper' => 8, 'electronics' => 2];
            $resources = Resource::whereIn('slug', array_keys($cost))->get()->keyBy('slug');

            if ($resources->count() !== count($cost)) {
                return 'resources';
            }

            $project = $base->constructionProjects()->create([
                'key' => 'warehouse_upgrade_2',
                'name' => 'Расширение склада до уровня II',
                'description' => 'Увеличить вместимость склада с 40 до 80 единиц.',
                'status' => 'in_progress',
            ]);

            foreach ($cost as $slug => $amount) {
                $project->requirements()->create([
                    'resource_id' => $resources[$slug]->id,
                    'required_amount' => $amount,
                    'delivered_amount' => 0,
                ]);
            }

            return 'created';
        });

        return match ($result) {
            'created' => back()->with('success', 'Проект расширения склада создан.'),
            'missing' => back()->with('error', 'Сначала необходимо восстановить склад.'),
            'upgraded' => back()->with('error', 'Склад уже улучшен.'),
            'exists' => back()->with('error', 'Проект расширения уже существует.'),
            default => back()->with('error', 'Не найдены необходимые типы ресурсов.'),
        };
    }
}
