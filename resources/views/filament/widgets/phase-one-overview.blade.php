<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ $settings?->system_name ?? config('app.name') }}
        </x-slot>

        <x-slot name="description">
            {{ $settings?->managing_office_name ?? 'Managing Office' }}
        </x-slot>

        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Signed in as</p>
                <p class="font-semibold text-gray-950 dark:text-white">{{ $user->full_name }}</p>
            </div>

            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Authorization level</p>
                <p class="font-semibold text-gray-950 dark:text-white">
                    {{ $user->role->label() }}
                    @if ($user->isLevelTwo())
                        — Records Administrator
                    @else
                        — {{ $settings?->level_1_unit_label ?? 'Unit' }} Encoder
                    @endif
                </p>
            </div>

            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $settings?->level_1_unit_label ?? 'Organizational unit' }}
                </p>
                <p class="font-semibold text-gray-950 dark:text-white">
                    {{ $user->organizationalUnit?->unit_name ?? 'Managing-office access' }}
                </p>
            </div>
        </div>

        <div class="mt-6 rounded-xl bg-primary-50 p-4 text-sm text-primary-800 dark:bg-primary-950 dark:text-primary-200">
            @if ($user->isLevelTwo())
                Phase 2 reference data administration is available for document types and origins.
            @else
                Active reference values are ready for approved forms. Document registration begins only after the next approved phase gate.
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
