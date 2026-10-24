<x-filament-panels::page>
    @php
        $attendance = $this->getAttendanceSummary();
        $journal = $this->getJournalSummary();
        $status = $this->getTodayStatus();
    @endphp

    <div class="flex flex-col gap-4">
        <div class="relative min-h-0 overflow-hidden px-4 pt-5 lg:min-h-[26rem]">
            <div class="relative z-20 mx-auto max-w-full px-3 py-2 text-center lg:absolute lg:inset-y-0 lg:left-4 lg:mx-0 lg:flex lg:w-[48%] lg:items-center lg:text-left">
                <div>
                    <p class="text-base font-bold leading-snug text-gray-950 dark:text-white lg:text-3xl">{{ $this->getGreeting() }}, {{ auth()->user()->name }}</p>
                    <div class="mt-1 flex flex-wrap items-center justify-center gap-2 lg:justify-start">
                        <span class="text-xs text-gray-500 dark:text-gray-400 lg:text-base">
                            {{ now()->translatedFormat('l, d F Y · H:i') }} WIB
                        </span>
                        <x-filament::badge :color="$status->getColor()">{{ $status->getLabel() }}</x-filament::badge>
                    </div>
                </div>
            </div>

            <div class="relative z-0 mx-auto mt-3 h-80 w-full max-w-sm lg:absolute lg:inset-y-0 lg:right-12 lg:mt-0 lg:h-auto lg:w-[42%] lg:max-w-none">
                <img
                    src="{{ asset('images/illustrations/teacher-team-dashboard.png') }}"
                    alt="Ilustrasi tim guru"
                    class="pointer-events-none absolute bottom-0 left-1/2 h-full w-auto max-w-none -translate-x-1/2 object-contain object-bottom lg:h-[26rem]"
                />
                <div class="pointer-events-none absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-gray-50 via-gray-50/80 to-transparent dark:from-gray-950 dark:via-gray-950/80"></div>
            </div>
        </div>

        @if ($this->canSeeSimpati())
            @include('filament.pages.partials.dashboard-simpati')
        @endif

        @if ($this->isPimpinanView())
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-success-600 dark:text-success-400">{{ $attendance['hadir'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Hadir</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-3 text-center dark:border-white/10 dark:bg-gray-900">
                    <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ $attendance['pulang_cepat'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pulang Awal</p>
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
                    <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ $attendance['alpa'] }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tanpa Keterangan</p>
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
                @if (\App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource::canAccess())
                    <a href="{{ \App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Absensi Guru</a>
                @endif
                @if (\App\Filament\Resources\Journals\JournalResource::canAccess())
                    <a href="{{ \App\Filament\Resources\Journals\JournalResource::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Jurnal Guru</a>
                @endif
                @if (\App\Filament\Resources\Substitutions\SubstitutionResource::canAccess())
                    <a href="{{ \App\Filament\Resources\Substitutions\SubstitutionResource::getUrl() }}" class="rounded-2xl border border-gray-200 bg-white p-3 text-center text-sm font-bold text-gray-700 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300">Penggantian Guru</a>
                @endif
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

            <div class="grid gap-2.5 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-3.5 dark:border-white/10 dark:bg-gray-900">
                    <div class="mb-2.5 flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">Agenda Mengajar Hari Ini</p>
                        <a href="{{ \App\Filament\Pages\ScheduleMatrix::getUrl() }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Lihat jadwal</a>
                    </div>

                    <div class="flex flex-col gap-2">
                        @forelse ($this->getMyScheduleToday() as $schedule)
                            <div class="flex items-center gap-2.5 rounded-xl bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                                <span class="w-16 shrink-0 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($schedule->timeSlot->starts_at)->format('H:i') }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $schedule->subject->name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $schedule->classroom->label }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="rounded-xl bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Tidak ada jadwal mengajar hari ini.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-3.5 dark:border-white/10 dark:bg-gray-900">
                    <div class="mb-2.5 flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">Tugas Aktif</p>
                        <a href="{{ \App\Filament\Pages\MyTasks::getUrl() }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Lihat semua</a>
                    </div>

                    <div class="flex flex-col gap-2">
                        @forelse ($this->getMyOpenTasks() as $assignee)
                            <div class="rounded-xl bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $assignee->task->title }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Batas: {{ $assignee->task->due_on->translatedFormat('d M Y') }}</p>
                            </div>
                        @empty
                            <p class="rounded-xl bg-gray-50 px-3 py-4 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">Tidak ada tugas aktif.</p>
                        @endforelse
                    </div>
                </div>
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
