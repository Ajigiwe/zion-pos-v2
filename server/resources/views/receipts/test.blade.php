{{-- Printer test receipt — prints itself. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print test — {{ $store['name'] }}</title>
    <link rel="stylesheet" href="/css/app.css">
    <style>
        @page { margin: 0; size: auto; }
        body { background: #fff; margin: 0; padding: 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        .print-hint { text-align: center; color: #64748b; font-size: 13px; margin-bottom: 8px; }
        .print-hint button { padding: 6px 18px; }
        @media print {
            body { margin: 0 !important; padding: 0 !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="print-hint no-print">
        <button onclick="window.print()">Print test receipt</button>
        <div class="text-muted">Sends an {{ $store['paper_width'] }}mm sample to verify cutter, font clarity and communication.</div>
        <div class="text-muted" style="max-width:520px;margin:6px auto 0;">
            Seeing a print dialog? That is Chrome, not the store — for silent output set the receipt printer as the Windows
            <b>default printer</b> and start Chrome with <code>--kiosk-printing</code>; this page will then print itself straight out.
        </div>
    </div>
    <div id="receipt-print">
        @include('receipts._thermal', ['r' => $r, 'store' => $store])
    </div>
    <script>
        // directPrinting parity: auto-print when the toggle is on
        var params = new URLSearchParams(location.search);
        if (params.get('direct') === '0') { /* show the button only */ }
        else { setTimeout(function () { window.print(); }, 150); }
    </script>
</body>
</html>
