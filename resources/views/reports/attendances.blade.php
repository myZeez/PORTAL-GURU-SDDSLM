@extends('reports.layout', ['title' => 'Rekap Absensi Kehadiran Guru dan Tenaga Kependidikan', 'period' => $month->translatedFormat('F Y')])

@section('content')
    <p class="meta">H = Hadir · PA = Pulang Awal · I = Izin · S = Sakit · C = Cuti · TK = Tanpa Keterangan · — = Belum diisi · Libur = hari tidak dihitung</p>

    <table class="attendance-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 2.5%">No.</th>
                <th rowspan="2" style="width: 12%">Nama</th>
                <th rowspan="2" style="width: 8%">Jabatan</th>
                <th colspan="{{ $days->count() }}">Tanggal</th>
                <th colspan="7">Jumlah</th>
            </tr>
            <tr>
                @foreach ($days as $date)
                    <th @class(['non-effective' => $nonEffectiveDateStrings->contains($date->toDateString())])>{{ $date->format('j') }}</th>
                @endforeach
                <th>H</th><th>PA</th><th>I</th><th>S</th><th>C</th><th>TK</th><th>%</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $row['teacher']->name }}</td>
                    <td>{{ $row['teacher']->position ?: '—' }}</td>
                    @foreach ($days as $date)
                        @php
                            $status = $row['dailyStatuses']->get($date->toDateString());
                            $isNonEffective = $nonEffectiveDateStrings->contains($date->toDateString());
                            $code = match ($status?->value) {
                                'hadir' => 'H', 'pulang_cepat' => 'PA', 'izin' => 'I', 'sakit' => 'S', 'cuti' => 'C', 'alpa' => 'TK', default => $isNonEffective ? 'Libur' : '—',
                            };
                        @endphp
                        <td @class(['non-effective' => $isNonEffective])>{{ $code }}</td>
                    @endforeach
                    <td>{{ $row['summary']['hadir'] }}</td><td>{{ $row['summary']['pulang_cepat'] }}</td><td>{{ $row['summary']['izin'] }}</td><td>{{ $row['summary']['sakit'] }}</td><td>{{ $row['summary']['cuti'] }}</td><td>{{ $row['summary']['alpa'] }}</td><td>{{ $row['attendance_percentage'] }}%</td>
                </tr>
            @empty
                <tr><td colspan="{{ $days->count() + 10 }}" class="empty">Belum ada guru aktif untuk direkap.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
