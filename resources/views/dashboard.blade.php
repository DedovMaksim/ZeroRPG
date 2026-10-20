<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Панель оператора Zero RPG
        </h2>
    </x-slot>

    @php
        $inventories = $robot->inventories()
            ->with('resource')
            ->get();

        $locations = \App\Models\Location::all();
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto pt-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 text-green-800 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-100 text-red-800 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <div class="space-y-6">

                {{-- Верхний ряд --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- Робот --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-2xl font-bold mb-2">
                            {{ $robot->name }}
                        </h3>

                        <p class="mb-6 text-gray-600">
                            Автономный робот-клон серии Zero готов к работе.
                        </p>

                        <div class="bg-amber-100 border border-amber-300 rounded-lg p-6 mb-6">

                            <div class="text-4xl font-bold text-amber-900">
                                <div class="font-bold">
                                    <span class="text-2xl">Скрапы:</span>
                                    <span class="text-xl">⛭ {{ $robot->scraps }}</span>
                                </div>
                            </div>

                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">

                        <div class="flex justify-between items-center mb-2">
                            <div>
                                <div class="text-sm uppercase tracking-wide text-blue-700">
                                    Прогресс робота
                                </div>

                                <div class="text-2xl font-bold text-blue-900">
                                    Уровень {{ $robot->level }}
                                </div>
                            </div>

                            <div class="text-right">
                                <div class="text-sm text-blue-700">
                                    XP
                                </div>

                                <div class="font-bold">
                                    {{ $robot->xp }} / {{ $robot->xpForNextLevel() }}
                                </div>
                            </div>
                        </div>

                        <div class="w-full bg-blue-100 rounded-full h-3 overflow-hidden">
                            <div
                                class="bg-blue-600 h-3"
                                style="width: {{ $robot->levelProgressPercent() }}%;"
                            ></div>
                        </div>

                    </div>

                        @php
                            $activeEffects = $robot->effects()
                                ->where('expires_at', '>', now())
                                ->orderBy('expires_at')
                                ->get();
                        @endphp

                        @if ($activeEffects->isNotEmpty())
                            <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <h4 class="font-semibold text-amber-900 mb-2">Активные эффекты</h4>
                                <ul class="space-y-1 text-sm text-amber-900">
                                    @foreach ($activeEffects as $effect)
                                        <li>
                                            {{ strtoupper($effect->stat) }}
                                            {{ $effect->modifier > 0 ? '+' : '' }}{{ $effect->modifier }}
                                            · до {{ $effect->expires_at->format('H:i') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div class="p-4 bg-gray-100 rounded-lg">
                                <div class="text-sm text-gray-500">CPU</div>
                                <div class="text-xl font-bold">{{ $robot->stats()->cpu($robot) }} MHz</div>
                            </div>

                            <div class="p-4 bg-gray-100 rounded-lg">
                                <div class="text-sm text-gray-500">RAM</div>
                                <div class="text-xl font-bold">{{ $robot->stats()->ram($robot) }} KB</div>
                            </div>

                            <div class="p-4 bg-gray-100 rounded-lg">
                                <div class="text-sm text-gray-500">Battery</div>
                                <div class="text-xl font-bold">{{ $robot->battery }} / {{ $robot->stats()->maxBattery($robot) }}%</div>
                            </div>

                            <div class="p-4 bg-gray-100 rounded-lg">
                                <div class="text-sm text-gray-500">Integrity</div>
                                <div class="text-xl font-bold">{{ $robot->integrity }} / {{ $robot->stats()->maxIntegrity($robot) }}</div>
                            </div>

                            <div class="p-4 bg-gray-100 rounded-lg">
                                <div class="text-sm text-gray-500">SSD</div>
                                <div class="text-2xl">
                                    {{ $usedStorage }} / {{ $robot->totalStorage() }} MB
                                </div>

                                @if ($usedStorage >= $robot->totalStorage())
                                    <div class="mt-1 text-sm text-red-600">
                                        Склад заполнен
                                    </div>
                                @elseif ($usedStorage >= $robot->totalStorage() * 0.8)
                                    <div class="mt-1 text-sm text-yellow-600">
                                        Почти заполнено
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Активная экспедиция --}}
                    @if ($activeExpedition)
                        <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-white">
                            <h4 class="text-lg font-bold mb-4">
                                Активная экспедиция
                            </h4>

                            <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                                <div>
                                    <div class="text-gray-500">Локация</div>
                                    <div class="font-semibold">
                                        {{ $activeExpedition->location->name }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-gray-500">Статус</div>
                                    <div class="font-semibold">
                                        выполняется
                                    </div>
                                </div>

                                @php
                                    $durationSeconds = $activeExpedition->started_at
                                        ->diffInSeconds($activeExpedition->finished_at);

                                    $durationMinutes = intdiv($durationSeconds, 60);
                                    $durationSecondsRest = $durationSeconds % 60;
                                @endphp

                                <div>
                                    <div class="text-gray-500">Длительность</div>
                                    <div class="font-semibold">
                                        {{ $durationMinutes }} мин. {{ $durationSecondsRest }} сек.
                                    </div>
                                </div>

                                <div>
                                    <div class="text-gray-500">Завершение</div>
                                    <div class="font-semibold">
                                        {{ $activeExpedition->finished_at->format('H:i') }}
                                    </div>
                                </div>
                            </div>

                            <h5 class="font-bold mb-3">
                                Журнал экспедиции
                            </h5>

                            @php
                                $elapsedMinutes = $activeExpedition->started_at->diffInMinutes(now());
                            @endphp

                            <div class="space-y-2 text-sm font-mono bg-gray-950 text-green-300 p-4 rounded-lg min-h-32 max-h-96 overflow-y-auto" role="log" aria-label="Журнал экспедиции">
                                @foreach ($activeExpedition->logs as $log)
                                    @if ($log->event_time->lte(now()))
                                        <div>
                                            <span class="text-green-500">
                                                [{{ $log->event_time->format('H:i:s') }}]
                                            </span>

                                            <span class="{{ in_array($log->event_type, ['critical_event', 'emergency_return']) ? 'text-red-300 font-bold' : ($log->event_type === 'random_event' ? 'text-amber-200' : 'text-green-300') }}">{{ $log->message }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @elseif ($lastCompletedExpedition)

                        <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-white">

                            <h4 class="text-lg font-bold mb-4">
                                {{ $lastCompletedExpedition->status === 'aborted' ? 'Аварийное возвращение' : 'Результат экспедиции' }}
                            </h4>

                            <div class="grid grid-cols-2 gap-4 text-sm mb-6">

                                <div>
                                    <div class="text-gray-500">Локация</div>
                                    <div class="font-semibold">
                                        {{ $lastCompletedExpedition->location->name }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-gray-500">Статус</div>
                                    <div class="font-semibold">
                                        {{ $lastCompletedExpedition->status === 'aborted' ? 'прервана' : 'завершена' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-gray-500">Длительность</div>
                                    <div class="font-semibold">
                                        {{ $lastCompletedExpedition->duration_minutes }} мин.
                                    </div>
                                </div>

                                <div>
                                    <div class="text-gray-500">Завершение</div>
                                    <div class="font-semibold">
                                        {{ $lastCompletedExpedition->finished_at->format('H:i') }}
                                    </div>
                                </div>

                            </div>

                            <div class="mb-4">
                                <strong>Потерял:</strong>
                                Battery -{{ $lastCompletedExpedition->battery_cost_paid ?? $lastCompletedExpedition->location->battery_cost }}%
                            </div>

                            <h5 class="font-bold mb-2">
                                Журнал экспедиции
                            </h5>

                            <div class="space-y-2 text-sm font-mono bg-gray-950 text-green-300 p-4 rounded-lg min-h-32 max-h-96 overflow-y-auto" role="log" aria-label="Журнал экспедиции">
                                @foreach ($lastCompletedExpedition->logs as $log)
                                    <div>
                                        <span class="text-green-500">
                                            [{{ $log->event_time->format('H:i:s') }}]
                                        </span>

                                        <span class="{{ in_array($log->event_type, ['critical_event', 'emergency_return']) ? 'text-red-300 font-bold' : ($log->event_type === 'random_event' ? 'text-amber-200' : 'text-green-300') }}">{{ $log->message }}</span>
                                    </div>
                                @endforeach
                            </div>

                        </div>

                    @else

                        <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-gray-200">
                            <h4 class="text-lg font-bold mb-2">
                                Активная экспедиция
                            </h4>

                            <p class="text-gray-500">
                                Робот находится на базе и ожидает приказа.
                            </p>
                        </div>

                    @endif

                </div>

                {{-- Доступные локации и действия базы --}}
                <div class="grid grid-cols-1 gap-6">

                    {{-- Локации --}}
                    <div class="min-w-0 bg-white shadow-sm sm:rounded-lg p-6">

                        <h4 class="text-lg font-bold mb-4">
                            Доступные локации
                        </h4>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            @foreach ($locations as $location)
                                <div class="border rounded-lg p-4 flex flex-col min-w-0">

                                    <h5 class="font-bold text-lg mb-2">
                                        {{ $location->name }}
                                    </h5>

                                    <p class="text-sm text-gray-600 mb-4">
                                        {{ $location->description }}
                                    </p>

                                    <div class="text-sm text-gray-500 mb-4 mt-auto">
                                        <div>Сложность: {{ $location->difficulty }}</div>
                                        <div>Расход батареи: {{ max(0, (int) ceil($location->battery_cost * (100 + $robot->stats()->energyCostPercent($robot)) / 100)) }}%</div>
                                    </div>

                                    <form method="POST" action="{{ route('expeditions.start', $location) }}">
                                        @csrf

                                        <button
                                            type="submit"
                                            class="w-full px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-700"
                                        >
                                            Отправить в экспедицию
                                        </button>
                                    </form>

                                </div>
                            @endforeach
                        </div>

                    </div>

                    {{-- Панель базы --}}
                    <div class="min-w-0 bg-white shadow-sm sm:rounded-lg p-6">

                        <h4 class="text-lg font-bold mb-4">
                            База
                        </h4>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                            <a href="{{ route('market.index') }}"
                            class="block border border-amber-300 bg-amber-50 rounded-lg p-4 hover:bg-amber-100 transition">
                                <div class="font-bold text-lg">
                                    Комиссионка
                                </div>

                                <div class="text-sm text-gray-600 mt-1">
                                    Сдача ресурсов и торговля оборудованием.
                                </div>
                            </a>

                            <a href="{{ route('inventory') }}"
                                class="block border border-amber-300 bg-amber-50 rounded-lg p-4 hover:bg-amber-100 transition">
                                    <div class="font-bold text-lg">
                                        Инвентарь
                                    </div>

                                    <div class="text-sm text-gray-600 mt-1">
                                        Экипировка робота, предметы и склад базы.
                                    </div>
                            </a>

                            <div class="border rounded-lg p-4 opacity-50">
                                <div class="font-bold">
                                    Модули
                                </div>

                                <div class="text-sm text-gray-500">
                                    Скоро
                                </div>
                            </div>

                            <div class="border rounded-lg p-4 opacity-50">
                                <div class="font-bold">
                                    Улучшения
                                </div>

                                <div class="text-sm text-gray-500">
                                    Скоро
                                </div>
                            </div>

                            <a href="{{ route('archive.index') }}"
                            class="block border border-amber-300 bg-amber-50 rounded-lg p-4 hover:bg-amber-100 transition">
                                <div class="font-bold text-lg">
                                    Архив экспедиций
                                </div>

                                <div class="text-sm text-gray-500">
                                    Скоро
                                </div>
                            </a>

                            <a href="{{ route('central-ai') }}" 
                            class="block border border-amber-300 bg-amber-50 rounded-lg p-4 hover:bg-amber-100 transition">
                                    <div class="font-bold text-lg">
                                        Задания Центрального ИИ
                                    </div>

                                    <div class="text-sm text-gray-500">
                                        Строительство, восстановление базы и особые поручения.
                                    </div>
                            </a>

                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-bold mb-4">
                                Специализации
                            </h3>

                            <div class="rounded-lg bg-gray-50 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="text-base font-bold">
                                            Логист
                                        </div>

                                        <div class="text-sm text-gray-600">
                                            Уровень {{ $robot->stats()->logistics($robot) }}
                                        </div>
                                    </div>

                                    <div class="text-right text-sm">
                                        <div class="text-gray-500">
                                            XP
                                        </div>

                                        <div class="font-bold">
                                            {{ $robot->logistics_xp }} / {{ $robot->xpForNextLogisticsLevel() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>

                {{-- Нижний ряд --}}
                <div class="grid grid-cols-1 mb-4 lg:grid-cols-2 gap-6">

                    {{-- Склад --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h4 class="text-lg font-bold mb-4">
                            Склад
                        </h4>

                        @if ($inventories->isEmpty())
                            <p class="text-gray-500">
                                Склад пуст. Робот ещё не добывал ресурсы.
                            </p>
                        @else
                            <div class="space-y-3 max-h-80 overflow-y-auto">
                                @foreach ($inventories as $item)
                                    <div class="flex justify-between border-b border-gray-100 pb-2">
                                        <span>{{ $item->resource->name }}</span>
                                        <span class="font-bold">{{ $item->amount }} ед.</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Терминал --}}
                    <div class="bg-black text-green-400 shadow-sm sm:rounded-lg p-6 font-mono text-sm">
                        <h4 class="text-green-300 font-bold mb-3">
                            Центральный ИИ
                        </h4>

                        <div class="space-y-1">
                            <p>&gt; Оператор подключён.</p>
                            <p>&gt; Робот {{ $robot->name }} активирован.</p>

                            @if (session('success'))
                                <p>&gt; {{ session('success') }}</p>
                            @elseif (session('error'))
                                <p>&gt; {{ session('error') }}</p>
                            @elseif ($activeExpedition)
                                <p>&gt; Экспедиция выполняется.</p>
                            @else
                                <p>&gt; Ожидание приказа...</p>
                            @endif
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>