@php
    use App\Models\RecordAudit;
@endphp

<div class="flex flex-col gap-2.5">
    @forelse ($audits as $audit)
        <div class="rounded-2xl border border-gray-200 p-3 text-sm dark:border-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-bold text-gray-950 dark:text-white">{{ $audit->label }}</span>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $audit->created_at->translatedFormat('d F Y, H.i') }} WIB</span>
            </div>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                @php $removed = in_array($audit->action, [RecordAudit::ACTION_DELETED, RecordAudit::ACTION_BULK_DELETED], true); @endphp
                <span @class([
                    'font-bold',
                    'text-danger-600 dark:text-danger-400' => $removed,
                    'text-warning-600 dark:text-warning-400' => ! $removed,
                ])>{{ match ($audit->action) {
                    RecordAudit::ACTION_BULK_DELETED => 'Dihapus massal',
                    RecordAudit::ACTION_DELETED => 'Dihapus',
                    default => 'Diubah',
                } }}</span>
                oleh {{ $audit->actor->name }}
            </p>

            @if ($removed)
                <dl class="mt-2 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400">
                    @foreach ($audit->before ?? [] as $field => $value)
                        <div class="flex gap-2"><dt class="w-40 shrink-0 font-semibold">{{ $field }}</dt><dd class="min-w-0">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            @else
                <dl class="mt-2 grid gap-0.5 text-xs text-gray-600 dark:text-gray-400">
                    @foreach ($audit->changes() as $field => [$old, $new])
                        <div class="flex gap-2">
                            <dt class="w-40 shrink-0 font-semibold">{{ $field }}</dt>
                            <dd class="min-w-0">{{ $old ?? '—' }} → <span class="font-semibold text-gray-950 dark:text-white">{{ $new ?? '—' }}</span></dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if (filled($audit->reason))
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Alasan: {{ $audit->reason }}</p>
            @endif
        </div>
    @empty
        <p class="rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">Belum ada perubahan atau penghapusan.</p>
    @endforelse
</div>
