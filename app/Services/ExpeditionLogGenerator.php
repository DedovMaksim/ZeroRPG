<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionLog;

class ExpeditionLogGenerator
{
    public function generate(Expedition $expedition): void
    {
        $searchEvents = [
            'Обнаружена перспективная область поиска',
            'Зафиксирован слабый радиосигнал',
            'Обнаружены следы неизвестного механизма',
            'Найдены остатки старого дрона',
            'Сканеры обнаружили залежи металла',
            'Зафиксирована аномальная активность',
            'Обнаружен заброшенный технический тоннель',
        ];

        $lootEvents = [
            'Найдены полезные ресурсы',
            'Обнаружен склад уцелевших материалов',
            'Найдён контейнер с компонентами',
            'Обнаружены пригодные для переработки детали',
        ];

        $events = [
            [0, 'start', 'Экспедиция началась'],
            [1, 'travel', 'Робот покинул базу'],
            [2, 'travel', 'Робот прибыл в локацию'],
            [3, 'search', $searchEvents[array_rand($searchEvents)]],
            [4, 'loot', $lootEvents[array_rand($lootEvents)]],
            [5, 'finish', 'Экспедиция завершена'],
        ];

        foreach ($events as [$minute, $type, $message]) {
            ExpeditionLog::create([
                'expedition_id' => $expedition->id,
                'minute' => $minute,
                'event_type' => $type,
                'message' => $message,
                'event_time' => $expedition->started_at->copy()->addMinutes($minute),
            ]);
        }
    }
}