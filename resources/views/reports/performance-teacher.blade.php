@extends('reports.layout', ['title' => 'Laporan Performance Guru', 'period' => $periodLabel])

@php
    $teacher = $result['teacher'];
    $resp = $result['responsibilities'];
    $indicators = \App\Enums\PerformanceIndicator::cases();
    $scoring = $settings->canScore();
    $percent = fn (?float $value): string => $value === null ? 'Tidak berlaku' : rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',').'%';
    $stateLabels = ['done' => 'Terpenuhi', 'partial' => 'Sebagian', 'missing' => 'Belum', 'pending' => 'Belum jatuh tempo'];
@endphp

@section('content')
    <p class="summary">
        <span><b>{{ $teacher->name }}</b></span>
        <span>Kode {{ $teacher->code ?? '—' }}</span>
        <span>{{ $teacher->position ?: 'Guru' }}</span>
        @if ($resp['homeroom'])<span>Wali kelas {{ $resp['homeroom']->label }}</span>@endif
        @if ($resp['assistant'])<span>Pendamping {{ $resp['assistant']->label }}</span>@endif
    </p>
    <p class="summary">
        <span>Periode: {{ $periodType->getLabel() }}, {{ $periodLabel }}</span>
        <span>JP mengajar {{ $result['jp']['teaching'] }} · tugas tambahan {{ $result['jp']['duties'] }} · total {{ $result['jp']['total'] }}</span>
        <span>Supervisi pada periode: {{ $result['supervisions'] }}</span>
    </p>

    @if ($scoring)
        <p class="summary">
            <span>
                @if ($result['score'])
                    <b>Skor gabungan {{ $result['score']['value'] }}{{ $result['label'] ? ' · '.$result['label'] : '' }}</b>, dihitung dari
                    {{ collect($result['score']['breakdown'])->map(fn ($part, $key) => \App\Enums\PerformanceIndicator::from($key)->getLabel().' ('.rtrim(rtrim(number_format($part['weight'], 1, ',', ''), '0'), ',').'%)')->join(', ') }}.
                @else
                    Belum cukup data untuk menghitung skor gabungan.
                @endif
                Skor hanya alat bantu monitoring dan pembinaan.
            </span>
        </p>
    @endif

    <table>
        <thead><tr><th style="width: 18%">Indikator</th><th style="width: 40%">Dasar perhitungan</th><th style="width: 12%">Nilai</th><th>Dasar data</th></tr></thead>
        <tbody>
            @foreach ($indicators as $indicator)
                @php $r = $result['indicators'][$indicator->value]; @endphp
                <tr>
                    <td>{{ $indicator->getLabel() }}</td>
                    <td>{{ $indicator->getBasis() }}@if ($r['value'] === null)<br><i>{{ $indicator->getNotApplicableReason() }}</i>@endif</td>
                    <td>{{ $percent($r['value']) }}</td>
                    <td>{{ $r['counted'] }} data dihitung, {{ $r['full'] }} terpenuhi penuh</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @foreach ($indicators as $indicator)
        @php $items = $result['indicators'][$indicator->value]['items']; @endphp
        @if ($items !== [])
            <h3 style="margin: 12px 0 4px; font-size: 10px">Data pendukung — {{ $indicator->getLabel() }}</h3>
            <table>
                <thead><tr><th style="width: 16%">Tanggal</th><th>Catatan</th><th style="width: 18%">Status</th></tr></thead>
                <tbody>
                    @foreach (array_slice($items, 0, 200) as $item)
                        <tr>
                            <td>{{ $item['date'] ? \Illuminate\Support\Carbon::parse($item['date'])->translatedFormat('d M Y') : '—' }}</td>
                            <td>{{ $item['label'] }}{{ $item['note'] ? ' · '.$item['note'] : '' }}</td>
                            <td>{{ $stateLabels[$item['state']] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h3 style="margin: 14px 0 4px; font-size: 10px">Catatan evaluasi, rekomendasi, dan tindak lanjut</h3>
    @if ($note)
        <p class="meta" style="color: #1f2937">Catatan evaluasi periode ini: {{ $note }}</p>
    @endif
    <table>
        <thead><tr><th>Temuan</th><th>Rekomendasi</th><th style="width: 14%">Penanggung jawab</th><th style="width: 11%">Target</th><th style="width: 12%">Status</th></tr></thead>
        <tbody>
            @forelse ($followUps as $followUp)
                <tr>
                    <td>{{ $followUp->indicator?->getLabel() ? '['.$followUp->indicator->getLabel().'] ' : '' }}{{ $followUp->finding }}</td>
                    <td>{{ $followUp->recommendation }}</td>
                    <td>{{ $followUp->responsible?->name ?? '—' }}</td>
                    <td>{{ $followUp->target_date->translatedFormat('d M Y') }}</td>
                    <td>{{ $followUp->status->getLabel() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada temuan atau tindak lanjut.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3 style="margin: 14px 0 4px; font-size: 10px">Riwayat per periode</h3>
    <table>
        <thead>
            <tr>
                <th style="width: 20%">Periode</th>
                @foreach ($indicators as $indicator)<th>{{ $indicator->getLabel() }}</th>@endforeach
                <th style="width: 7%">Skor</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($history as $snapshot)
                <tr>
                    <td>{{ $snapshot->period_label }}</td>
                    @foreach ($indicators as $indicator)
                        @php $saved = $snapshot->indicators[$indicator->value] ?? null; @endphp
                        <td>{{ ($saved['value'] ?? null) === null ? '—' : $percent((float) $saved['value']) }}</td>
                    @endforeach
                    <td>{{ $snapshot->score ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($indicators) + 2 }}" class="empty">Belum ada periode yang disimpan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="meta" style="margin-top: 12px">
        Dibuat {{ now()->translatedFormat('d F Y, H.i') }} WIB · data periode {{ $periodLabel }}. Hasil didasarkan pada data SiPeka yang tersedia.
    </p>
@endsection
