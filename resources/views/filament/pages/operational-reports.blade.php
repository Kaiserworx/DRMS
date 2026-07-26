<x-filament-panels::page>
    @once
        <style>
            .drms-report-filter-form {
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
            }

            .drms-report-primary-grid,
            .drms-report-secondary-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
            }

            .drms-report-field {
                display: flex;
                min-width: 0;
                flex-direction: column;
                gap: 0.375rem;
            }

            .drms-report-field > label {
                display: block;
                color: rgb(75 85 99);
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1rem;
            }

            .drms-report-primary-grid .drms-report-field > label {
                font-size: 0.7rem;
                letter-spacing: 0.045em;
                text-transform: uppercase;
            }

            .dark .drms-report-field > label {
                color: rgb(209 213 219);
            }

            .drms-report-optional {
                border: 1px solid rgb(229 231 235);
                border-radius: 0.75rem;
                background: rgb(249 250 251);
                padding: 1rem;
            }

            .dark .drms-report-optional {
                border-color: rgb(255 255 255 / 0.1);
                background: rgb(255 255 255 / 0.035);
            }

            .drms-report-filter-intro {
                margin-bottom: 0.875rem;
            }

            .drms-report-filter-intro h3 {
                color: rgb(17 24 39);
                font-size: 0.875rem;
                font-weight: 600;
                line-height: 1.25rem;
            }

            .dark .drms-report-filter-intro h3 {
                color: white;
            }

            .drms-report-filter-intro p {
                margin-top: 0.125rem;
                color: rgb(107 114 128);
                font-size: 0.75rem;
                line-height: 1rem;
            }

            .dark .drms-report-filter-intro p {
                color: rgb(156 163 175);
            }

            .drms-report-actions {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 0.75rem;
                border-top: 1px solid rgb(229 231 235);
                padding-top: 1rem;
            }

            .dark .drms-report-actions {
                border-color: rgb(255 255 255 / 0.1);
            }

            @media (min-width: 640px) {
                .drms-report-secondary-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 900px) {
                .drms-report-primary-grid {
                    grid-template-columns: minmax(0, 2fr) repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1280px) {
                .drms-report-secondary-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }
        </style>
    @endonce

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Operational report filters</x-slot>
            <x-slot name="description">
                On-screen rows and CSV exports always use the same authorization scope.
            </x-slot>

            <form class="drms-report-filter-form" wire:submit="runReport">
                <div class="drms-report-primary-grid">
                    <div class="drms-report-field">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300" for="report-type">
                            Report type
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select id="report-type" wire:model="report">
                                @foreach ($reportOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                        @error('report') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="drms-report-field">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300" for="report-date-from">
                            Date from
                        </label>
                        <x-filament::input.wrapper>
                            <input
                                class="fi-input"
                                id="report-date-from"
                                type="date"
                                wire:model="filters.from"
                            >
                        </x-filament::input.wrapper>
                        @error('from') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="drms-report-field">
                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300" for="report-date-to">
                            Date to
                        </label>
                        <x-filament::input.wrapper>
                            <input
                                class="fi-input"
                                id="report-date-to"
                                type="date"
                                wire:model="filters.to"
                            >
                        </x-filament::input.wrapper>
                        @error('to') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="drms-report-optional">
                    <div class="drms-report-filter-intro">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Optional filters</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            Narrow the report further. Available values are already limited to your authorized records.
                        </p>
                    </div>

                    <div class="drms-report-secondary-grid">
                        <div class="drms-report-field">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="report-unit">
                                Organizational unit
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="report-unit" wire:model="filters.organizational_unit_id">
                                    <option value="">All authorized units</option>
                                    @foreach ($filterOptions['units'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <div class="drms-report-field">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="report-document-type">
                                Document type
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="report-document-type" wire:model="filters.document_type_id">
                                    <option value="">All authorized types</option>
                                    @foreach ($filterOptions['types'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <div class="drms-report-field">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="report-origin">
                                Origin
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="report-origin" wire:model="filters.origin_id">
                                    <option value="">All authorized origins</option>
                                    @foreach ($filterOptions['origins'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <div class="drms-report-field">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="report-user">
                                Transaction user
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select id="report-user" wire:model="filters.performed_by">
                                    <option value="">All authorized users</option>
                                    @foreach ($filterOptions['users'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                </div>

                <div class="drms-report-actions">
                    <x-filament::button type="submit">Run report</x-filament::button>
                    <x-filament::button color="gray" icon="heroicon-o-arrow-down-tray" tag="a" :href="$exportUrl">
                        Export scoped CSV
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $reportOptions[$report] }}</x-slot>
            <x-slot name="description">
                {{ number_format($rows->total()) }} authorized {{ Str::plural('row', $rows->total()) }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <tr>
                            @foreach ($columns as $label)
                                <th class="whitespace-nowrap px-3 py-2 font-medium">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($rows as $row)
                            <tr>
                                @foreach (array_keys($columns) as $key)
                                    <td class="max-w-xs px-3 py-3 align-top text-gray-700 dark:text-gray-200">
                                        {{ filled($row[$key] ?? null) ? $row[$key] : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-8 text-center text-gray-500" colspan="{{ count($columns) }}">
                                    No authorized records match these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="mt-4">{{ $rows->links() }}</div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
