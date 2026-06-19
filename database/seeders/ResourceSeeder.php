<?php

namespace Database\Seeders;

use App\Models\Resource;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('resources') as $slug => $resource) {
            Resource::updateOrCreate(
                [
                    'slug' => $slug,
                ],
                [
                    'name' => $resource['name'],
                    'storage_size' => $resource['storage_size'],
                    'scrap_value' => $resource['scrap_value'],
                ]
            );
        }
    }
}