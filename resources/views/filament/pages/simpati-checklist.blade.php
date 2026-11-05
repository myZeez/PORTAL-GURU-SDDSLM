<x-filament-panels::page>
    @php
        $classrooms = $this->getClassrooms();
        $classroom = $this->selectedClassroom();
        $completion = $this->getCompletion();
    @endphp

    <div class="flex flex-col gap-4">
        @if (! $classroom)
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Anda belum ditugaskan sebagai pendamping wali kelas.
            </div>
        @else
            <div class="flex flex-wrap items-end gap-3">
                @if ($classrooms->count() > 1)
                    <div>
                        <label for="simpati-classroom" class="mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400">Kelas</label>
                        <select
                            id="simpati-classroom"
                            wire:model.live="classroomId"
                            class="rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                        >
                            @foreach ($classrooms as $option)
                                <option value="{{ $option->id }}">{{ $option->label }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">Kelas Anda</p>
                        <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $classroom->label }}</p>
                    </div>
                @endif

                <div class="ml-auto flex items-center gap-2">
                    <button type="button" wire:click="previousMonth" class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5" aria-label="Bulan sebelumnya">‹</button>
                    <span class="min-w-32 text-center text-sm font-bold text-gray-950 dark:text-white">{{ $this->getMonthStart()->translatedFormat('F Y') }}</span>
                    <button
                        type="button"
                        wire:click="nextMonth"
                        @disabled(! $this->canGoToNextMonth())
                        class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                        aria-label="Bulan berikutnya"
                    >›</button>
                </div>
            </div>

            @if ($this->showsSimpatiLink())
                @php $todayDone = (bool) $this->getTodayCheck()?->is_filled; @endphp
                <div class="flex flex-col gap-3 rounded-2xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-400/20 dark:bg-primary-400/5">
                    <div>
                        <p class="text-sm font-bold text-gray-950 dark:text-white">Isi SIMPATI hari ini</p>
                        <ol class="mt-1 list-decimal space-y-0.5 pl-4 text-xs text-gray-600 dark:text-gray-400">
                            <li>Buka SIMPATI. Halamannya terbuka di tab baru, jadi SiPeka tetap terbuka di sini.</li>
                            <li>Isi SIMPATI untuk kelas Anda.</li>
                            <li>Kembali ke halaman ini, lalu tekan Selesai.</li>
                        </ol>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::button
                            tag="a"
                            :href="$this->getSimpatiUrl()"
                            target="_blank"
                            rel="noopener noreferrer"
                            icon="heroicon-m-arrow-top-right-on-square"
                            color="gray"
                        >
                            Buka SIMPATI
                        </x-filament::button>

                        @if (! $this->isTodayRequired())
                            <span class="text-xs text-gray-500 dark:text-gray-400">Hari ini bukan hari wajib SIMPATI.</span>
                        @elseif ($todayDone)
                            <x-filament::badge color="success" icon="heroicon-m-check">Selesai hari ini</x-filament::badge>
                        @else
                            <x-filament::button wire:click="markTodayDone" wire:loading.attr="disabled" color="success" icon="heroicon-m-check">
                                Selesai
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @endif

            <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div>
                    <p class="text-sm font-bold text-gray-950 dark:text-white">Penyelesaian bulan ini</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $completion['filled'] }} dari {{ $completion['required'] }} hari wajib sudah diisi · libur tidak dihitung
                    </p>
                </div>
                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $completion['percentage'] }}%</p>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Centang setelah Anda memastikan SIMPATI kelas ini sudah diisi. Perubahan langsung terbaca di dasbor Waka Kurikulum.
            </p>

            <div class="flex flex-col gap-2.5">
                @forelse ($this->getRows() as $row)
                    @php
                        $check = $row['check'];
                        $isFilled = (bool) $check?->is_filled;
                    @endphp
                    <button
                        type="button"
                        wire:click="toggle('{{ $row['date']->toDateString() }}')"
                        wire:loading.attr="disabled"
                        role="checkbox"
                        aria-checked="{{ $isFilled ? 'true' : 'false' }}"
                        @class([
                            'flex items-center gap-3 rounded-2xl border p-3 text-left transition',
                            'border-success-300 bg-success-50 dark:border-success-400/30 dark:bg-success-400/10' => $isFilled,
                            'border-gray-200 bg-white hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5' => ! $isFilled,
                        ])
                    >
                        <span @class([
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border-2',
                            'border-success-600 bg-success-600 text-white' => $isFilled,
                            'border-gray-300 text-transparent dark:border-white/20' => ! $isFilled,
                        ])>
                            <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-gray-950 dark:text-white">
                                {{ $row['date']->translatedFormat('l, d F Y') }}
                                @if ($row['date']->isToday())
                                    <span class="ml-1 rounded-full bg-primary-50 px-2 py-0.5 text-[0.65rem] font-bold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">Hari ini</span>
                                @endif
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                @if ($isFilled)
                                    Sudah diisi · {{ $check->checkedBy?->name ?? '—' }} · {{ $check->updated_at->translatedFormat('d M, H.i') }} WIB
                                @elseif ($check)
                                    Dibatalkan oleh {{ $check->checkedBy?->name ?? '—' }} · {{ $check->updated_at->translatedFormat('d M, H.i') }} WIB
                                @else
                                    Belum diisi
                                @endif
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Belum ada hari wajib SIMPATI pada bulan ini.
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</x-filament-panels::page>
