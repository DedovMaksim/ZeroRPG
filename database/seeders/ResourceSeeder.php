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
            ],
            [
                'name' => 'Медь',
                'slug' => 'copper',
                'storage_size' => 3,
            ],
            [
                'name' => 'Электроника',
                'slug' => 'electronics',
                'storage_size' => 8,
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