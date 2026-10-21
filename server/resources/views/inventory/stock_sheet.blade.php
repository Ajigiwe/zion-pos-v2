{{-- Stock count sheet (Flutter stock_sheet_document parity) — dompdf A4. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock count sheet — {{ $store['name'] }}</title>
    <style>
        @page { margin: 40px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111; }
        .letterhead-name { font-size: 20px; font-weight: 700; letter-spacing: .5px; }
        .muted { color: #444; font-size: 10px; }
        .rowline { display: flex; justify-content: space-between; margin-top: 8px; }
        .sheet { display: flex; justify-content: space-between; align-items: baseline; font-weight: 700; margin: 14px 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #000; padding: 4px 6px; text-align: left; }
        td.price, td.qty { text-align: right; }
        th { font-size: 10px; }
        .footer { margin-top: 26px; width: 60%; }
        .footer .line { margin-top: 18px; border-top: 1px solid #000; padding-top: 2px; font-size: 10px; width: 75%; }
    </style>
</head>
<body>
    <div class="letterhead-name">{{ mb_strtoupper($store['name']) }}</div>
    @if(! empty($store['address']))<div class="muted">{{ $store['address'] }}</div>@endif
    @php($tels = array_values(array_filter(array_map('trim', explode('/', $store['phone'] ?? '')))))
    @foreach($tels as $tel)
        <div class="muted">Tel: {{ $tel }}</div>
    @endforeach
    <div class="rowline"><span></span><span class="muted">{{ now()->format('Y-m-d') }}</span></div>

    <div class="sheet"><span>STOCK COUNT SHEET</span></div>

    <div class="table-responsive">
    <table>
        <thead>
        <tr><th style="width:6%;">#</th><th style="width:44%;">ITEM NAME</th><th style="width:20%;">PRICE (GHC)</th><th style="width:15%;">QTY</th></tr>
        </thead>
        <tbody>
        @foreach($products as $p)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $p->name }}</td>
                <td class="price">{{ number_format((float) $p->selling_price, 2) }}</td>
                <td class="qty">{{ rtrim(rtrim(number_format((float) $p->stock_cached, 2), '0'), '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    <div class="footer">
        <div class="line">Counted by: _________________________</div>
        <div class="line">Checked by: _________________________</div>
    </div>
</body>
</html>
