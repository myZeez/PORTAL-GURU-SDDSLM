<x-filament-panels::page>
    @php
        $semester = $this->getSemester();
        $isAll = $matrixView === \App\Filament\Pages\ScheduleMatrix::VIEW_ALL;
        $perspectives = [
            \App\Filament\Pages\ScheduleMatrix::VIEW_ALL => 'Seluruh Kelas',
            \App\Filament\Pages\ScheduleMatrix::VIEW_CLASS => 'Satu Kelas',
        ];
        if ($this->canViewTeacherPerspective()) {
            $perspectives[\App\Filament\Pages\ScheduleMatrix::VIEW_TEACHER] = 'Satu Guru';
        }
        $selectClass = 'w-full rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white';
        $labelClass = 'mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400';
    @endphp

    <div class="flex flex-col gap-4">
        @if ($this->getSemesters()->isEmpty() || ! $semester)
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Belum ada semester aktif. Aktifkan semester di menu Tahun Ajaran & Semester dulu.
            </div>
        @else
            @php
                $grid = $this->getGrid();
                $summary = $this->summarize($grid);
            @endphp

            <div class="inline-flex w-fit max-w-full flex-wrap rounded-full bg-gray-100 p-1 dark:bg-white/5" role="tablist" aria-label="Tampilan matriks">
                @foreach ($perspectives as $value => $label)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $matrixView === $value ? 'true' : 'false' }}"
                        wire:click="setMatrixView('{{ $value }}')"
                        @class([
                            'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                            'bg-primary-600 text-white shadow-sm' => $matrixView === $value,
                            'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => $matrixView !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="matrix-semester" class="{{ $labelClass }}">Tahun Ajaran & Semester</label>
                    <select id="matrix-semester" wire:model.live="semesterId" class="{{ $selectClass }}">
                        @foreach ($this->getSemesters() as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}{{ $option->is_active ? ' (aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="matrix-classroom" class="{{ $labelClass }}">Kelas / Rombel</label>
                    <select id="matrix-classroom" wire:model.live="classroomId" class="{{ $selectClass }}">
                        @if ($isAll)
                            <option value="">Semua rombel</option>
                        @endif
                        @foreach ($this->getClassrooms() as $option)
                            <option value="{{ $option->id }}">{{ $option->label }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($isAll || $matrixView === \App\Filament\Pages\ScheduleMatrix::VIEW_TEACHER)
                    <div>
                        <label for="matrix-teacher" class="{{ $labelClass }}">Guru</label>
                        <select id="matrix-teacher" wire:model.live="teacherId" class="{{ $selectClass }}">
                            @if ($isAll)
                                <option value="">Semua guru</option>
                            @endif
                            @foreach ($this->getTeachers() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($isAll)
                    <div>
                        <label for="matrix-subject" class="{{ $labelClass }}">Mata Pelajaran</label>
                        <select id="matrix-subject" wire:model.live="subjectId" class="{{ $selectClass }}">
                            <option value="">Semua mata pelajaran</option>
                            @foreach ($this->getSubjects() as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @unless ($isAll)
                    <button
                        type="button"
                        wire:click="selectDay(0)"
                        @class([
                            'rounded-full px-4 py-1.5 text-sm font-semibold transition',
                            'bg-primary-600 text-white' => $day === 0,
                            'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-white/10' => $day !== 0,
                        ])
                    >
                        Semua Hari
                    </button>
                @endunless
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

                <button
                    type="button"
                    wire:click="resetFilters"
                    class="ml-auto rounded-full border border-gray-200 px-4 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                >
                    Atur Ulang Filter
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-primary-100 ring-2 ring-primary-500 dark:bg-primary-400/20"></span>Jadwal Anda</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-dashed border-gray-400"></span>Kosong</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-gray-100 dark:bg-white/10"></span>Rutinitas sekolah</span>
                @if ($this->canSeeClashes())
                    <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-danger-100 ring-2 ring-danger-500 dark:bg-danger-400/20"></span>Bentrok</span>
                @endif
            </div>

            @if ($this->canSeeClashes())
                <div class="flex flex-wrap gap-2">
                    <span @class([
                        'inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-bold',
                        'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => $summary['clashes'] > 0,
                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $summary['clashes'] === 0,
                    ])>
                        {{ $summary['clashes'] }} slot bentrok
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3.5 py-1.5 text-sm font-bold text-gray-700 dark:bg-white/5 dark:text-gray-300">
                        {{ $summary['empty'] }} slot kosong
                    </span>
                </div>
            @endif

            @if (empty($grid['columns']))
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Tidak ada data untuk ditampilkan dengan filter ini.
                </div>
            @else
                <div @class(['overflow-x-auto rounded-2xl border border-gray-200 dark:border-white/10', 'hidden md:block' => ! $isAll])>
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-white/5">
                                <th class="sticky left-0 z-10 min-w-[92px] bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                    Jam
                                </th>
                                @foreach ($grid['columns'] as $column)
                                    <th class="min-w-[104px] border-l border-gray-200 px-2 py-2 text-center text-xs font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">
                                        {{ $column['label'] }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grid['rows'] as $row)
                                <tr class="border-t border-gray-200 dark:border-white/10">
                                    <td class="sticky left-0 z-10 bg-white px-3 py-2 text-xs font-semibold whitespace-nowrap text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                        {{ $row['timeSlot']->label }}
                                    </td>

                                    @if ($row['band'] !== null)
                                        <td
                                            colspan="{{ count($grid['columns']) }}"
                                            class="border-l border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400"
                                        >
                                            <span class="sticky left-28 inline-block">{{ $row['band'] }}</span>
                                        </td>
                                    @else
                                        @foreach ($grid['columns'] as $column)
                                            <td class="border-l border-gray-200 p-1 align-top dark:border-white/10">
                                                @include('filament.pages.partials.schedule-matrix-cell', ['cell' => $row['cells'][$column['key']] ?? null])
                                            </td>
                                        @endforeach
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @unless ($isAll)
                    <div class="flex flex-col gap-4 md:hidden">
                        @foreach ($grid['columns'] as $column)
                            <section class="flex flex-col gap-2">
                                <h2 class="text-sm font-bold tracking-wide text-gray-950 uppercase dark:text-white">{{ $column['label'] }}</h2>
                                @foreach ($grid['rows'] as $row)
                                    @php $cell = $row['cells'][$column['key']] ?? null; @endphp
                                    <div class="flex items-stretch gap-2">
                                        <span class="w-24 shrink-0 pt-2 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $row['timeSlot']->label }}</span>
                                        <div class="min-w-0 flex-1">
                                            @include('filament.pages.partials.schedule-matrix-cell', ['cell' => $cell])
                                        </div>
                                    </div>
                                @endforeach
                            </section>
                        @endforeach
                    </div>
                @endunless

                <p class="text-xs text-gray-400 dark:text-gray-500">
                    @if ($isAll)
                        ↔ Geser tabel ke kanan untuk melihat semua rombel.
                    @endif
                    Data diambil langsung dari Jadwal Pelajaran dan ikut berubah begitu jadwal disimpan.
                </p>

                @if ($this->canSeeClashes() && ($descriptions = $this->getClashDescriptions()) !== [])
                    <details class="rounded-2xl border border-danger-200 bg-danger-50/50 p-4 dark:border-danger-400/20 dark:bg-danger-400/5">
                        <summary class="cursor-pointer text-sm font-bold text-danger-700 dark:text-danger-400">
                            Daftar bentrok pada semester ini ({{ count($descriptions) }})
                        </summary>
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                            @foreach ($descriptions as $description)
                                <li>{{ $description }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        @endif
    </div>
</x-filament-panels::page>
