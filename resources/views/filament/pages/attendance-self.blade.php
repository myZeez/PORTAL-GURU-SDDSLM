<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        @if (! $this->isApplicable())
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Kepala Sekolah tidak perlu mengisi absensi. Lihat rekap di menu Absensi Guru.
            </div>
        @else
            <div class="rounded-3xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Pilih tanggal</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ now()->translatedFormat('F Y') }}</p>
                    </div>
                    <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">
                        Hari ini: {{ now()->format('d') }}
                    </span>
                </div>

                <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($this->getAttendanceDays() as $day)
                        @php
                            $date = $day['date'];
                            $attendance = $day['attendance'];
                            $isSelected = $selectedDate === $date->toDateString();
                            $isToday = $date->isToday();
                            $isReopened = $day['reopened'] && ! $attendance;
                        @endphp
                        <button
                            type="button"
                            wire:click="selectDate('{{ $date->toDateString() }}')"
                            @class([
                                'flex min-w-11 shrink-0 flex-col items-center rounded-lg border px-2 py-1.5 text-center transition',
                                'border-primary-600 bg-primary-600 text-white shadow-sm' => $isSelected,
                                'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-500/50 dark:bg-primary-400/10 dark:text-primary-300' => ! $isSelected && $isToday,
                                'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-white/5' => ! $isSelected && ! $isToday,
                            ])
                        >
                            <span class="text-[10px] font-semibold uppercase">{{ $date->translatedFormat('D') }}</span>
                            <span class="text-sm font-semibold">{{ $date->format('d') }}</span>
                            <span @class([
                                'mt-1 h-1.5 w-1.5 rounded-full',
                                'bg-white' => $isSelected && $attendance,
                                'bg-primary-500' => ! $isSelected && $attendance,
                                'bg-warning-500' => $isReopened,
                                'bg-transparent' => ! $attendance && ! $isReopened,
                            ])></span>
                        </button>
                    @endforeach
                </div>

                @if (($reopenings = $this->getOpenReopenings())->isNotEmpty())
                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                        <span class="text-xs font-semibold text-warning-700 dark:text-warning-400">Dibuka kembali untuk Anda:</span>
                        @foreach ($reopenings as $reopening)
                            <button
                                type="button"
                                wire:click="selectDate('{{ $reopening->date->toDateString() }}')"
                                class="rounded-full border border-warning-300 bg-warning-50 px-3 py-1 text-xs font-bold text-warning-800 transition hover:bg-warning-100 dark:border-warning-400/30 dark:bg-warning-400/10 dark:text-warning-300"
                            >
                                {{ $reopening->date->translatedFormat('D, d M') }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($this->isFillingSelectedDate())
                @if (! $this->isFillDateFillable())
                    <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Tanggal ini bukan hari efektif, jadi tidak perlu mengisi absensi.
                    </div>
                @elseif ($this->canCorrectTodayAttendance())
                    <div class="rounded-3xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                        @if ($selectedDate !== now()->toDateString())
                            <div class="mb-3 rounded-xl border border-warning-200 bg-warning-50 px-3 py-2 text-xs font-semibold text-warning-800 dark:border-warning-400/20 dark:bg-warning-400/10 dark:text-warning-300">
                                Absensi {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }} dibuka kembali untuk Anda. Isi sekarang sebelum ditutup.
                            </div>
                        @endif

                        @if ($this->getRecord()?->is_late)
                            <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-warning-50 px-3 py-1 text-xs font-bold text-warning-700 dark:bg-warning-400/10 dark:text-warning-400">
                                Terlambat diisi
                            </div>
                        @endif

                        {{ $this->form }}
                    </div>
                @else
                    <div class="rounded-3xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Absensi hari ini sudah tersimpan</p>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <x-filament::badge :color="$this->getRecord()->status->getColor()">
                                    {{ $this->getRecord()->status->getLabel() }}
                                </x-filament::badge>
                                <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ $this->getRecord()->notes ?: 'Tidak ada keterangan.' }}
                                </p>
                                @if ($this->getRecord()->status === \App\Enums\AttendanceStatus::PulangCepat)
                                    <p class="mt-1 text-xs font-medium text-warning-700 dark:text-warning-400">
                                        Alasan pulang awal: {{ $this->getRecord()->early_leave_reason }}
                                    </p>
                                @endif
                            </div>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Terkunci</span>
                        </div>
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Hubungi Super Admin apabila diperlukan koreksi.</p>
                    </div>
                @endif
            @else
                @php $selectedAttendance = $this->getSelectedAttendance(); @endphp
                <div class="rounded-3xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}
                    </p>

                    @if ($selectedAttendance)
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <div>
                                <x-filament::badge :color="$selectedAttendance->status->getColor()">
                                    {{ $selectedAttendance->status->getLabel() }}
                                </x-filament::badge>
                                <p class="mt-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ $selectedAttendance->notes ?: 'Tidak ada keterangan.' }}
                                </p>
                                @if ($selectedAttendance->status === \App\Enums\AttendanceStatus::PulangCepat)
                                    <p class="mt-1 text-xs font-medium text-warning-700 dark:text-warning-400">
                                        Alasan pulang awal: {{ $selectedAttendance->early_leave_reason }}
                                    </p>
                                @endif
                            </div>
                            @if ($selectedAttendance->is_late)
                                <span class="text-xs font-bold text-warning-600 dark:text-warning-400">Terlambat</span>
                            @endif
                        </div>
                    @else
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada data absensi untuk tanggal ini.</p>
                    @endif
                </div>
            @endif

            <div class="rounded-3xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Rekap {{ now()->translatedFormat('F Y') }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bobot kehadiran: hadir 100%, pulang awal 75%, izin/sakit 50%, tanpa keterangan/cuti 0%.</p>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($this->getAttendanceSummary() as $summary)
                        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $summary['status']->getLabel() }}</p>
                            <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $summary['total'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
