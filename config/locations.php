<?php

return [
    'drone_dump' => [
        'name' => 'Свалка дронов',
        'description' => 'Старая зона утилизации сервисных машин. Здесь много металлолома и иногда встречается электроника.',
        'difficulty' => 1,
        'battery_cost' => 80,
        'xp' => [
            'min' => 1,
            'max' => 3,
        ],
        'loot' => [
            [
                'resource' => 'Металлолом',
                'chance' => 95,
                'min' => 3,
                'max' => 7,
            ],
            [
                'resource' => 'Медь',
                'chance' => 35,
                'min' => 1,
                'max' => 3,
            ],
            [
                'resource' => 'Электроника',
                'chance' => 10,
                'min' => 1,
                'max' => 1,
            ],
        ],
    ],

    'abandoned_factory' => [
        'name' => 'Заброшенный завод',
        'description' => 'Полуразрушенный промышленный комплекс. Здесь чаще встречаются медные кабели и старые электронные блоки.',
        'difficulty' => 2,
        'battery_cost' => 90,
        'xp' => [
            'min' => 2,
            'max' => 4,
        ],
        'loot' => [
            [
                'resource' => 'Металлолом',
                'chance' => 65,
                'min' => 2,
                'max' => 5,
            ],
            [
                'resource' => 'Медь',
                'chance' => 75,
                'min' => 2,
                'max' => 5,
            ],
            [
                'resource' => 'Электроника',
                'chance' => 20,
                'min' => 1,
                'max' => 1,
            ],
        ],
    ],

    'old_substation' => [
        'name' => 'Старая подстанция',
        'description' => 'Опасная энергетическая зона. Здесь много меди и выше шанс найти рабочую электронику.',
        'difficulty' => 3,
        'battery_cost' => 100,
        'xp' => [
            'min' => 3,
            'max' => 5,
        ],
        'loot' => [
            [
                'resource' => 'Металлолом',
                'chance' => 30,
                'min' => 1,
                'max' => 3,
            ],
            [
                'resource' => 'Медь',
                'chance' => 70,
                'min' => 2,
                'max' => 4,
            ],
            [
                'resource' => 'Электроника',
                'chance' => 55,
                'min' => 1,
                'max' => 2,
            ],
        ],
    ],
];