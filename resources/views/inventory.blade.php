<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Инвентарь
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto pt-4 sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-2xl font-bold mb-6">
                    Инвентарь робота {{ $robot->name }}
                </h3>

                @if (session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-4 text-green-700">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <div class="border rounded-xl p-5">
                        <h4 class="text-xl font-semibold mb-4">
                            Экипировка
                        </h4>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="border rounded-lg p-3">Голова<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Корпус<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Оружие<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Транспорт<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Модуль CPU<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Модуль RAM<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Модуль SSD<br><span class="text-gray-500">Пусто</span></div>
                            <div class="border rounded-lg p-3">Аккумулятор<br><span class="text-gray-500">Пусто</span></div>
                        </div>
                    </div>

                    <div class="border rounded-xl p-5">
                        <h4 class="text-xl font-semibold mb-4">
                            Хранилище
                        </h4>

                        <div class="space-y-4">

                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <div class="font-semibold">
                                        SSD робота
                                    </div>

                                    <div class="text-gray-600">
                                        {{ $robot->usedStorage() }} / {{ $robot->totalStorage() }} MB
                                    </div>
                                </div>

                                <div class="mt-4 border-t pt-4">
                                    @forelse ($robot->inventories as $item)
                                        <div class="flex items-center justify-between gap-3 text-sm py-2">
                                            <div>
                                                {{ $item->resource->name }}
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <span>×{{ $item->amount }}</span>

                                                @if ($warehouse)
                                                    <form
                                                        method="POST"
                                                        action="{{ route('warehouse.deposit', $item) }}"
                                                        class="flex items-center gap-2"
                                                    >
                                                        @csrf

                                                        <input
                                                            type="number"
                                                            name="amount"
                                                            value="{{ $item->amount }}"
                                                            min="1"
                                                            max="{{ $item->amount }}"
                                                            class="w-20 rounded-lg border-gray-300 text-sm"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="px-3 py-1 bg-zinc-800 text-white rounded-lg text-sm"
                                                        >
                                                            На склад
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-sm text-gray-500">
                                            Пусто
                                        </div>
                                    @endforelse
                                </div>
                            </div>

                            @if ($warehouse)

                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <div class="font-semibold">
                                            Склад базы
                                        </div>

                                        <div class="text-gray-600">
                                            {{ $warehouse->usedStorage() }} / {{ $warehouse->capacity }} MB
                                        </div>
                                    </div>

                                    <div class="mt-4 border-t pt-4">
                                        @forelse ($warehouse->warehouseInventories as $item)
                                            <div class="flex items-center justify-between gap-3 text-sm py-2">
                                                <div>
                                                    {{ $item->resource->name }}
                                                </div>

                                                <div class="flex items-center gap-2">
                                                    <span>×{{ $item->amount }}</span>

                                                    <form
                                                        method="POST"
                                                        action="{{ route('warehouse.withdraw', $item) }}"
                                                        class="flex items-center gap-2"
                                                    >
                                                        @csrf

                                                        <input
                                                            type="number"
                                                            name="amount"
                                                            value="{{ $item->amount }}"
                                                            min="1"
                                                            max="{{ $item->amount }}"
                                                            class="w-20 rounded-lg border-gray-300 text-sm"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="px-3 py-1 bg-zinc-800 text-white rounded-lg text-sm"
                                                        >
                                                            На SSD
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-sm text-gray-500">
                                                Пусто
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                            @else

                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="font-semibold">
                                        Склад базы
                                    </div>

                                    <div class="text-gray-600">
                                        Недоступен
                                    </div>

                                    <div class="text-sm text-amber-600 mt-1">
                                        Требуется восстановить складской комплекс.
                                    </div>
                                </div>

                            @endif

                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>