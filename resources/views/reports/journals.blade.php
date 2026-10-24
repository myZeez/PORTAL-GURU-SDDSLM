@extends('reports.layout', ['title' => 'Rekap Jurnal Guru', 'period' => $date->translatedFormat('l, d F Y')])

@section('content')
    <table>
        <thead><tr><th style="width: 4%">No.</th><th style="width: 14%">Kelas</th><th style="width: 14%">Mapel</th><th style="width: 12%">Jam</th><th style="width: 16%">Guru</th><th style="width: 20%">Materi</th><th style="width: 20%">Kehadiran Siswa</th></tr></thead>
        <tbody>
            @forelse ($journals as $journal)
                <tr>
                    <td>{{ $loop->iteration }}</td><td>{{ $journal->schedule->classroom->label }}</td><td>{{ $journal->schedule->subject->name }}</td>
                    <td>{{ $journal->schedule->timeSlot->label }}</td><td>{{ $journal->teacher->name }}</td><td>{{ $journal->topic }}</td>
                    <td>H: {{ $journal->students_present }} · I: {{ $journal->students_permitted }} · S: {{ $journal->students_sick }} · TK: {{ $journal->students_absent }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Belum ada jurnal untuk tanggal ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
