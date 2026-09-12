<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        @if (! $this->isApplicable())
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Kepala Sekolah tidak perlu mengisi absensi. Lihat rekap di menu Absensi Guru.
            </div>
        @elseif (! $this->isTodayFillable())
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Hari ini bukan hari efektif, jadi tidak perlu mengisi absensi.
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                @if ($this->getRecord()?->is_late)
                    <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-warning-50 px-3 py-1 text-xs font-bold text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                        Terlambat diisi
                    </div>
                @endif

                {{ $this->form }}
            </div>
        @endif

        @if ($this->isApplicable())
        <div class="flex flex-col gap-2.5">
            <p class="text-sm font-bold text-gray-950 dark:text-white">Riwayat 14 Hari Terakhir</p>

            @forelse ($this->getHistory() as $entry)
                @php
                    $badgeClasses = match ($entry->status->getColor()) {
                        'success' => 'bg-success-50 text-success-600 dark:bg-success-400/10 dark:text-success-400',
                        'info' => 'bg-info-50 text-info-600 dark:bg-info-400/10 dark:text-info-400',
                        'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-400/10 dark:text-warning-400',
                        default => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400',
                    };
                @endphp
                <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $badgeClasses }}">
                        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-4.5 w-4.5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-gray-950 dark:text-white">
                            {{ $entry->date->translatedFormat('l, d/m/Y') }}
                        </p>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            @if ($entry->status === \App\Enums\AttendanceStatus::Cuti && $entry->leave_starts_on)
                                Cuti {{ $entry->leave_starts_on->format('d/m') }}–{{ $entry->leave_ends_on->format('d/m') }}
                            @else
                                {{ $entry->notes ?: '—' }}
                            @endif
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <x-filament::badge :color="$entry->status->getColor()">
                            {{ $entry->status->getLabel() }}
                        </x-filament::badge>
                        @if ($entry->is_late)
                            <span class="text-[11px] font-bold text-warning-600 dark:text-warning-400">Terlambat</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Belum ada riwayat absensi.
                </div>
            @endforelse
        </div>
        @endif
    </div>
</x-filament-panels::page>
