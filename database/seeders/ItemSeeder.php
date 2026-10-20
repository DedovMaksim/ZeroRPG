<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        Item::updateOrCreate(['slug' => 'cpu-module-mk1'], [
            'name' => 'CPU Module Mk.1',
            'type' => 'cpu_module',
            'description' => 'Увеличивает мощность процессора на 1.',
            'cpu_bonus' => 1,
            'price' => 25,
        ]);

        Item::updateOrCreate(['slug' => 'ram-module-mk1'], [
            'name' => 'RAM Module Mk.1',
            'type' => 'ram_module',
            'description' => 'Увеличивает оперативную память на 4.',
            'ram_bonus' => 4,
            'price' => 30,
        ]);

        Item::updateOrCreate(['slug' => 'ssd-module-mk1'], [
            'name' => 'SSD Module Mk.1',
            'type' => 'ssd_module',
            'description' => 'Увеличивает вместимость SSD на 8 MB.',
            'ssd_bonus' => 8,
            'price' => 40,
        ]);
    }
}
