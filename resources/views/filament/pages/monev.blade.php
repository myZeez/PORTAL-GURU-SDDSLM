<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <label class="text-sm font-bold text-gray-950 dark:text-white">Bulan</label>
            <input
                type="month"
                wire:model.live="month"
                class="fi-input rounded-lg border-none bg-white text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
            />
        </div>

        <div class="flex flex-col gap-2.5">
            @foreach ($this->getTeacherRows() as $row)
                @php
                    $lightClasses = match ($row['status']) {
                        'merah' => 'bg-danger-50 text-danger-600 dark:bg-danger-400/10 dark:text-danger-400',
                        'kuning' => 'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400',
                        default => 'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400',
                    };
                    $isSelected = $this->selectedTeacherId === $row['teacher']->id;
                @endphp
                <button
                    type="button"
                    wire:click="selectTeacher({{ $row['teacher']->id }})"
                    @class([
                        'flex items-center gap-3 rounded-2xl border bg-white p-3 text-left dark:bg-gray-900',
                        'border-primary-400 ring-1 ring-primary-400' => $isSelected,
                        'border-gray-200 dark:border-white/10' => ! $isSelected,
                    ])
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $lightClasses }}">
                        <x-filament::icon icon="heroicon-o-signal" class="h-4.5 w-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">{{ $row['teacher']->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Kehadiran {{ $row['attendance_percentage'] }}%
                            · Penilaian {{ $row['has_assessment'] ? 'ada' : 'belum ada' }}
                            · Supervisi {{ $row['supervision_count'] }}x
                        </p>
                    </div>

                    @if ($row['journal_missing_today'])
                        <span class="shrink-0 text-[11px] font-bold whitespace-nowrap text-danger-600 dark:text-danger-400">
                            Jurnal hari ini kosong
                        </span>
                    @endif
                </button>
            @endforeach
        </div>

        @if ($teacher = $this->getSelectedTeacher())
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <p class="mb-3 text-sm font-bold text-gray-950 dark:text-white">
                    Indikator Jurnal 4 Minggu Terakhir — {{ $teacher->name }}
                </p>

                <div class="flex flex-col gap-2">
                    @foreach ($this->getJournalWeeks() as $week)
                        <div class="flex items-center justify-between gap-2 rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300">{{ $week['label'] }}</span>
                            <div class="flex gap-3 text-xs font-bold">
                                <span class="text-success-600 dark:text-success-400">Tepat waktu: {{ $week['tepat_waktu'] }}</span>
                                <span class="text-warning-600 dark:text-warning-400">Terlambat: {{ $week['terlambat'] }}</span>
                                <span class="text-danger-600 dark:text-danger-400">Kosong: {{ $week['kosong'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
