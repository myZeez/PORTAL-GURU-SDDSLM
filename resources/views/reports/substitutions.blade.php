@extends('reports.layout', ['title' => 'Laporan Penggantian Guru', 'period' => $date->translatedFormat('l, d F Y')])

@section('content')
    <table>
        <thead><tr><th style="width: 4%">No.</th><th style="width: 16%">Kelas</th><th style="width: 16%">Mapel</th><th style="width: 12%">Jam</th><th style="width: 18%">Guru Digantikan</th><th style="width: 18%">Guru Pengganti</th><th style="width: 16%">Alasan</th></tr></thead>
        <tbody>
            @forelse ($substitutions as $substitution)
                <tr>
                    <td>{{ $loop->iteration }}</td><td>{{ $substitution->schedule->classroom->label }}</td><td>{{ $substitution->schedule->subject->name }}</td>
                    <td>{{ $substitution->schedule->timeSlot->label }}</td><td>{{ $substitution->schedule->teacher_name }}</td><td>{{ $substitution->substituteTeacher->name }}</td><td>{{ $substitution->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Tidak ada penggantian guru untuk tanggal ini.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
