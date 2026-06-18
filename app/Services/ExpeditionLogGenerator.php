<?php

namespace App\Services;

use App\Models\Expedition;
use App\Models\ExpeditionLog;

class ExpeditionLogGenerator
{
    public function generate(Expedition $expedition): void
    {
        $events = [
            [0, 'start', 'Экспедиция началась'],
            [1, 'travel', 'Робот покинул базу'],
            [2, 'travel', 'Робот прибыл в локацию'],
            [3, 'search', 'Обнаружена перспективная область поиска'],
            [4, 'loot', 'Найдены полезные ресурсы'],
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