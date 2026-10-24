<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 22px 26px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #111827; font-size: 17px; margin: 0 0 4px; }
        .meta { color: #6b7280; margin: 0 0 16px; }
        .summary { margin: 0 0 14px; }
        .summary span { background: #f3f4f6; border-radius: 5px; display: inline-block; margin-right: 6px; padding: 6px 9px; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        th { background: #ede9fe; color: #4c1d95; font-size: 8px; text-align: left; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; word-wrap: break-word; }
        tr:nth-child(even) { background: #f9fafb; }
        .attendance-table { font-size: 6px; }
        .attendance-table th, .attendance-table td { padding: 3px 1px; text-align: center; }
        .attendance-table th:nth-child(2), .attendance-table td:nth-child(2), .attendance-table th:nth-child(3), .attendance-table td:nth-child(3) { padding: 3px; text-align: left; }
        .non-effective { background: #f3f4f6; color: #6b7280; }
        .empty { color: #6b7280; padding: 18px; text-align: center; }
        .footer { bottom: 0; color: #6b7280; font-size: 8px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">SD Islam Darussalam · {{ $period }}@isset($semester) · Semester {{ $semester->name }}@endisset · Dicetak {{ now()->translatedFormat('d F Y H:i') }} WIB</p>

    @yield('content')

    <div class="footer">Portal Guru · Dokumen internal sekolah</div>
</body>
</html>
