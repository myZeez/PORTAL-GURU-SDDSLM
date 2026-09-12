<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        @if (! $this->isApplicable())
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Kepala Sekolah tidak mengisi penggantian. Lihat riwayat di menu Penggantian Guru.
            </div>
        @else
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            {{ $this->form }}
        </div>

        <div class="flex flex-col gap-2.5">
            <p class="text-sm font-bold text-gray-950 dark:text-white">Riwayat Penggantian</p>

            @forelse ($this->getHistory() as $entry)
                @php
                    $iAmSubstitute = $entry->substitute_teacher_id === auth()->id();
                @endphp
                <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <span
                        @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                            'bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400' => $iAmSubstitute,
                            'bg-gray-100 text-gray-400 dark:bg-white/5 dark:text-gray-500' => ! $iAmSubstitute,
                        ])
                    >
                        <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4.5 w-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">
                            {{ $entry->schedule->classroom->label }} — {{ $entry->schedule->subject->name }}
                        </p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            @if ($iAmSubstitute)
                                Menggantikan {{ $entry->schedule->teacher->name }}
                            @else
                                Digantikan oleh {{ $entry->substituteTeacher->name }}
                            @endif
                            · {{ $entry->schedule->timeSlot->label }}
                        </p>
                    </div>

                    <span class="shrink-0 text-xs font-bold whitespace-nowrap text-gray-700 dark:text-gray-300">
                        {{ $entry->date->format('d/m/Y') }}
                    </span>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Belum ada riwayat penggantian.
                </div>
            @endforelse
        </div>
        @endif
    </div>
</x-filament-panels::page>
