<?php

return [
    'stats' => [
        'cpu' => ['min' => 1],
        'ram' => ['min' => 1],
        'ssd' => ['min' => 0],
        'max_battery' => ['min' => 1],
        'max_integrity' => ['min' => 1],
        'logistics' => ['min' => 1],
        'charge_rate_percent' => ['min' => -100, 'max' => 300],
        'energy_cost_percent' => ['min' => -90, 'max' => 300],
        'loot_percent' => ['min' => -100, 'max' => 300],
        'xp_percent' => ['min' => -100, 'max' => 300],
        'duration_percent' => ['min' => -90, 'max' => 300],
    ],
];
