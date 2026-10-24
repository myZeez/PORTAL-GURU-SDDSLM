@php
    $statusLabel = fn (?array $snapshot): string => $snapshot
        ? (\App\Enums\AttendanceStatus::tryFrom($snapshot['status'])?->getLabel() ?? $snapshot['status'])
        : 'Belum ada catatan';
@endphp

<div class="flex flex-col gap-3">
    @forelse ($logs as $log)
        <div class="rounded-2xl border border-gray-200 p-3 text-sm dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-bold text-gray-950 dark:text-white">
                    {{ $log->action === \App\Models\TeacherAttendanceLog::ACTION_CREATED ? 'Diisi atas nama guru' : 'Dikoreksi' }}
                    oleh {{ $log->actor->name }}
                </span>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $log->created_at->translatedFormat('d F Y, H.i') }} WIB</span>
            </div>
            <p class="mt-1 text-gray-700 dark:text-gray-300">
                {{ $statusLabel($log->before) }} → <span class="font-semibold">{{ $statusLabel($log->after) }}</span>
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Alasan: {{ $log->reason }}</p>
        </div>
    @empty
        <p class="rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            Belum ada perubahan yang dicatat untuk absensi ini. Absensi yang diisi guru sendiri tidak masuk riwayat.
        </p>
    @endforelse
</div>
