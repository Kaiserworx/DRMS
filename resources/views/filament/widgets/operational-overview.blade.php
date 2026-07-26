<x-filament-widgets::widget>
    <div class="space-y-6" wire:poll.30s>
        <x-filament::section>
            <x-slot name="heading">
                {{ $user->isLevelTwo() ? 'Managing-office overview' : ($user->organizationalUnit?->unit_name ?? 'Unit overview') }}
            </x-slot>

            <x-slot name="description">
                @if ($user->isLevelTwo())
                    Live managing-office counts scoped to your authorization level.
                @else
                    {{ $settings?->level_1_unit_label ?? 'Unit' }} Encoder dashboard with unit-scoped operational counts.
                @endif
            </x-slot>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($metricLabels as $key => $label)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">
                            {{ number_format($metrics[$key]) }}
                        </p>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        @if ($user->isLevelTwo())
            <x-filament::section>
                <x-slot name="heading">
                    {{ $settings?->receiving_box_label ?? 'Receiving Box' }} summary
                </x-slot>

                <x-slot name="description">
                    Ready items and the oldest waiting date for every configured box.
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">Organizational unit</th>
                                <th class="px-3 py-2 font-medium">Box location</th>
                                <th class="px-3 py-2 font-medium">Waiting</th>
                                <th class="px-3 py-2 font-medium">Oldest waiting</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse ($boxSummary as $box)
                                <tr>
                                    <td class="px-3 py-3 font-medium text-gray-950 dark:text-white">{{ $box['unit'] }}</td>
                                    <td class="px-3 py-3">{{ $box['location'] }}</td>
                                    <td class="px-3 py-3">{{ number_format($box['waiting']) }}</td>
                                    <td class="px-3 py-3">
                                        {{ $box['oldest_waiting_at']?->format('M j, Y H:i') ?? 'No waiting items' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-3 py-6 text-center text-gray-500" colspan="4">
                                        No receiving boxes are configured.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-widgets::widget>
