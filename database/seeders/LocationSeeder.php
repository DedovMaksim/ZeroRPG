<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Свалка дронов',
                'slug' => 'drone_dump',
                'description' => 'Старая зона утилизации сервисных машин. Здесь много металлолома и иногда встречается электроника.',
                'difficulty' => 1,
                'battery_cost' => 80,
            ],
            [
                'name' => 'Заброшенный завод',
                'slug' => 'abandoned_factory',
                'description' => 'Полуразрушенный производственный комплекс. Хорошее место для поиска металла, меди и старых компонентов.',
                'difficulty' => 2,
                'battery_cost' => 90,
            ],
            [
                'name' => 'Старая подстанция',
                'slug' => 'old_substation',
                'description' => 'Отключённый энергетический узел. Здесь можно найти медь, электронику и повреждённые энергоячейки.',
                'difficulty' => 2,
                'battery_cost' => 100,
            ],
        ];

        foreach ($locations as $location) {
            Location::updateOrCreate(
                ['slug' => $location['slug']],
                $location
            );
        }
    }
}