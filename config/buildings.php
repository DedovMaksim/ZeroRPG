<?php

return [
    'repair_bay' => [
        'name' => 'Ремонтный отсек',
        'description' => 'Восстановить ремонтный отсек базы. Функция ремонта будет добавлена отдельно.',
        'requires' => ['warehouse'],
        'cost' => [
            'scrap_metal' => 15,
            'copper' => 5,
            'electronics' => 2,
        ],
        'building' => [
            'name' => 'Ремонтный отсек',
            'level' => 1,
            'status' => 'active',
            'capacity' => 0,
        ],
    ],
];
