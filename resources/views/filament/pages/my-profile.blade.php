<x-filament-panels::page>
    @php
        $formatTime = fn (string $time): string => str_replace(':', '.', substr($time, 0, 5));
        $weeklySchedule = collect($this->getWeeklySchedule())->filter(fn (array $day): bool => count($day['entries']) > 0);
    @endphp

    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-bold text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                {{ auth()->user()->code }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ auth()->user()->name }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ auth()->user()->position }} · {{ auth()->user()->email }}</p>
            </div>
        </div>

        <div class="inline-flex w-fit items-center gap-2 rounded-full bg-primary-50 px-3.5 py-1.5 dark:bg-primary-400/10">
            <span class="h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-400"></span>
            <span class="text-sm font-bold text-primary-700 dark:text-primary-400">
                {{ $this->getTotalJp() }} JP Total Beban Kerja
            </span>
        </div>

        <div class="flex flex-col gap-2.5">
            <p class="text-sm font-bold text-gray-950 dark:text-white">Jadwal Mengajar</p>

            @forelse ($weeklySchedule as $day)
                <div class="rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <p class="mb-2 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $day['label'] }}</p>
                    <div class="flex flex-col gap-2">
                        @foreach ($day['entries'] as $entry)
                            <div class="flex items-center gap-3">
                                <span class="shrink-0 text-xs font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
                                    {{ $formatTime($entry['starts_at']) }}–{{ $formatTime($entry['ends_at']) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $entry['subject'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $entry['classroom'] }}</p>
                                </div>
                                <span class="shrink-0 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $entry['jp'] }} JP</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Belum ada jadwal mengajar.
                </div>
            @endforelse
        </div>

        <div class="flex flex-col gap-2.5">
            <p class="text-sm font-bold text-gray-950 dark:text-white">Tugas Utama</p>

            @forelse ($this->getTeachingLoad() as $load)
                <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $load['subject'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $load['classroom'] }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $load['jp'] }} JP</span>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Belum ada tugas utama.
                </div>
            @endforelse
        </div>

        <div class="flex flex-col gap-2.5">
            <p class="text-sm font-bold text-gray-950 dark:text-white">Tugas Tambahan</p>

            @forelse ($this->getAdditionalDuties() as $duty)
                <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $duty->title }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $duty->type->getLabel() }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $duty->jp }} JP</span>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Belum ada tugas tambahan.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
