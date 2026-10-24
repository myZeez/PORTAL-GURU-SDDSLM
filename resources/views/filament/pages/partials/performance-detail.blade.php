@php
    $teacher = $detail['teacher'];
    $resp = $detail['responsibilities'];
    $stateClasses = [
        'done' => 'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400',
        'partial' => 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400',
        'missing' => 'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400',
        'pending' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
    ];
    $stateLabels = ['done' => 'Terpenuhi', 'partial' => 'Sebagian', 'missing' => 'Belum', 'pending' => 'Belum jatuh tempo'];
    $percent = fn (?float $value): string => $value === null ? '—' : rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',').'%';
    $query = [...$this->periodQuery(), 'teacher' => $teacher->id];
@endphp

<div class="flex flex-col gap-4 rounded-3xl border border-primary-200 bg-white p-4 dark:border-primary-400/30 dark:bg-gray-900" wire:key="perf-detail-{{ $teacher->id }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $teacher->name }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ $teacher->code ? 'Kode '.$teacher->code.' · ' : '' }}{{ $teacher->position ?: 'Guru' }} · periode {{ $this->getPeriodLabel() }}
            </p>
            <div class="mt-1 flex flex-wrap gap-1">
                @if ($resp['homeroom'])
                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-[0.7rem] font-bold text-primary-700 dark:bg-primary-400/10 dark:text-primary-300">Wali kelas {{ $resp['homeroom']->label }}</span>
                @endif
                @if ($resp['assistant'])
                    <span class="rounded-full bg-info-50 px-2 py-0.5 text-[0.7rem] font-bold text-info-700 dark:bg-info-400/10 dark:text-info-300">Pendamping {{ $resp['assistant']->label }}</span>
                @endif
                @if ($resp['teaches'])
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[0.7rem] font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">Guru mata pelajaran</span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('reports.performance.pdf', $query) }}" target="_blank" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">Unduh PDF guru ini</a>
            @if ($canReview)
                <button type="button" wire:click="closeDetail" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">Tutup</button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
            <p class="text-xs text-gray-500 dark:text-gray-400">JP mengajar / minggu</p>
            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $detail['jp']['teaching'] }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
            <p class="text-xs text-gray-500 dark:text-gray-400">JP tugas tambahan</p>
            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $detail['jp']['duties'] }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
            <p class="text-xs text-gray-500 dark:text-gray-400">Total JP</p>
            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $detail['jp']['total'] }}</p>
        </div>
        <div class="rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/5">
            <p class="text-xs text-gray-500 dark:text-gray-400">Supervisi pada periode</p>
            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $detail['supervisions'] }}</p>
        </div>
    </div>

    @if ($scoring)
        <div class="rounded-2xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-400/20 dark:bg-primary-400/10">
            @if ($detail['score'])
                <p class="text-sm font-bold text-primary-800 dark:text-primary-200">
                    Skor gabungan {{ $detail['score']['value'] }}{{ $detail['label'] ? ' · '.$detail['label'] : '' }}
                </p>
                <p class="mt-1 text-xs text-primary-800 dark:text-primary-200">
                    Dihitung dari {{ count($detail['score']['breakdown']) }} indikator yang berlaku bagi guru ini:
                    @foreach ($detail['score']['breakdown'] as $key => $part)
                        {{ \App\Enums\PerformanceIndicator::from($key)->getLabel() }} ({{ rtrim(rtrim(number_format($part['weight'], 1, ',', ''), '0'), ',') }}% dari skor){{ ! $loop->last ? ', ' : '.' }}
                    @endforeach
                    Indikator yang tidak berlaku tidak dihitung, bobotnya dibagi ke indikator lain.
                </p>
            @else
                <p class="text-sm font-semibold text-primary-800 dark:text-primary-200">Belum cukup data untuk menghitung skor gabungan pada periode ini.</p>
            @endif
            <p class="mt-1 text-xs text-primary-800/80 dark:text-primary-200/80">Skor adalah alat bantu monitoring dan pembinaan, bukan satu-satunya dasar penilaian kinerja guru.</p>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-white/10">
        <table class="w-full min-w-[640px] border-collapse text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-white/5">
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Indikator</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Dasar perhitungan</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Nilai</th>
                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Dasar data</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($indicators as $indicator)
                    @php $r = $detail['indicators'][$indicator->value]; @endphp
                    <tr class="border-t border-gray-100 align-top dark:border-white/5">
                        <td class="px-3 py-2 font-semibold text-gray-950 dark:text-white">{{ $indicator->getLabel() }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                            {{ $indicator->getBasis() }}
                            @if ($r['value'] === null)
                                <span class="mt-1 block font-semibold text-gray-500 dark:text-gray-400">{{ $indicator->getNotApplicableReason() }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center font-bold {{ $this->toneFor($r['value']) }}">{{ $r['value'] === null ? 'Tidak berlaku' : $percent($r['value']) }}</td>
                        <td class="px-3 py-2 text-center text-xs text-gray-600 dark:text-gray-400">{{ $r['counted'] }} data dihitung, {{ $r['full'] }} terpenuhi penuh</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex flex-col gap-2">
        <p class="text-sm font-bold text-gray-950 dark:text-white">Data pendukung setiap angka</p>
        @foreach ($indicators as $indicator)
            @php $r = $detail['indicators'][$indicator->value]; @endphp
            <details class="rounded-2xl border border-gray-200 px-3 py-2 dark:border-white/10">
                <summary class="cursor-pointer text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $indicator->getLabel() }}
                    <span class="font-normal text-gray-500 dark:text-gray-400">· {{ count($r['items']) }} catatan</span>
                </summary>
                <div class="mt-2 flex flex-col gap-1.5">
                    @forelse (array_slice($r['items'], 0, 60) as $item)
                        <div class="flex flex-wrap items-center gap-2 rounded-xl bg-gray-50 px-2.5 py-1.5 text-xs dark:bg-white/5">
                            <span class="w-20 shrink-0 font-semibold text-gray-500 dark:text-gray-400">{{ $item['date'] ? \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d M Y') : '—' }}</span>
                            <span class="min-w-0 flex-1 text-gray-800 dark:text-gray-200">{{ $item['label'] }}@if ($item['note']) <span class="text-gray-500 dark:text-gray-400">· {{ $item['note'] }}</span>@endif</span>
                            <span class="rounded-full px-2 py-0.5 text-[0.65rem] font-bold {{ $stateClasses[$item['state']] }}">{{ $stateLabels[$item['state']] }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $indicator->getNotApplicableReason() }}</p>
                    @endforelse
                    @if (count($r['items']) > 60)
                        <p class="text-xs text-gray-500 dark:text-gray-400">…dan {{ count($r['items']) - 60 }} catatan lain (lihat PDF untuk semuanya).</p>
                    @endif
                </div>
            </details>
        @endforeach
    </div>

    <div class="flex flex-col gap-2">
        <p class="text-sm font-bold text-gray-950 dark:text-white">Temuan, rekomendasi, dan tindak lanjut</p>
        @forelse ($this->getFollowUps() as $followUp)
            <div class="rounded-2xl border border-gray-200 p-3 text-sm dark:border-white/10" wire:key="followup-{{ $followUp->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-gray-950 dark:text-white">
                        {{ $followUp->indicator?->getLabel() ?? 'Umum' }}
                    </span>
                    <x-filament::badge :color="$followUp->status->getColor()">{{ $followUp->status->getLabel() }}</x-filament::badge>
                </div>
                <p class="mt-1 text-gray-800 dark:text-gray-200"><b>Temuan:</b> {{ $followUp->finding }}</p>
                <p class="text-gray-800 dark:text-gray-200"><b>Rekomendasi:</b> {{ $followUp->recommendation }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Penanggung jawab {{ $followUp->responsible?->name ?? '—' }} · target {{ $followUp->target_date->translatedFormat('d M Y') }}
                    @if ($followUp->completed_at) · selesai {{ $followUp->completed_at->translatedFormat('d M Y') }} @endif
                </p>
                @if ($canManage)
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach (\App\Enums\PerformanceFollowUpStatus::cases() as $status)
                            @if ($status !== $followUp->status)
                                <button type="button" wire:click="setFollowUpStatus({{ $followUp->id }}, '{{ $status->value }}')" class="rounded-full border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                                    Tandai: {{ $status->getLabel() }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                Belum ada temuan atau tindak lanjut untuk guru ini.
            </p>
        @endforelse
    </div>

    @if ($canManage)
        <div class="flex flex-col gap-2 rounded-2xl border border-gray-200 p-3 dark:border-white/10">
            <label for="perf-note" class="text-sm font-bold text-gray-950 dark:text-white">Catatan evaluasi periode ini</label>
            <textarea id="perf-note" wire:model="evaluationNote" rows="3" maxlength="1000" class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"></textarea>
            @error('evaluationNote') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
            <div>
                <button type="button" wire:click="saveTeacherSnapshot" class="rounded-full bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-500">Simpan catatan &amp; riwayat guru ini</button>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Menyimpan menyalin angka periode ini ke riwayat bersama catatan di atas.</p>
        </div>
    @endif

    <div class="flex flex-col gap-2">
        <p class="text-sm font-bold text-gray-950 dark:text-white">Riwayat per periode</p>
        <div class="overflow-x-auto rounded-2xl border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[720px] border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400">Periode</th>
                        @foreach ($indicators as $indicator)
                            <th class="px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $indicator->getLabel() }}</th>
                        @endforeach
                        <th class="px-2 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400">Skor</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->getHistory() as $snapshot)
                        <tr class="border-t border-gray-100 align-top dark:border-white/5" wire:key="snapshot-{{ $snapshot->id }}">
                            <td class="px-3 py-2 text-xs">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $snapshot->period_label }}</span>
                                <span class="block text-gray-500 dark:text-gray-400">{{ $snapshot->period_type->getLabel() }} · disimpan {{ $snapshot->savedBy?->name ?? '—' }}</span>
                                @if ($snapshot->notes)
                                    <span class="mt-0.5 block text-gray-700 dark:text-gray-300">Catatan: {{ $snapshot->notes }}</span>
                                @endif
                            </td>
                            @foreach ($indicators as $indicator)
                                @php $saved = $snapshot->indicators[$indicator->value] ?? null; @endphp
                                <td class="px-2 py-2 text-center text-xs font-semibold {{ $this->toneFor($saved['value'] ?? null) }}">{{ ($saved['value'] ?? null) === null ? '—' : $percent((float) $saved['value']) }}</td>
                            @endforeach
                            <td class="px-2 py-2 text-center text-xs font-bold text-gray-950 dark:text-white">{{ $snapshot->score ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($indicators) + 2 }}" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada periode yang disimpan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">Hasil ini didasarkan pada data SiPeka yang tersedia. Bila ada data sumber yang keliru, koreksi dulu di modul terkait (absensi, jurnal, tugas, SIMPATI) sebelum hasilnya dipakai sebagai bahan pembinaan.</p>
</div>
