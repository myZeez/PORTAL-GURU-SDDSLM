@extends('reports.layout', ['title' => 'Laporan Monitoring Kelas', 'period' => $month->translatedFormat('F Y')])

@section('content')
    <table>
        <thead><tr><th style="width: 5%">No.</th><th style="width: 14%">Tanggal</th><th style="width: 23%">Kelas</th><th style="width: 16%">Status</th><th style="width: 10%">Skor</th><th style="width: 16%">Catatan</th><th style="width: 16%">Rekomendasi</th></tr></thead>
        <tbody>
            @forelse ($schedules as $schedule)
                <tr>
                    <td>{{ $loop->iteration }}</td><td>{{ $schedule->date->translatedFormat('d F Y') }}</td><td>{{ $schedule->classroom->label }}</td>
                    <td>{{ $schedule->result?->status?->getLabel() ?? 'Belum dimulai' }}</td><td>{{ $schedule->result ? $schedule->result->score.'%' : '—' }}</td>
                    <td>{{ $schedule->result?->notes ?: ($schedule->notes ?: '—') }}</td><td>{{ $schedule->result?->recommendation ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Belum ada jadwal monitoring pada bulan ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
