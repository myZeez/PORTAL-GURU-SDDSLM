<x-filament-panels::page>
    @php
        $attendance = $this->getAttendanceSummary();
        $journal = $this->getJournalSummary();
        $status = $this->getTodayStatus();
    @endphp

    <div class="flex flex-col gap-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $this->getGreeting() }}, {{ auth()->user()->name }}</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ now()->translatedFormat('l, d F Y · H:i') }} WIB
                </span>
                <x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge>
            </div>
        </div>

        @if ($this->isPimpinanView())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $attendance['hadir'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Hadir</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-info-600 dark:text-info-400">{{ $attendance['izin'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Izin</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $attendance['sakit'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Sakit</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-gray-500 dark:text-gray-400">{{ $attendance['cuti'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cuti</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $attendance['belum_mengisi'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Belum Absen</p>
                </div>
            </div>

            <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div>
                    <p class="text-sm font-bold text-gray-950 dark:text-white">Jurnal Hari Ini</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Slot mengajar yang sudah diisi jurnalnya</p>
                </div>
                <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $journal['filled'] }}/{{ $journal['expected'] }}</p>
            </div>

            <div class="flex flex-col gap-2.5">
                <p class="text-sm font-bold text-gray-950 dark:text-white">Guru Digantikan Hari Ini</p>

                @forelse ($this->getTodaysSubstitutions() as $substitution)
                    <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4.5 w-4.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-950 dark:text-white">
                                {{ $substitution->schedule->classroom->label }} — {{ $substitution->schedule->subject->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $substitution->schedule->teacher->name }} → {{ $substitution->substituteTeacher->name }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Tidak ada penggantian hari ini.
                    </div>
                @endforelse
            </div>

            <div class="flex flex-col gap-2.5">
                <p class="text-sm font-bold text-gray-950 dark:text-white">Analisis Beban Kerja</p>

                <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Guru</th>
                                <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Total JP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->getWorkloadAnalysis() as $row)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                    <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['teacher'] }}</td>
                                    <td class="px-3 py-2 text-right font-bold text-gray-950 dark:text-white">{{ $row['jp'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data beban kerja.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <a href="{{ \App\Filament\Pages\ScheduleMatrix::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Matriks Jadwal</a>
                <a href="{{ \App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Absensi Guru</a>
                <a href="{{ \App\Filament\Resources\Journals\JournalResource::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Jurnal Guru</a>
                <a href="{{ \App\Filament\Resources\Substitutions\SubstitutionResource::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Penggantian Guru</a>
            </div>
        @else
            @if ($this->isPastDailyReminderTime() && (! $this->hasFilledAttendanceToday() || $this->hasUnfilledJournalToday()))
                <div class="rounded-2xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-400/20 dark:bg-warning-400/10">
                    <p class="text-sm font-bold text-warning-700 dark:text-warning-400">Pengingat Kelengkapan Harian</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-warning-700 dark:text-warning-400">
                        @if (! $this->hasFilledAttendanceToday())
                            <li>Absensi hari ini belum diisi.</li>
                        @endif
                        @if ($this->hasUnfilledJournalToday())
                            <li>Ada jurnal mengajar hari ini yang belum diisi.</li>
                        @endif
                    </ul>
                </div>
            @endif

            <div class="flex flex-col gap-2.5">
                <p class="text-sm font-bold text-gray-950 dark:text-white">Penggantian Hari Ini</p>

                @forelse ($this->getMySubstitutionsToday() as $substitution)
                    @php $iAmSubstitute = $substitution->substitute_teacher_id === auth()->id(); @endphp
                    <div class="flex items-center gap-3 rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-400/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4.5 w-4.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-950 dark:text-white">
                                {{ $substitution->schedule->classroom->label }} — {{ $substitution->schedule->subject->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $iAmSubstitute ? 'Anda menggantikan '.$substitution->schedule->teacher->name : 'Digantikan oleh '.$substitution->substituteTeacher->name }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        Tidak ada penggantian untuk Anda hari ini.
                    </div>
                @endforelse
            </div>

            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                <a href="{{ \App\Filament\Pages\ScheduleMatrix::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Matriks Jadwal</a>
                <a href="{{ \App\Filament\Pages\AttendanceSelf::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Absensi Saya</a>
                <a href="{{ \App\Filament\Pages\JournalSelf::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Jurnal Saya</a>
                <a href="{{ \App\Filament\Pages\MyTasks::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Tugas Saya</a>
            </div>
        @endif
    </div>
</x-filament-panels::page>
