{{-- Standalone PDF wrapper (dompdf has no auth/session — cannot use layouts.app). --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $r['number'] }}</title>
    <style>
        @page { margin: {{ $store['side_margin'] ?? 6 }}mm; }
        body { font-family: 'DejaVu Sans Mono', monospace; font-size: 11px; color: #000; background: #fff; }
    </style>
</head>
<body>
    @include('receipts._thermal', ['r' => $r, 'store' => $store])
</body>
</html>
