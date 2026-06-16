<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Панель оператора Zero RPG
        </h2>
    </x-slot>

    @php
        $robot = auth()->user()->robot;
        $inventories = $robot->inventories()->with('resource')->get();
    @endphp

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

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
                        <p>&gt; Ожидание первой экспедиции...</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>