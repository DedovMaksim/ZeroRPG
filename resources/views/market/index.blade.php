<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Комиссионка
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto pt-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 bg-green-100 border border-green-300 text-green-800 rounded-lg p-4">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 bg-red-100 border border-red-300 text-red-800 rounded-lg p-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-2xl font-bold mb-2">
                    Комиссионка Центрального ИИ
                </h3>

                <p class="text-gray-600 mb-6">
                    Сдавайте найденные ресурсы и получайте скрапы.
                </p>

                <div class="bg-amber-100 border border-amber-300 rounded-lg p-6 mb-6">
                    <div class="text-sm uppercase tracking-wide text-amber-700">
                        Баланс
                    </div>

                    <div class="text-4xl font-bold text-amber-900 mt-2">
                        Скрапы: {{ $robot->scraps }}
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <div class="border rounded-lg p-6">
                        <h4 class="font-bold text-lg mb-4">
                            Сдать ресурсы
                        </h4>

                        <form method="POST"
                            action="{{ route('market.sell-all') }}"
                            class="mb-6">
                            @csrf

                            <button
                                type="submit"
                                class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-gray-700"
                            >
                                Сдать всё
                            </button>
                        </form>

                        @if ($inventory->isEmpty())
                            <p class="text-gray-600">
                                Склад пуст. Отправьте робота в экспедицию.
                            </p>
                        @else
                            <div class="space-y-4">
                                @foreach ($inventory as $item)
                                    <div class="border rounded-lg p-4">
                                        <div class="flex justify-between gap-4 mb-3">
                                            <div>
                                                <div class="font-bold">
                                                    {{ $item->resource->name }}
                                                </div>

                                                <div class="text-sm text-gray-500">
                                                    На складе: {{ $item->amount }}
                                                </div>
                                            </div>

                                            <div class="text-right text-sm text-gray-600">
                                                {{ $item->resource->scrap_value }} скрапов / шт.
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('market.sell') }}" class="flex gap-3">
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="inventory_id"
                                                value="{{ $item->id }}"
                                            >

                                            <input
                                                type="number"
                                                name="amount"
                                                min="1"
                                                max="{{ $item->amount }}"
                                                value="{{ $item->amount }}"
                                                class="w-24 rounded-lg border-gray-300"
                                            >

                                            <button
                                                type="submit"
                                                class="px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-700"
                                            >
                                                Сдать
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="border rounded-lg p-6">
                        <h4 class="font-bold text-lg mb-2">Магазин модулей</h4>
                        <p class="text-gray-600 mb-4">Улучшайте робота за скрапы.</p>

                        <div class="space-y-4">
                            @forelse ($items as $module)
                                <div class="border rounded-lg p-4">
                                    <div class="font-bold">{{ $module->name }}</div>
                                    <p class="text-sm text-gray-600 mt-1">{{ $module->description }}</p>
                                    @if (count($module->bonuses()))
                                        <p class="text-sm text-emerald-700 mt-2">
                                            @foreach ($module->bonuses() as $stat => $bonus)
                                                {{ $stat }}: {{ $bonus > 0 ? '+' : '' }}{{ $bonus }}{{ !$loop->last ? ' · ' : '' }}
                                            @endforeach
                                        </p>
                                    @endif
                                    <div class="flex items-center justify-between gap-3 mt-3">
                                        <span class="font-semibold">{{ $module->price }} скрапов</span>
                                        <form method="POST" action="{{ route('items.buy', $module) }}">
                                            @csrf
                                            <button type="submit"
                                                @disabled($robot->scraps < $module->price)
                                                class="px-4 py-2 rounded-lg text-white bg-gray-900 disabled:opacity-40 disabled:cursor-not-allowed">
                                                Купить
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-gray-500">Модули пока недоступны.</p>
                            @endforelse
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </div>
</x-app-layout>