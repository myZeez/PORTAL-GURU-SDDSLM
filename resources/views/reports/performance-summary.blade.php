@extends('reports.layout', ['title' => 'Laporan Performance Guru', 'period' => $periodLabel])

@php
    $indicators = \App\Enums\PerformanceIndicator::cases();
    $scoring = $settings->canScore();
    $percent = fn (?float $value): string => $value === null ? 'Tidak berlaku' : rtrim(rtrim(number_format($value, 1, ',', ''), '0'), ',').'%';
@endphp

@section('content')
    <p class="meta">
        Target tiap indikator {{ $settings->target_percentage }}%. Indikator yang tidak berlaku bagi seorang guru ditandai "Tidak berlaku", bukan nol.
        @if ($scoring) Skor gabungan adalah alat bantu monitoring dan pembinaan, bukan satu-satunya dasar penilaian. @endif
    </p>

    <table>
        <thead>
            <tr>
                <th style="width: 3%">No.</th>
                <th style="width: 20%">Guru</th>
                @foreach ($indicators as $indicator)
                    <th>{{ $indicator->getLabel() }}</th>
                @endforeach
                <th style="width: 6%">JP</th>
                @if ($scoring)<th style="width: 8%">Skor</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        {{ $row['teacher']->name }}
                        @if ($row['responsibilities']['homeroom'])<br>Wali {{ $row['responsibilities']['homeroom']->code }}@endif
                        @if ($row['responsibilities']['assistant'])<br>Pendamping {{ $row['responsibilities']['assistant']->code }}@endif
                    </td>
                    @foreach ($indicators as $indicator)
                        @php $r = $row['indicators'][$indicator->value]; @endphp
                        <td>{{ $percent($r['value']) }}@if ($r['value'] !== null)<br>{{ $r['full'] }}/{{ $r['counted'] }} data @endif</td>
                    @endforeach
                    <td>{{ $row['jp']['total'] }}</td>
                    @if ($scoring)<td>{{ $row['score']['value'] ?? '—' }}{{ $row['label'] ? ' · '.$row['label'] : '' }}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ count($indicators) + 3 + ($scoring ? 1 : 0) }}" class="empty">Tidak ada guru yang cocok dengan filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="meta" style="margin-top: 12px">
        Dibuat {{ now()->translatedFormat('d F Y, H.i') }} WIB · data periode {{ $periodLabel }}. Hasil didasarkan pada data SiPeka yang tersedia.
    </p>
@endsection
