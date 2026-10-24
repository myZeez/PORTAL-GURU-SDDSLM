@php
    $simpati = $this->getSimpatiSummary();
    $simpatiMonth = $this->getSimpatiMonthSummary();
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-bold text-gray-950 dark:text-white">Pengisian SIMPATI Hari Ini</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Dicentang pendamping wali kelas di tiap kelas
                @if (\App\Filament\Pages\SimpatiChecklist::canAccess())
                    · <a href="{{ \App\Filament\Pages\SimpatiChecklist::getUrl() }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Buka checklist</a>
                @endif
            </p>
        </div>

        @if ($simpati['required'])
            <p class="text-3xl font-bold text-primary-600 dark:text-primary-400">{{ $simpati['percentage'] }}%</p>
        @endif
    </div>

    @if (! $simpati['required'])
        <p class="mt-3 rounded-xl bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
            Hari ini bukan hari wajib SIMPATI (libur), jadi tidak dihitung.
        </p>
    @else
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $simpati['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full rounded-full bg-primary-600" style="width: {{ $simpati['percentage'] }}%"></div>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-success-50 px-2 py-2 dark:bg-success-400/10">
                <p class="text-xl font-bold text-success-700 dark:text-success-400">{{ $simpati['filled'] }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Sudah mengisi</p>
            </div>
            <div class="rounded-xl bg-danger-50 px-2 py-2 dark:bg-danger-400/10">
                <p class="text-xl font-bold text-danger-700 dark:text-danger-400">{{ $simpati['missing'] }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belum mengisi</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-2 py-2 dark:bg-white/5">
                <p class="text-xl font-bold text-gray-950 dark:text-white">{{ $simpati['total'] }}</p>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total kelas</p>
            </div>
        </div>

        @if ($simpati['unfilled']->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($simpati['unfilled'] as $classroom)
                    <span
                        class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-300"
                        title="{{ $classroom->label }} — pendamping: {{ $classroom->assistantTeacher?->name ?? 'belum ditetapkan' }}"
                    >
                        {{ $classroom->code }}
                    </span>
                @endforeach
            </div>
        @endif
    @endif

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Bulan ini: {{ $simpatiMonth['filled'] }} dari {{ $simpatiMonth['possible'] }} isian wajib ({{ $simpatiMonth['percentage'] }}%).
    </p>
</div>
