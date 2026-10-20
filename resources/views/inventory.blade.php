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

                        @php
                            $modules = $robot->robotItems()->with('item')->get();
                            $slots = [
                                'cpu_module' => 'Модуль CPU',
                                'ram_module' => 'Модуль RAM',
                                'ssd_module' => 'Модуль SSD',
                            ];
                        @endphp

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            @foreach ($slots as $type => $label)
                                @php
                                    $equipped = $modules->first(fn ($module) => $module->equipped && $module->item->type === $type);
                                @endphp
                                <div class="border rounded-lg p-3">
                                    <div class="font-semibold">{{ $label }}</div>
                                    @if ($equipped)
                                        <div class="mt-1">{{ $equipped->item->name }}</div>
                                        <form method="POST" action="{{ route('robot-items.unequip', $equipped) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="text-amber-700 underline">Снять</button>
                                        </form>
                                    @else
                                        <span class="text-gray-500">Пусто</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <h5 class="font-semibold mt-6 mb-3">Мои модули</h5>
                        <div class="space-y-3">
                            @forelse ($modules->where('equipped', false) as $module)
                                <div class="border rounded-lg p-3 flex items-center justify-between gap-3 text-sm">
                                    <div>
                                        <div class="font-medium">{{ $module->item->name }}</div>
                                        <div class="text-gray-500">{{ $module->item->description }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('robot-items.equip', $module) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-2 bg-gray-900 text-white rounded-lg">Установить</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Нет свободных модулей. Их можно купить в магазине.</p>
                            @endforelse
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