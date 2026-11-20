<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .muted { color: #777; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #6f4e37; color: #fff; text-align: left; padding: 5px; font-size: 9px; }
        td { padding: 4px 5px; border-bottom: 1px solid #eee; }
        td.num { text-align: right; white-space: nowrap; }
        .summary { margin-top: 14px; width: 50%; }
        .summary td { border: none; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>{{ $cafe }} — {{ $report['title'] }}</h1>
    <div class="muted">Periode {{ \Carbon\Carbon::parse($report['period']['from'])->translatedFormat('d M Y') }} s/d {{ \Carbon\Carbon::parse($report['period']['to'])->translatedFormat('d M Y') }} · Dicetak {{ now()->format('d/m/Y H:i') }}</div>
    <table>
        <thead>
            <tr>@foreach ($report['columns'] as $c)<th>{{ $c[1] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                @php($row = (array) $row)
                <tr>
                    @foreach ($report['columns'] as $c)
                        @php($v = $row[$c[0]] ?? '')
                        <td class="{{ $c[2] !== 'text' ? 'num' : '' }}">{{ $c[2] === 'money' ? 'Rp '.number_format((float) $v, 0, ',', '.') : ($c[2] === 'number' ? number_format((float) $v, is_float($v) ? 1 : 0, ',', '.') : $v) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">Tidak ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if (! empty($report['summary']))
        <table class="summary">
            @foreach ($report['summary'] as $s)
                <tr><td>{{ $s[0] }}</td><td class="num"><strong>{{ $s[2] === 'money' ? 'Rp '.number_format((float) $s[1], 0, ',', '.') : $s[1] }}</strong></td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
