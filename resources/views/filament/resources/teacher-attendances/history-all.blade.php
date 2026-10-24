@php
    $statusLabel = fn (?array $snapshot): string => $snapshot
        ? (\App\Enums\AttendanceStatus::tryFrom($snapshot['status'])?->getLabel() ?? $snapshot['status'])
        : 'Belum ada catatan';
    $calendarLabel = fn (string $status): string => \App\Enums\CalendarDayStatus::tryFrom($status)?->getLabel() ?? $status;
@endphp

<div class="flex flex-col gap-5">
    <section class="flex flex-col gap-2">
        <h3 class="text-sm font-bold text-gray-950 dark:text-white">Absensi diisi atau dikoreksi atas nama guru — {{ $month->translatedFormat('F Y') }}</h3>
        @forelse ($attendanceLogs as $log)
            <div class="rounded-2xl border border-gray-200 p-3 text-sm dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-bold text-gray-950 dark:text-white">
                        {{ $log->teacher->name }} · {{ $log->date->translatedFormat('d F Y') }}
                    </span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $log->created_at->translatedFormat('d F Y, H.i') }} WIB</span>
                </div>
                <p class="mt-1 text-gray-700 dark:text-gray-300">
                    {{ $statusLabel($log->before) }} → <span class="font-semibold">{{ $statusLabel($log->after) }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">· oleh {{ $log->actor->name }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alasan: {{ $log->reason }}</p>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">Belum ada pengisian atau koreksi pada bulan ini.</p>
        @endforelse
    </section>

    <section class="flex flex-col gap-2">
        <h3 class="text-sm font-bold text-gray-950 dark:text-white">Perubahan status tanggal (libur / kegiatan)</h3>
        @forelse ($calendarLogs as $log)
            <div class="rounded-2xl border border-gray-200 p-3 text-sm dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-bold text-gray-950 dark:text-white">{{ $log->date->translatedFormat('l, d F Y') }}</span>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $log->created_at->translatedFormat('d F Y, H.i') }} WIB</span>
                </div>
                <p class="mt-1 text-gray-700 dark:text-gray-300">
                    {{ $calendarLabel($log->before_status) }} → <span class="font-semibold">{{ $calendarLabel($log->after_status) }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">· oleh {{ $log->actor->name }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Keterangan: {{ $log->notes ?: '—' }}</p>
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">Belum ada perubahan kalender pada bulan ini.</p>
        @endforelse
    </section>
</div>
