<div class="space-y-5">
    <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Pilih tanggal absensi</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Pilih satu tanggal untuk melihat dan memperbarui rekap guru.</p>
            </div>
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ now()->translatedFormat('F Y') }}</span>
        </div>

        <div class="grid grid-cols-7 gap-2 sm:grid-cols-10 lg:grid-cols-[repeat(16,minmax(0,1fr))]">
            @foreach ($this->getAttendanceDays() as $attendanceDay)
                <button
                    type="button"
                    wire:click="selectDate('{{ $attendanceDay['date'] }}')"
                    wire:loading.attr="disabled"
                    @class([
                        'relative min-h-14 rounded-lg border px-1 py-1.5 text-center transition focus:outline-none focus:ring-2 focus:ring-primary-500',
                        'border-primary-600 bg-primary-600 text-white shadow-sm' => $this->selectedDate === $attendanceDay['date'],
                        'border-gray-200 bg-gray-50 text-gray-950 hover:border-primary-300 hover:bg-primary-50 dark:border-white/10 dark:bg-white/5 dark:text-white dark:hover:border-primary-400 dark:hover:bg-primary-500/10' => $this->selectedDate !== $attendanceDay['date'],
                    ])
                >
                    <span @class(['block text-[10px] font-medium uppercase', 'text-primary-100' => $this->selectedDate === $attendanceDay['date'], 'text-gray-500 dark:text-gray-400' => $this->selectedDate !== $attendanceDay['date']])>{{ $attendanceDay['dayName'] }}</span>
                    <span class="block text-sm font-semibold leading-tight">{{ $attendanceDay['day'] }}</span>
                    <span @class(['mt-1 inline-flex min-w-5 justify-center rounded-full px-1 text-[10px] font-semibold', 'bg-white/20 text-white' => $this->selectedDate === $attendanceDay['date'], 'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-200' => $this->selectedDate !== $attendanceDay['date']])>{{ $attendanceDay['total'] }}</span>
                    @if ($attendanceDay['isToday'] && $this->selectedDate !== $attendanceDay['date'])
                        <span class="absolute right-1 top-1 size-1.5 rounded-full bg-primary-500"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </section>

    <section class="rounded-3xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Rekap {{ $this->getSelectedDateLabel() }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Daftar di bawah hanya menampilkan absensi pada tanggal ini.</p>
            </div>
            <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">{{ $this->getSelectedDateSummary()->sum('total') }} tercatat</span>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($this->getSelectedDateSummary() as $summary)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $summary['label'] }}</p>
                    <p @class(['mt-1 text-xl font-semibold', 'text-success-600 dark:text-success-400' => $summary['color'] === 'success', 'text-info-600 dark:text-info-400' => $summary['color'] === 'info', 'text-warning-600 dark:text-warning-400' => $summary['color'] === 'warning', 'text-gray-700 dark:text-gray-200' => $summary['color'] === 'gray'])>{{ $summary['total'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
</div>
