@php
    $coverage = $this->getCoverage();
    $classrooms = $coverage['classrooms'];
    $teachers = $coverage['teachers'];
@endphp

<div class="flex flex-col gap-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Siapa yang sudah melaksanakan penilaian sumatif{{ $coverage['semester'] ? ' pada semester '.$coverage['semester'] : '' }}, berdasarkan yang dicatat guru.
    </p>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Rombel yang sudah melaksanakan</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $classrooms['done'] }}<span class="text-base font-semibold text-gray-400"> / {{ $classrooms['total'] }}</span></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Guru yang sudah mencatat</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $teachers['done'] }}<span class="text-base font-semibold text-gray-400"> / {{ $teachers['total'] }}</span></p>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Penilaian tercatat</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $coverage['assessments'] }}<span class="text-base font-semibold text-gray-400"> · {{ $coverage['completed'] }} selesai</span></p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        <details class="group rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl p-4 select-none [&::-webkit-details-marker]:hidden">
                <span class="text-sm font-bold text-gray-950 dark:text-white">
                    Rombel yang belum melaksanakan
                    <span @class([
                        'ml-1 rounded-full px-2 py-0.5 text-xs font-bold',
                        'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' => $classrooms['missing']->isNotEmpty(),
                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $classrooms['missing']->isEmpty(),
                    ])>{{ $classrooms['missing']->count() }}</span>
                </span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" />
            </summary>
            <div class="flex flex-wrap gap-2 border-t border-gray-100 p-4 dark:border-white/5">
                @forelse ($classrooms['missing'] as $classroom)
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $classroom->label }}</span>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Semua rombel sudah melaksanakan.</p>
                @endforelse
            </div>
        </details>

        <details class="group rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-2xl p-4 select-none [&::-webkit-details-marker]:hidden">
                <span class="text-sm font-bold text-gray-950 dark:text-white">
                    Guru yang belum mencatat
                    <span @class([
                        'ml-1 rounded-full px-2 py-0.5 text-xs font-bold',
                        'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' => $teachers['missing']->isNotEmpty(),
                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $teachers['missing']->isEmpty(),
                    ])>{{ $teachers['missing']->count() }}</span>
                </span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180" />
            </summary>
            <ul class="flex flex-col gap-1 border-t border-gray-100 p-4 text-sm text-gray-700 dark:border-white/5 dark:text-gray-300">
                @forelse ($teachers['missing'] as $teacher)
                    <li>{{ $teacher->name }}</li>
                @empty
                    <li class="text-gray-500 dark:text-gray-400">Semua guru pengajar sudah mencatat.</li>
                @endforelse
            </ul>
        </details>
    </div>
</div>
