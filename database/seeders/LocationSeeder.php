<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('locations') as $slug => $location) {
            Location::updateOrCreate(
                [
                    'slug' => $slug,
                ],
                [
                    'name' => $location['name'],
                    'description' => $location['description'],
                    'difficulty' => $location['difficulty'],
                    'battery_cost' => $location['battery_cost'],
                ]
            );
        }
    }
}