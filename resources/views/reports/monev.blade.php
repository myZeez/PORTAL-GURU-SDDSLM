@extends('reports.layout', ['title' => 'Laporan Monitoring & Evaluasi Guru', 'period' => $month->translatedFormat('F Y')])

@section('content')
    <table>
        <thead><tr><th style="width: 5%">No.</th><th style="width: 30%">Guru</th><th style="width: 16%">Kehadiran</th><th style="width: 17%">Penilaian Bulanan</th><th style="width: 15%">Supervisi</th><th style="width: 17%">Status</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td><td>{{ $row['teacher']->name }}</td><td>{{ $row['attendance_percentage'] }}%</td>
                    <td>{{ $row['has_assessment'] ? 'Sudah ada' : 'Belum ada' }}</td><td>{{ $row['supervision_count'] }} kali</td>
                    <td>{{ $row['status'] }}{{ $row['journal_missing_today'] ? ' · Jurnal hari ini kosong' : '' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">Tidak ada guru aktif untuk dievaluasi.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
