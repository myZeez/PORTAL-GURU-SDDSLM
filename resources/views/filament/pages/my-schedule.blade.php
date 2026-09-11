<x-filament-panels::page>
    <div class="flex flex-col gap-4">
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

        <div class="inline-flex w-fit items-center gap-2 rounded-full bg-primary-50 px-3.5 py-1.5 dark:bg-primary-400/10">
            <span class="h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-400"></span>
            <span class="text-sm font-bold text-primary-700 dark:text-primary-400">
                {{ $this->getTotalJp() }} JP Mengajar Hari Ini
            </span>
        </div>

        <div class="flex flex-col gap-2.5">
            @forelse ($this->getEntries() as $entry)
                <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <span
                        @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                            'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' => $entry['type'] === 'teaching',
                            'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => $entry['type'] === 'routine',
                        ])
                    >
                        @if ($entry['type'] === 'teaching')
                            <x-filament::icon icon="heroicon-o-pencil-square" class="h-4.5 w-4.5" />
                        @else
                            <x-filament::icon icon="heroicon-o-book-open" class="h-4.5 w-4.5" />
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $entry['label'] }}</p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $entry['subtitle'] }}</p>
                    </div>

                    <span class="shrink-0 text-xs font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
                        {{ $entry['timeSlot'] }}
                    </span>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Tidak ada jadwal untuk hari ini.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
