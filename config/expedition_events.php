<?php

/**
 * Expedition event definitions.
 *
 * Weight is relative within the pool of eligible events, not a percentage.
 * Effects are descriptive until the event processor is implemented.
 */
return [
    'wasteland_mechanic' => [
        'name' => 'Странствующий мастер пустошей',
        'category' => 'positive',
        'weight' => 12,
        'min_difficulty' => 1,
        'locations' => ['drone_dump', 'abandoned_factory'],
        'message' => 'Мастер пустошей установил экспериментальный чип памяти.',
        'effect' => ['type' => 'temporary_stat', 'stat' => 'ram', 'modifier' => 2, 'duration_minutes' => 60],
    ],
    'solar_charger' => [
        'name' => 'Забытая зарядная станция',
        'category' => 'positive',
        'weight' => 16,
        'min_difficulty' => 1,
        'locations' => ['drone_dump', 'old_substation'],
        'message' => 'Робот обнаружил уцелевшую зарядную станцию.',
        'effect' => ['type' => 'battery', 'min' => 15, 'max' => 30],
    ],
    'salvage_cache' => [
        'name' => 'Тайник сборщика',
        'category' => 'positive',
        'weight' => 20,
        'min_difficulty' => 1,
        'locations' => ['drone_dump', 'abandoned_factory'],
        'message' => 'Под обломками найден тайник с металлоломом.',
        'effect' => ['type' => 'resource', 'resource' => 'scrap_metal', 'min' => 3, 'max' => 8],
    ],
    'ancient_terminal' => [
        'name' => 'Архив древней сети',
        'category' => 'positive',
        'weight' => 12,
        'min_difficulty' => 2,
        'locations' => ['abandoned_factory', 'old_substation'],
        'message' => 'Робот расшифровал архив старой сети.',
        'effect' => ['type' => 'xp', 'min' => 10, 'max' => 25],
    ],
    'falling_debris' => [
        'name' => 'Обвал конструкции',
        'category' => 'negative',
        'weight' => 20,
        'min_difficulty' => 1,
        'locations' => ['drone_dump', 'abandoned_factory'],
        'message' => 'Падающие обломки повредили корпус робота.',
        'effect' => ['type' => 'integrity', 'min' => -12, 'max' => -5],
    ],
    'hostile_drones' => [
        'name' => 'Атака бродячих дронов',
        'category' => 'negative',
        'weight' => 18,
        'min_difficulty' => 2,
        'locations' => ['abandoned_factory', 'old_substation'],
        'message' => 'Охранные дроны атаковали робота.',
        'effect' => ['type' => 'integrity', 'min' => -15, 'max' => -8],
    ],
    'electrical_surge' => [
        'name' => 'Электрический разряд',
        'category' => 'negative',
        'weight' => 20,
        'min_difficulty' => 2,
        'locations' => ['old_substation'],
        'message' => 'Мощный разряд повредил энергетическую систему.',
        'effect' => ['type' => 'battery', 'min' => -15, 'max' => -5],
    ],
    'memory_fault' => [
        'name' => 'Сбой памяти',
        'category' => 'negative',
        'weight' => 12,
        'min_difficulty' => 2,
        'locations' => ['abandoned_factory', 'old_substation'],
        'message' => 'Электромагнитная помеха вызвала временный сбой RAM.',
        'effect' => ['type' => 'temporary_stat', 'stat' => 'ram', 'modifier' => -2, 'duration_minutes' => 60],
    ],
    'critical_emp' => [
        'name' => 'Критический ЭМИ',
        'category' => 'negative',
        'weight' => 3,
        'min_difficulty' => 3,
        'locations' => ['old_substation'],
        'message' => 'Критический электромагнитный импульс обесточил робота.',
        'effect' => ['type' => 'critical_battery', 'value' => 0],
    ],
    'unknown_signal' => [
        'name' => 'Неизвестный сигнал',
        'category' => 'neutral',
        'weight' => 15,
        'min_difficulty' => 1,
        'locations' => ['drone_dump', 'abandoned_factory', 'old_substation'],
        'message' => 'Сканеры зарегистрировали неизвестный сигнал вдалеке.',
        'effect' => ['type' => 'none'],
    ],
    'machine_tracks' => [
        'name' => 'Следы древних машин',
        'category' => 'neutral',
        'weight' => 15,
        'min_difficulty' => 1,
        'locations' => ['drone_dump', 'abandoned_factory'],
        'message' => 'На поверхности обнаружены следы неизвестных машин.',
        'effect' => ['type' => 'none'],
    ],
];
