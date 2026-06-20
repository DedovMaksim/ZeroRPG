<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Base;
use App\Models\Resource;
use App\Models\ConstructionProject;
use App\Models\ConstructionRequirement;

class ConstructionProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $base = Base::first();

        if (! $base) {
            return;
        }

        // Защита от повторного создания
        if (ConstructionProject::where('key', 'warehouse')->exists()) {
            return;
        }

        $project = ConstructionProject::create([
            'base_id' => $base->id,
            'key' => 'warehouse',
            'name' => 'Восстановить складской комплекс',
            'description' => 'Старый склад базы требует восстановления.',
            'status' => 'in_progress',
        ]);

        $requirements = [
            'Металлолом' => 100,
            'Медь' => 50,
            'Электроника' => 10,
        ];

        foreach ($requirements as $resourceName => $requiredAmount) {

            $resource = Resource::where('name', $resourceName)->first();

            if (! $resource) {
                continue;
            }

            ConstructionRequirement::create([
                'construction_project_id' => $project->id,
                'resource_id' => $resource->id,
                'required_amount' => $requiredAmount,
                'delivered_amount' => 0,
            ]);
        }
    }
}