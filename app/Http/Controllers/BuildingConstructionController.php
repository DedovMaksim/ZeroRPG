<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class BuildingConstructionController extends Controller
{
    public function store(string $key): RedirectResponse
    {
        $definition = config("buildings.{$key}");

        abort_unless(is_array($definition), 404);

        $robot = auth()->user()->robot;

        $result = DB::transaction(function () use ($robot, $key, $definition): string {
            $base = $robot->base;

            // Lock the parent base to serialize project creation for this player.
            $base = $base->newQuery()->whereKey($base->id)->lockForUpdate()->firstOrFail();

            if ($base->buildings()->where('key', $key)->exists()) {
                return 'built';
            }

            if ($base->constructionProjects()->where('key', $key)->exists()) {
                return 'exists';
            }

            foreach ($definition['requires'] ?? [] as $requiredBuilding) {
                if (! $base->buildings()->where('key', $requiredBuilding)
                    ->where('status', 'active')->exists()) {
                    return 'prerequisite';
                }
            }

            $cost = $definition['cost'];
            $resources = Resource::whereIn('slug', array_keys($cost))
                ->get()->keyBy('slug');

            if ($resources->count() !== count($cost)) {
                return 'resources';
            }

            $project = $base->constructionProjects()->create([
                'key' => $key,
                'name' => $definition['name'],
                'description' => $definition['description'],
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
            'created' => back()->with('success', 'Строительный проект создан.'),
            'built' => back()->with('error', 'Постройка уже существует.'),
            'exists' => back()->with('error', 'Строительство уже начато.'),
            'prerequisite' => back()->with('error', 'Сначала восстановите необходимые постройки.'),
            default => back()->with('error', 'Не найдены необходимые типы ресурсов.'),
        };
    }
}
