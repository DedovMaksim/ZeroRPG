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
                    $warehouse = $buildings->get('warehouse');
                    $upgradeExists = $projects->contains('key', 'warehouse_upgrade_2');
                    $activeProjects = $projects->where('status', '!=', 'completed');
                    $finishedProjects = $projects->where('status', 'completed');
                @endphp

                <section class="mb-8">
                    <h3 class="text-xl font-bold mb-4">Состояние базы</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl bg-slate-50 border p-4">
                            <p class="text-sm text-gray-500">Уровень базы</p>
                            <p class="text-2xl font-bold">{{ $base->level }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border p-4">
                            <p class="text-sm text-gray-500">Статус базы</p>
                            <p class="text-lg font-semibold">{{ $base->status === 'emergency' ? 'Аварийный режим' : $base->status }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border p-4">
                            <p class="text-sm text-gray-500">Постройки / активные проекты</p>
                            <p class="text-2xl font-bold">{{ $buildings->count() }} / {{ $activeProjects->count() }}</p>
                        </div>
                    </div>
                </section>

                <section class="mb-8">
                    <h3 class="text-xl font-bold mb-4">Постройки базы</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="border rounded-xl p-5">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-lg font-semibold">Склад</h4>
                                <span class="text-sm {{ $warehouse && $warehouse->status === 'active' ? 'text-green-700' : 'text-gray-500' }}">
                                    {{ $warehouse && $warehouse->status === 'active' ? 'Построен' : 'Не построен' }}
                                </span>
                            </div>
                            @if ($warehouse && $warehouse->status === 'active')
                                @php
                                    $used = $warehouse->usedStorage();
                                    $percent = $warehouse->capacity > 0 ? min(100, round($used / $warehouse->capacity * 100)) : 0;
                                @endphp
                                <p class="text-gray-600 mt-2">Уровень {{ $warehouse->level }} · Вместимость {{ $used }} / {{ $warehouse->capacity }}</p>
                                <div class="h-3 bg-gray-200 rounded-full overflow-hidden mt-3" role="progressbar" aria-label="Заполнение склада" aria-valuenow="{{ $used }}" aria-valuemin="0" aria-valuemax="{{ $warehouse->capacity }}">
                                    <div class="h-full bg-blue-600" style="width: {{ $percent }}%"></div>
                                </div>
                                @if ($warehouse->level === 1 && ! $upgradeExists)
                                    <p class="text-sm text-gray-600 mt-4">Уровень II: металлолом ×20, медь ×8, электроника ×2. Вместимость 80.</p>
                                    <form method="POST" action="{{ route('warehouse.upgrade') }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-zinc-800 text-white rounded-lg">Улучшить склад</button>
                                    </form>
                                @elseif ($warehouse->level === 1)
                                    <p class="text-sm text-amber-700 mt-4">Расширение в процессе или уже запланировано.</p>
                                @else
                                    <p class="text-sm text-green-700 mt-4">Максимальный доступный уровень достигнут.</p>
                                @endif
                            @else
                                <p class="text-gray-600 mt-2">Восстановите склад через задания Центрального ИИ.</p>
                            @endif
                        </div>

                        @foreach ($buildingCatalog as $key => $definition)
                            @php
                                $building = $buildings->get($key);
                                $hasProject = $projects->contains('key', $key);
                                $prerequisitesMet = collect($definition['requires'] ?? [])->every(
                                    fn ($requiredKey) => $buildings->get($requiredKey)?->status === 'active'
                                );
                            @endphp
                            <div class="border rounded-xl p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-lg font-semibold">{{ $definition['name'] }}</h4>
                                    <span class="text-sm {{ $building && $building->status === 'active' ? 'text-green-700' : 'text-gray-500' }}">
                                        {{ $building && $building->status === 'active' ? 'Построен' : ($hasProject ? 'Строится' : 'Не построен') }}
                                    </span>
                                </div>
                                @if ($building)
                                    <p class="text-gray-600 mt-2">Уровень {{ $building->level }} · {{ $building->status === 'active' ? 'Активен' : $building->status }}</p>
                                    @if ($key === 'repair_bay')
                                        <p class="text-sm text-gray-500 mt-2">Ремонт робота пока не доступен.</p>
                                    @endif
                                @elseif ($hasProject)
                                    <p class="text-amber-700 mt-2">Передайте материалы в проект ниже.</p>
                                @elseif (! $prerequisitesMet)
                                    <p class="text-gray-600 mt-2">Для строительства сначала восстановите необходимые здания.</p>
                                @else
                                    <p class="text-gray-600 mt-2">{{ $definition['description'] }}</p>
                                    <p class="text-sm text-gray-600 mt-2">
                                        Материалы:
                                        @foreach ($definition['cost'] as $slug => $amount)
                                            {{ config("resources.{$slug}.name", $slug) }} ×{{ $amount }}{{ $loop->last ? '' : ', ' }}
                                        @endforeach
                                    </p>
                                    <form method="POST" action="{{ route('buildings.construct', $key) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-zinc-800 text-white rounded-lg">Начать строительство</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="mb-5">
                    <h3 class="text-xl font-bold">Строительные проекты</h3>
                    <p class="text-gray-600 mt-1">Активных: {{ $activeProjects->count() }} · Завершённых: {{ $finishedProjects->count() }}</p>
                </section>

                <div class="space-y-6">
                    @forelse ($projects as $project)
                        <div class="border rounded-xl p-5">

                            <div class="flex flex-wrap justify-between items-start gap-3 mb-4">
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
                                                style="width: {{ $requirement->required_amount > 0 ? min(100, ($requirement->delivered_amount / $requirement->required_amount) * 100) : 100 }}%;"
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