<x-filament-widgets::widget>
    @once
        <style>
            .drms-level-one-box {
                display: grid;
                align-items: center;
                gap: 1.5rem;
            }

            .drms-level-one-box-qr {
                display: flex;
                justify-content: center;
            }

            .drms-level-one-box-qr svg {
                width: min(100%, 14rem);
                height: auto;
            }

            .drms-level-one-box-details {
                display: flex;
                min-width: 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            @media (min-width: 768px) {
                .drms-level-one-box {
                    grid-template-columns: minmax(15rem, 0.8fr) minmax(0, 1.2fr);
                }
            }
        </style>
    @endonce

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
        @else
            <x-filament::section>
                <x-slot name="heading">
                    Your {{ $settings?->receiving_box_label ?? 'Receiving Box' }}
                </x-slot>

                <x-slot name="description">
                    Scan the QR code or use the link to open your unit's ready-for-pickup inventory.
                </x-slot>

                @if ($levelOneReceivingBox)
                    <div class="drms-level-one-box">
                        <div class="drms-level-one-box-qr">
                            <div
                                class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10"
                                role="img"
                                aria-label="QR code for {{ $user->organizationalUnit->unit_name }} receiving box"
                            >
                                {!! $levelOneReceivingBox['svg'] !!}
                            </div>
                        </div>

                        <div class="drms-level-one-box-details">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Organizational unit</p>
                                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                                    {{ $user->organizationalUnit->unit_name }}
                                </p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Box location</p>
                                <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $levelOneReceivingBox['location'] ?: 'Not specified' }}
                                </p>
                            </div>

                            <x-filament::button
                                tag="a"
                                :href="$levelOneReceivingBox['url']"
                                icon="heroicon-o-qr-code"
                            >
                                Open {{ $settings?->receiving_box_label ?? 'Receiving Box' }}
                            </x-filament::button>

                            <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                The QR code identifies your unit's box. Sign-in and an exact organizational-unit match are still required.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-gray-300 px-6 py-8 text-center dark:border-white/20">
                        <p class="font-medium text-gray-950 dark:text-white">No active receiving box is configured.</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Contact a Level 2 administrator to configure an active box for your organizational unit.
                        </p>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-widgets::widget>
