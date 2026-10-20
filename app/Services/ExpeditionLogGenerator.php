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
            [4, 'search', 'Сканирование территории завершено. Ожидание результатов анализа.'],
        ];

        $duration = max(1, (int) $expedition->started_at->diffInSeconds($expedition->finished_at));

        foreach ($events as [$minute, $type, $message]) {
            $offset = $type === 'finish' ? $duration : (int) floor($minute * $duration / 5);
            $offset = min($duration, $offset);
            ExpeditionLog::create([
                'expedition_id' => $expedition->id,
                'minute' => (int) floor($offset / 60),
                'event_type' => $type,
                'message' => $message,
                'event_time' => $expedition->started_at->copy()->addSeconds($offset),
            ]);
        }
    }
}