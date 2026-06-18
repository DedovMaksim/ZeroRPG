<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Панель оператора Zero RPG
        </h2>
    </x-slot>

    @php
        $robot = auth()->user()->robot;

        $inventories = $robot->inventories()
            ->with('resource')
            ->get();

        $locations = \App\Models\Location::all();

        $activeExpedition = $robot->expeditions()
            ->with(['location', 'logs'])
            ->where('status', 'in_progress')
            ->latest()
            ->first();
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-2xl font-bold mb-2">
                    {{ $robot->name }}
                </h3>

                <p class="mb-6 text-gray-600">
                    Автономный робот-клон серии Zero готов к работе.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div class="p-4 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-500">CPU</div>
                        <div class="text-xl font-bold">{{ $robot->cpu }}</div>
                    </div>

                    <div class="p-4 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-500">RAM</div>
                        <div class="text-xl font-bold">{{ $robot->ram }} ГБ</div>
                    </div>

                    <div class="p-4 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-500">SSD</div>
                        <div class="text-xl font-bold">{{ $robot->ssd }} ГБ</div>
                    </div>

                    <div class="p-4 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-500">Battery</div>
                        <div class="text-xl font-bold">{{ $robot->battery }}%</div>
                    </div>

                    <div class="p-4 bg-gray-100 rounded-lg">
                        <div class="text-sm text-gray-500">Integrity</div>
                        <div class="text-xl font-bold">{{ $robot->integrity }}%</div>
                    </div>
                </div>

                @if ($activeExpedition)
                    <div class="mt-8 p-4 border border-yellow-300 bg-yellow-50 rounded-lg">
                        <h4 class="text-lg font-bold mb-4">Активная экспедиция</h4>

                        <div class="space-y-2 text-sm">
                            <p>
                                <span class="font-semibold">Локация:</span>
                                {{ $activeExpedition->location->name }}
                            </p>

                            <p>
                                <span class="font-semibold">Статус:</span>
                                выполняется
                            </p>

                            <p>
                                <span class="font-semibold">Длительность:</span>
                                {{ $activeExpedition->duration_minutes }} мин.
                            </p>

                            <p>
                                <span class="font-semibold">Завершение:</span>
                                {{ $activeExpedition->finished_at->format('H:i') }}
                            </p>
                        </div>

                        <div class="mt-6 border-t border-yellow-200 pt-4">
                            <h5 class="font-bold mb-3">
                                Журнал экспедиции
                            </h5>

                            @php
                                $elapsedMinutes = $activeExpedition->started_at->diffInMinutes(now());
                            @endphp

                            <div class="space-y-1 text-sm font-mono bg-black text-green-400 p-3 rounded-lg">
                                @foreach ($activeExpedition->logs as $log)

                                    @if ($log->minute <= $elapsedMinutes)

                                        <div>
                                            <span class="text-green-600">
                                                [{{ $log->event_time->format('H:i') }}]
                                            </span>

                                            {{ $log->message }}
                                        </div>

                                    @endif

                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <div class="mt-8">
                    <h4 class="text-lg font-bold mb-4">Доступные локации</h4>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach ($locations as $location)
                            <div class="p-4 border border-gray-200 rounded-lg">
                                <h5 class="font-bold text-lg mb-2">
                                    {{ $location->name }}
                                </h5>

                                <p class="text-sm text-gray-600 mb-4">
                                    {{ $location->description }}
                                </p>

                                <div class="text-sm text-gray-500 mb-4">
                                    <div>Сложность: {{ $location->difficulty }}</div>
                                    <div>Расход батареи: {{ $location->battery_cost }}%</div>
                                </div>

                                <form method="POST" action="{{ route('expeditions.start', $location) }}">
                                    @csrf

                                    <button
                                        type="submit"
                                        class="w-full px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-700"
                                    >
                                        Отправить
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <h4 class="text-lg font-bold mb-4">Склад</h4>

                        @if ($inventories->isEmpty())
                            <p class="text-gray-500">
                                Склад пуст. Робот ещё не добывал ресурсы.
                            </p>
                        @else
                            <div class="space-y-3">
                                @foreach ($inventories as $item)
                                    <div class="flex justify-between border-b border-gray-100 pb-2">
                                        <span>{{ $item->resource->name }}</span>
                                        <span class="font-bold">{{ $item->amount }} ед.</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="p-4 bg-black text-green-400 rounded-lg font-mono text-sm">
                        <p>&gt; Центральный ИИ: оператор подключён.</p>
                        <p>&gt; Робот {{ $robot->name }} активирован.</p>

                        @if (session('success'))
                            <p>&gt; {{ session('success') }}</p>
                        @elseif (session('error'))
                            <p>&gt; {{ session('error') }}</p>
                        @else
                            <p>&gt; Ожидание экспедиции...</p>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>