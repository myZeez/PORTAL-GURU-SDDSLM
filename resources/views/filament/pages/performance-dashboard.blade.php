<x-filament-panels::page>
    @php
        $rows = $this->getRows();
        $settings = $this->getSettings();
        $target = $settings->target_percentage;
        $summary = $this->summarize($rows);
        $detail = $this->getDetail();
        $comparison = $this->getComparison();
        $canReview = $this->canReviewAll();
        $canManage = $this->canManage();
        $indicators = \App\Enums\PerformanceIndicator::cases();
        $scoring = $settings->canScore();
        $input = 'w-full rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white';
        $label = 'mb-1 block text-xs font-semibold text-gray-500 dark:text-gray-400';
    @endphp

    <div class="flex flex-col gap-4">
        <div class="rounded-2xl border border-info-200 bg-info-50 px-4 py-3 text-sm text-info-900 dark:border-info-400/20 dark:bg-info-400/10 dark:text-info-200">
            Angka di sini dihitung hanya dari data yang tersimpan di SiPeka. Indikator yang tidak berlaku bagi seorang guru ditandai
            <b>Tidak berlaku</b>, bukan nol, dan data yang belum jatuh tempo tidak dihitung.
            @if ($scoring)
                Skor gabungan adalah alat bantu monitoring dan pembinaan, bukan satu-satunya dasar penilaian kinerja guru.
            @else
                Skor gabungan belum diaktifkan, sekolah belum menetapkan bobot indikator.
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div>
                <label for="perf-period" class="{{ $label }}">Periode evaluasi</label>
                <select id="perf-period" wire:model.live="periodType" class="{{ $input }}">
                    @foreach (\App\Enums\PerformancePeriod::cases() as $period)
                        <option value="{{ $period->value }}">{{ $period->getLabel() }}</option>
                    @endforeach
                </select>
            </div>

            @switch($this->getPeriodTypeEnum())
                @case(\App\Enums\PerformancePeriod::Week)
                    <div>
                        <label for="perf-anchor" class="{{ $label }}">Pekan yang memuat tanggal</label>
                        <input id="perf-anchor" type="date" wire:model.live="anchor" class="{{ $input }}">
                    </div>
                    @break
                @case(\App\Enums\PerformancePeriod::Month)
                    <div>
                        <label for="perf-month" class="{{ $label }}">Bulan</label>
                        <input id="perf-month" type="month" wire:model.live="month" class="{{ $input }}">
                    </div>
                    @break
                @case(\App\Enums\PerformancePeriod::Semester)
                    <div>
                        <label for="perf-semester" class="{{ $label }}">Semester</label>
                        <select id="perf-semester" wire:model.live="semesterId" class="{{ $input }}">
                            @foreach ($this->getSemesters() as $semester)
                                <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @break
                @default
                    <div>
                        <label for="perf-from" class="{{ $label }}">Dari tanggal</label>
                        <input id="perf-from" type="date" wire:model.live="from" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="perf-to" class="{{ $label }}">Sampai tanggal</label>
                        <input id="perf-to" type="date" wire:model.live="to" class="{{ $input }}">
                    </div>
            @endswitch

            <div class="col-span-2 self-end text-sm font-semibold text-gray-700 dark:text-gray-300 lg:col-span-1">
                {{ $this->getPeriodLabel() }}
            </div>
        </div>

        @if ($canReview)
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                <div>
                    <label for="perf-teacher" class="{{ $label }}">Guru</label>
                    <select id="perf-teacher" wire:model.live="teacherId" class="{{ $input }}">
                        <option value="">Semua guru</option>
                        @foreach ($this->getTeacherOptions() as $teacher)
                            <option value="{{ $teacher->id }}">{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="perf-classroom" class="{{ $label }}">Kelas / rombel</label>
                    <select id="perf-classroom" wire:model.live="classroomId" class="{{ $input }}">
                        <option value="">Semua kelas</option>
                        @foreach ($this->getClassrooms() as $classroom)
                            <option value="{{ $classroom->id }}">{{ $classroom->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="perf-subject" class="{{ $label }}">Mata pelajaran</label>
                    <select id="perf-subject" wire:model.live="subjectId" class="{{ $input }}">
                        <option value="">Semua mapel</option>
                        @foreach ($this->getSubjects() as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="perf-responsibility" class="{{ $label }}">Jenis tanggung jawab</label>
                    <select id="perf-responsibility" wire:model.live="responsibility" class="{{ $input }}">
                        <option value="">Semua</option>
                        <option value="homeroom">Wali kelas</option>
                        <option value="assistant">Pendamping wali kelas</option>
                        <option value="subject">Guru mata pelajaran</option>
                    </select>
                </div>
                <div>
                    <label for="perf-indicator" class="{{ $label }}">Indikator</label>
                    <select id="perf-indicator" wire:model.live="indicatorFilter" class="{{ $input }}">
                        <option value="">Semua indikator</option>
                        @foreach ($indicators as $indicator)
                            <option value="{{ $indicator->value }}">{{ $indicator->getLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="perf-status" class="{{ $label }}">Status penyelesaian</label>
                    <select id="perf-status" wire:model.live="statusFilter" class="{{ $input }}">
                        <option value="">Semua status</option>
                        <option value="below">Belum mencapai target ({{ $target }}%)</option>
                        <option value="met">Sudah mencapai target</option>
                        <option value="unavailable">Tidak berlaku / belum ada data</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label for="perf-sort" class="text-xs font-semibold text-gray-500 dark:text-gray-400">Urutkan</label>
                <select id="perf-sort" wire:model.live="sort" class="rounded-xl border border-gray-300 bg-white px-3 py-1.5 text-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                    <option value="name">Nama</option>
                    @if ($scoring)
                        <option value="score">Skor tertinggi</option>
                    @endif
                    @foreach ($indicators as $indicator)
                        <option value="{{ $indicator->value }}">{{ $indicator->getLabel() }} (terendah dulu)</option>
                    @endforeach
                </select>
                <button type="button" wire:click="resetFilters" class="rounded-full border border-gray-200 px-4 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                    Atur Ulang Filter
                </button>
                <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">Target tiap indikator: {{ $target }}%</span>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                @foreach ($indicators as $indicator)
                    @php $s = $summary[$indicator->value]; @endphp
                    <div class="rounded-2xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $indicator->getLabel() }}</p>
                        <p class="mt-1 text-2xl font-bold {{ $this->toneFor($s['average']) }}">
                            {{ $s['average'] === null ? '—' : rtrim(rtrim(number_format($s['average'], 1, ',', ''), '0'), ',').'%' }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            rata-rata dari {{ $s['applicable'] }} guru · {{ $s['belowTarget'] }} di bawah target
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <table class="w-full min-w-[960px] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Guru</th>
                            @foreach ($indicators as $indicator)
                                <th class="px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400" title="{{ $indicator->getBasis() }}">{{ $indicator->getLabel() }}</th>
                            @endforeach
                            <th class="px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">JP/minggu</th>
                            @if ($scoring)
                                <th class="px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Skor</th>
                            @endif
                            <th class="px-2 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $teacher = $row['teacher'];
                                $resp = $row['responsibilities'];
                                $isCompared = in_array($teacher->id, $this->compare, true);
                            @endphp
                            <tr wire:key="perf-row-{{ $teacher->id }}" class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $teacher->name }}</p>
                                    <div class="mt-0.5 flex flex-wrap gap-1">
                                        @if ($resp['homeroom'])
                                            <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[0.65rem] font-bold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">Wali {{ $resp['homeroom']->code }}</span>
                                        @endif
                                        @if ($resp['assistant'])
                                            <span class="rounded-full bg-info-50 px-2 py-0.5 text-[0.65rem] font-bold text-info-700 dark:bg-info-400/10 dark:text-info-300">Pendamping {{ $resp['assistant']->code }}</span>
                                        @endif
                                        @if ($teacher->role)
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[0.65rem] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $teacher->role->getLabel() }}</span>
                                        @endif
                                    </div>
                                </td>
                                @foreach ($indicators as $indicator)
                                    @include('filament.pages.partials.performance-cell', ['result' => $row['indicators'][$indicator->value], 'indicator' => $indicator])
                                @endforeach
                                <td class="px-2 py-2 text-center text-gray-700 dark:text-gray-300" title="Mengajar {{ $row['jp']['teaching'] }} JP + tugas tambahan {{ $row['jp']['duties'] }} JP">{{ $row['jp']['total'] }}</td>
                                @if ($scoring)
                                    <td class="px-2 py-2 text-center">
                                        @if ($row['score'])
                                            <span class="font-bold {{ $this->toneFor((float) $row['score']['value']) }}">{{ $row['score']['value'] }}</span>
                                            @if ($row['label'])
                                                <span class="block text-[0.65rem] text-gray-500 dark:text-gray-400">{{ $row['label'] }}</span>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-400" title="Belum cukup data untuk dihitung">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-2 py-2 text-right whitespace-nowrap">
                                    <button type="button" wire:click="toggleCompare({{ $teacher->id }})" @class([
                                        'rounded-full border px-2.5 py-1 text-xs font-semibold transition',
                                        'border-primary-600 bg-primary-600 text-white' => $isCompared,
                                        'border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5' => ! $isCompared,
                                    ])>{{ $isCompared ? 'Dibandingkan' : 'Bandingkan' }}</button>
                                    <button type="button" wire:click="openDetail({{ $teacher->id }})" class="ml-1 rounded-full bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-primary-500">Detail</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($indicators) + 3 + ($scoring ? 1 : 0) }}" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Tidak ada guru yang cocok dengan filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (count($comparison) >= 2)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">Perbandingan guru (periode yang sama)</p>
                        <button type="button" wire:click="clearCompare" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Kosongkan</button>
                    </div>
                    <p class="mt-1 text-xs text-warning-700 dark:text-warning-400">
                        Bandingkan dengan hati-hati: guru berbeda memegang beban dan tanggung jawab berbeda. Lihat jumlah data dan tanggung jawab di bawah nama sebelum menyimpulkan.
                    </p>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full min-w-[520px] border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-white/10">
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Indikator</th>
                                    @foreach ($comparison as $item)
                                        <th class="px-2 py-2 text-center text-xs font-semibold text-gray-950 dark:text-white">{{ $item['teacher']->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($indicators as $indicator)
                                    <tr class="border-b border-gray-100 dark:border-white/5">
                                        <td class="px-2 py-2 text-gray-700 dark:text-gray-300">{{ $indicator->getLabel() }}</td>
                                        @foreach ($comparison as $item)
                                            @php $r = $item['indicators'][$indicator->value]; @endphp
                                            <td class="px-2 py-2 text-center">
                                                @if ($r['value'] === null)
                                                    <span class="text-xs text-gray-400">Tidak berlaku</span>
                                                @else
                                                    <span class="font-bold {{ $this->toneFor($r['value']) }}">{{ rtrim(rtrim(number_format($r['value'], 1, ',', ''), '0'), ',') }}%</span>
                                                    <span class="block text-[0.65rem] text-gray-500 dark:text-gray-400">{{ $r['full'] }}/{{ $r['counted'] }} data</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-300">JP per minggu</td>
                                    @foreach ($comparison as $item)
                                        <td class="px-2 py-2 text-center text-gray-700 dark:text-gray-300">{{ $item['jp']['total'] }}</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td class="px-2 py-2 text-gray-700 dark:text-gray-300">Tanggung jawab</td>
                                    @foreach ($comparison as $item)
                                        <td class="px-2 py-2 text-center text-xs text-gray-600 dark:text-gray-400">
                                            {{ collect([$item['responsibilities']['homeroom'] ? 'Wali '.$item['responsibilities']['homeroom']->code : null, $item['responsibilities']['assistant'] ? 'Pendamping '.$item['responsibilities']['assistant']->code : null, $item['responsibilities']['teaches'] ? 'Guru mapel' : null])->filter()->join(' · ') ?: '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        @if ($detail)
            @include('filament.pages.partials.performance-detail', ['detail' => $detail])
        @endif
    </div>
</x-filament-panels::page>
