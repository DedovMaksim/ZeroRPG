<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            [
                'name' => 'Металлолом',
                'slug' => 'metal_scrap',
                'storage_size' => 1,
                'scrap_value' => 1,
            ],
            [
                'name' => 'Медь',
                'slug' => 'copper',
                'storage_size' => 3,
                'scrap_value' => 3,
            ],
            [
                'name' => 'Электроника',
                'slug' => 'electronics',
                'storage_size' => 8,
                'scrap_value' => 8,
            ],
        ];

        foreach ($resources as $resource) {
            Resource::updateOrCreate(
                ['slug' => $resource['slug']],
                $resource
            );
        }
    }
}