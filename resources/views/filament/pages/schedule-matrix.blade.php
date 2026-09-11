<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap gap-2">
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
        </div>

        @if (! \App\Models\Semester::current())
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Belum ada semester aktif. Aktifkan semester di menu Tahun Ajaran & Semester dulu.
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-white/10">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-white/5">
                            <th class="sticky left-0 z-10 min-w-[92px] bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                                Jam
                            </th>
                            @foreach ($this->getClassrooms() as $classroom)
                                <th class="min-w-[104px] border-l border-gray-200 px-2 py-2 text-center text-xs font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">
                                    {{ $classroom->code }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getRows() as $row)
                            <tr class="border-t border-gray-200 dark:border-white/10">
                                <td class="sticky left-0 z-10 bg-white px-3 py-2 text-xs font-semibold whitespace-nowrap text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                                    {{ $row['timeSlot']->label }}
                                </td>

                                @if ($row['type'] === 'routine')
                                    <td
                                        colspan="{{ $this->getClassrooms()->count() }}"
                                        class="border-l border-gray-200 bg-gray-50 px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400"
                                    >
                                        {{ $row['label'] }}
                                    </td>
                                @else
                                    @foreach ($this->getClassrooms() as $classroom)
                                        @php $schedule = $row['schedules']->get($classroom->id); @endphp
                                        <td class="border-l border-gray-200 p-1 dark:border-white/10">
                                            @if ($schedule)
                                                <div
                                                    @class([
                                                        'rounded-lg px-2 py-1 text-center leading-tight',
                                                        'bg-primary-100 ring-2 ring-primary-500 dark:bg-primary-400/20' => $schedule->teacher_id === auth()->id(),
                                                        'bg-gray-100 dark:bg-white/5' => $schedule->teacher_id !== auth()->id(),
                                                    ])
                                                    title="{{ $schedule->subject->name }} — {{ $schedule->teacher->name }}"
                                                >
                                                    <div class="truncate text-xs font-semibold text-gray-800 dark:text-gray-200">
                                                        {{ $schedule->subject->name }}
                                                    </div>
                                                    <div class="text-[0.65rem] text-gray-500 dark:text-gray-400">
                                                        {{ $schedule->teacher->code ?? \Illuminate\Support\Str::limit($schedule->teacher->name, 10) }}
                                                    </div>
                                                </div>
                                            @else
                                                <div class="px-2 py-1 text-center text-xs text-gray-300 dark:text-gray-700">—</div>
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-gray-400 dark:text-gray-500">
                ↔ Geser tabel ke kanan untuk melihat semua rombel.
                @unless (auth()->user()->isAdministrator() || auth()->user()->isPrincipal())
                    Jadwal Anda ditandai hijau.
                @endunless
            </p>
        @endif
    </div>
</x-filament-panels::page>
