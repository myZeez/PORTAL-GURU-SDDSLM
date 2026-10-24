<x-filament-panels::page>
    @php
        $modes = [
            \App\Filament\Pages\MySchedule::MODE_DAY => 'Per Hari',
            \App\Filament\Pages\MySchedule::MODE_WEEK => 'Mingguan',
            \App\Filament\Pages\MySchedule::MODE_DATE => 'Per Tanggal',
        ];
    @endphp

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-full bg-gray-100 p-1 dark:bg-white/5" role="tablist" aria-label="Tampilan jadwal">
                @foreach ($modes as $value => $label)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $mode === $value ? 'true' : 'false' }}"
                        wire:click="setMode('{{ $value }}')"
                        @class([
                            'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                            'bg-primary-600 text-white shadow-sm' => $mode === $value,
                            'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $mode !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <button
                type="button"
                wire:click="selectToday"
                class="rounded-full border border-gray-200 px-4 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
            >
                Hari Ini
            </button>
        </div>

        @if ($mode === \App\Filament\Pages\MySchedule::MODE_DAY)
            <div class="flex flex-wrap gap-2">
                @foreach (\App\Enums\SchoolDay::cases() as $schoolDay)
                    <button
                        type="button"
                        wire:click="selectDay({{ $schoolDay->value }})"
                        @class([
                            'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                            'bg-primary-600 text-white' => $day === $schoolDay->value,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10' => $day !== $schoolDay->value,
                        ])
                    >
                        {{ $schoolDay->getLabel() }}
                    </button>
                @endforeach
            </div>
        @endif

        @if ($mode === \App\Filament\Pages\MySchedule::MODE_DATE)
            <div class="flex flex-wrap items-center gap-3">
                <label for="schedule-date" class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tanggal</label>
                <input
                    id="schedule-date"
                    type="date"
                    wire:model.live="date"
                    class="rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                />
                @if ($this->selectedDate())
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $this->selectedDate()->translatedFormat('l, d F Y') }}
                    </span>
                @endif
            </div>

            @php $dateStatus = $this->getSelectedDateStatus(); @endphp
            @if ($dateStatus && ! $dateStatus->requiresJournal())
                <div class="rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-medium text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-300">
                    Tanggal ini berstatus {{ $dateStatus->getLabel() }}.
                </div>
            @endif
        @endif

        <div class="inline-flex w-fit items-center gap-2 rounded-full bg-primary-50 px-3.5 py-1.5 dark:bg-primary-400/10">
            <span class="h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-400"></span>
            <span class="text-sm font-bold text-primary-700 dark:text-primary-400">
                {{ $this->getTotalJp() }} {{ $this->getJpLabel() }}
            </span>
        </div>

        @if ($mode === \App\Filament\Pages\MySchedule::MODE_WEEK)
            <div class="flex flex-col gap-5">
                @foreach ($this->getWeek() as $week)
                    <section class="flex flex-col gap-2.5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold tracking-wide text-gray-950 uppercase dark:text-white">{{ $week['day']->getLabel() }}</h2>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $week['jp'] }} JP</span>
                        </div>
                        @forelse ($week['entries'] as $entry)
                            @include('filament.pages.partials.my-schedule-entry', ['entry' => $entry])
                        @empty
                            <p class="rounded-2xl border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                                Tidak ada jadwal pada hari {{ $week['day']->getLabel() }}.
                            </p>
                        @endforelse
                    </section>
                @endforeach
            </div>
        @else
            <div class="flex flex-col gap-2.5">
                @forelse ($this->getEntries() as $entry)
                    @include('filament.pages.partials.my-schedule-entry', ['entry' => $entry])
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        @if ($mode === \App\Filament\Pages\MySchedule::MODE_DATE)
                            Tidak ada jadwal pada tanggal ini.
                        @elseif ($this->isToday())
                            Tidak ada jadwal untuk hari ini.
                        @else
                            Tidak ada jadwal pada hari {{ \App\Enums\SchoolDay::from($day)->getLabel() }}.
                        @endif
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</x-filament-panels::page>
