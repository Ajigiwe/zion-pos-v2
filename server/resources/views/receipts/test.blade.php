<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print test — {{ $store['name'] }}</title>
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style id="page-style">
        @page { size: 72mm 200mm; margin: 0mm !important; }
        body { background: #f8fafc; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        .test-topbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .test-content-wrap {
            max-width: 520px;
            margin: 20px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .print-hint {
            text-align: center;
            color: #64748b;
            font-size: 13px;
            padding: 14px;
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
        }
        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                color: #000 !important;
                width: 100% !important;
                height: auto !important;
            }
            .test-topbar, .print-hint, .no-print { display: none !important; }
            .test-content-wrap { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; max-width: none !important; }
            #receipt-print { padding: 0 !important; margin: 0 !important; }
        }
    </style>
</head>
<body>
    <div class="test-topbar no-print">
        <div class="d-flex align-items-center gap-2">
            <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ route('settings.index') }}'" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back
            </button>
            <a href="{{ route('settings.index') }}" class="btn btn-sm btn-outline-secondary">Settings</a>
            <a href="{{ route('pos') }}" class="btn btn-sm btn-outline-primary">POS Register</a>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button onclick="fitPageToReceipt(); setTimeout(function(){ window.print(); }, 100);" class="btn btn-sm btn-accent d-inline-flex align-items-center gap-1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 13h10v8H7z"/></svg>
                Print Test Receipt
            </button>
            <button onclick="window.close()" class="btn btn-sm btn-outline-dark">Close Window</button>
        </div>
    </div>

    <div class="test-content-wrap">
        <div class="print-hint no-print">
            <div class="fw-bold text-dark mb-1">Thermal Receipt Test ({{ $store['paper_width'] }}mm)</div>
            <div class="text-muted" style="font-size:12px;">Sends a sample print to verify cutter, font clarity, and direct printing.</div>
        </div>
        <div id="receipt-print" style="padding:16px;">
            @include('receipts._thermal', ['r' => $r, 'store' => $store])
        </div>
    </div>

    <script>
        function fitPageToReceipt() {
            var receipt = document.querySelector('.receipt');
            if (receipt) {
                var heightPx = receipt.scrollHeight || receipt.offsetHeight;
                var heightMm = Math.ceil(heightPx / 3.7795) + 5;
                var style = document.getElementById('page-style');
                if (style) {
                    style.textContent = style.textContent.replace(
                        /@page\s*\{[^}]*\}/,
                        '@page { size: 72mm ' + heightMm + 'mm; margin: 0mm !important; }'
                    );
                }
            }
        }
        var params = new URLSearchParams(location.search);
        if (params.get('direct') === '0') { /* preview only */ }
        else {
            setTimeout(function () {
                fitPageToReceipt();
                setTimeout(function () { window.print(); }, 100);
            }, 250);
        }
    </script>
</body>
</html>
