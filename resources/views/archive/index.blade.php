<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Архив экспедиций
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto pt-4 sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                <h3 class="text-2xl font-bold mb-2">
                    Бортовой журнал {{ $robot->name }}
                </h3>

                <p class="text-gray-600 mb-6">
                    Сводка завершённых экспедиций робота серии Zero.
                </p>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-white">
                        <h4 class="text-lg font-bold mb-4">
                            Статистика
                        </h4>

                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Всего экспедиций</span>
                                <strong>{{ $totalReports }}</strong>
                            </div>

                            <div class="flex justify-between">
                                <span>Получено XP</span>
                                <strong>{{ $totalXp }}</strong>
                            </div>

                            <div class="flex justify-between">
                                <span>Типов ресурсов</span>
                                <strong>{{ count($totalResources) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-white">
                        <h4 class="text-lg font-bold mb-4">
                            Всего добыто
                        </h4>

                        <div class="space-y-3">
                            @foreach($totalResources as $resourceName => $amount)
                                <div class="flex justify-between">
                                    <span>{{ $resourceName }}</span>
                                    <strong>×{{ $amount }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @forelse($reports as $report)
                    <div class="border border-gray-200 rounded-lg p-4 mb-4">
                        <div class="flex justify-between gap-4 mb-3">
                            <div>
                                <h4 class="font-bold text-lg">
                                    {{ $report->location_name }}
                                </h4>

                                <div class="text-sm text-gray-500">
                                    {{ $report->finished_at?->format('d.m.Y H:i') }}
                                </div>
                            </div>

                            <div class="text-right text-sm">
                                <div class="text-gray-500">
                                    XP
                                </div>

                                <div class="font-bold">
                                    +{{ $report->xp_gained }}
                                </div>
                            </div>
                        </div>

                        <div class="text-sm text-gray-700">
                            <div class="font-semibold mb-2">
                                Получено:
                            </div>

                            @if(!empty($report->resources))
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach($report->resources as $item)
                                        <li>
                                            {{ $item['resource'] }}
                                            ×{{ $item['amount'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-gray-500">
                                    Склад был заполнен. Робот ничего не смог унести.
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-gray-500">
                        Архив пока пуст. Завершите первую экспедицию.
                    </div>
                @endforelse

                <div class="mt-6">
                    {{ $reports->links() }}
                </div>

            </div>

        </div>
    </div>
</x-app-layout>