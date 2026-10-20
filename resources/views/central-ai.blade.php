<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Задания Центрального ИИ
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto pt-4 sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-2xl font-bold mb-2">
                    Центральный ИИ
                </h3>

                <p class="text-gray-600 mb-6">
                    Доступные поручения для восстановления базы серии Zero.
                </p>

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

                @php
                    $warehouse = $robot->base->buildings()->where('key', 'warehouse')->first();
                    $upgradeExists = $projects->contains('key', 'warehouse_upgrade_2');
                @endphp

                @if ($warehouse && $warehouse->status === 'active')
                    <div class="border rounded-xl p-5 mb-6">
                        <h4 class="text-xl font-semibold">Склад — уровень {{ $warehouse->level }}</h4>
                        <p class="text-gray-600 mt-1">Вместимость: {{ $warehouse->usedStorage() }} / {{ $warehouse->capacity }}</p>
                        @if ($warehouse->level === 1 && ! $upgradeExists)
                            <p class="text-sm text-gray-600 mt-3">Расширение до 80: металлолом ×20, медь ×8, электроника ×2.</p>
                            <form method="POST" action="{{ route('warehouse.upgrade') }}" class="mt-3">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-zinc-800 text-white rounded-lg">Начать расширение склада</button>
                            </form>
                        @elseif ($warehouse->level === 1)
                            <p class="text-sm text-amber-700 mt-3">Проект расширения уже создан.</p>
                        @else
                            <p class="text-sm text-green-700 mt-3">Склад расширен.</p>
                        @endif
                    </div>
                @endif

                @if ($warehouse && $warehouse->status === 'active')
                    @php
                        $repairBay = $robot->base->buildings()->where('key', 'repair_bay')->first();
                        $repairProject = $projects->contains('key', 'repair_bay');
                    @endphp
                    <div class="border rounded-xl p-5 mb-6">
                        <h4 class="text-xl font-semibold">Ремонтный отсек</h4>
                        @if ($repairBay)
                            <p class="text-green-700 mt-2">Построен. Функция ремонта появится в следующем этапе.</p>
                        @elseif ($repairProject)
                            <p class="text-amber-700 mt-2">Строительство начато. Передавайте материалы в проект ниже.</p>
                        @else
                            <p class="text-gray-600 mt-2">Требуется: металлолом ×15, медь ×5, электроника ×2.</p>
                            <form method="POST" action="{{ route('buildings.construct', 'repair_bay') }}" class="mt-3">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-zinc-800 text-white rounded-lg">Начать строительство</button>
                            </form>
                        @endif
                    </div>
                @endif

                <div class="space-y-6">
                    @forelse ($projects as $project)
                        <div class="border rounded-xl p-5">

                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h4 class="text-xl font-semibold">
                                        {{ $project->name }}
                                    </h4>

                                    <p class="text-gray-600">
                                        {{ $project->description }}
                                    </p>
                                </div>

                                <div class="text-sm text-amber-600 font-semibold">
                                    {{ $project->status === 'completed' ? 'Завершено' : 'В процессе' }}
                                </div>
                            </div>

                            <div class="space-y-3">
                                @foreach ($project->requirements as $requirement)
                                    <div class="bg-gray-50 rounded-lg p-4">
                                        <div class="flex justify-between">
                                            <div class="font-semibold">
                                                {{ $requirement->resource->name }}
                                            </div>

                                            <div>
                                                {{ $requirement->delivered_amount }}
                                                /
                                                {{ $requirement->required_amount }}
                                            </div>
                                        </div>

                                        <div class="w-full bg-gray-200 rounded-full h-3 mt-2 overflow-hidden">
                                            <div
                                                class="bg-green-600 h-3"
                                                style="width: {{ min(100, ($requirement->delivered_amount / $requirement->required_amount) * 100) }}%;"
                                            ></div>
                                        </div>

                                        @php
                                        $inventoryItem = $robot->inventories
                                            ->where('resource_id', $requirement->resource_id)
                                            ->first();

                                        $availableAmount = $inventoryItem?->amount ?? 0;
                                        $remainingAmount = $requirement->remainingAmount();
                                    @endphp

                                    @if ($project->status !== 'completed' && $remainingAmount > 0)
                                        <form
                                            method="POST"
                                            action="{{ route('construction.transfer', $requirement) }}"
                                            class="mt-3 flex items-end gap-3"
                                        >
                                            @csrf

                                            <div>
                                                <label class="block text-sm text-gray-600 mb-1">
                                                    На SSD: {{ $availableAmount }}
                                                </label>

                                                <input
                                                    type="number"
                                                    name="amount"
                                                    value="{{ min($availableAmount, $remainingAmount) }}"
                                                    min="1"
                                                    max="{{ min($availableAmount, $remainingAmount) }}"
                                                    class="w-24 rounded-lg border-gray-300"
                                                    @disabled($availableAmount <= 0)
                                                >
                                            </div>

                                            <button
                                                type="submit"
                                                class="px-4 py-2 bg-zinc-800 text-white rounded-lg disabled:opacity-50"
                                                @disabled($availableAmount <= 0)
                                            >
                                                Передать
                                            </button>
                                        </form>
                                    @endif
                                    </div>
                                @endforeach
                            </div>

                        </div>
                    @empty
                        <p class="text-gray-600">
                            Сейчас поручений нет.
                        </p>
                    @endforelse
                </div>

            </div>

        </div>
    </div>
</x-app-layout>