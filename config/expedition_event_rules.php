<?php

return [
    // Chance that an event slot triggers, based on location difficulty.
    'trigger_chance_by_difficulty' => [
        1 => 20,
        2 => 35,
        3 => 50,
        4 => 65,
        5 => 80,
    ],
    // Upper bound for the number of event slots in one expedition.
    'max_events_by_difficulty' => [
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
    ],
    'category_weights' => [
        'negative' => 30,
        'positive' => 40,
        'neutral' => 30,
    ],
    'allow_duplicate_event_keys' => false,
];
