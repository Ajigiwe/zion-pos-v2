{{-- Thermal receipt — Flutter receipt_document parity.
    Receives $r array: kind, title, number, date, cashier, ref_number, items[['name','qty','unit','total']],
    subtotal, discount, tax, total, payments[['method','amount','ref']], change, needs_review.
    Wrapper variables: $store (store_info()), $currency width/margins derived from $store. --}}
@php($w = (int) ($store['paper_width'] ?? '80'))
@php($side = max(2, min(36, (float) ($store['side_margin'] ?? 14))))
@php($feed = max(10, min(120, (float) ($store['bottom_feed_space'] ?? 50))))
@php($currency = $store['currency'] ?? 'GHS')
@php($tels = array_values(array_filter(array_map('trim', explode('/', $store['phone'] ?? '')))))
<style>
    @page { size: auto; margin: 0mm !important; }
    @media print {
        html, body {
            width: 100% !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            color: #000 !important;
        }
    }
    .receipt {
        font-family: 'Consolas', 'Courier New', monospace;
        width: 72mm;
        max-width: 72mm;
        margin: 0 auto;
        font-size: 12px;
        line-height: 1.35;
        color: #000 !important;
        font-weight: 700;
        background: #fff;
        padding: 0 2px;
        box-sizing: border-box;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .receipt-divider {
        letter-spacing: 1px;
        font-family: 'Consolas', 'Courier New', monospace;
        font-weight: 700;
        overflow: hidden;
        white-space: nowrap;
        line-height: 1;
        margin: 3px 0;
        text-align: center;
        color: #000 !important;
    }
</style>
<div class="receipt">
    @if($r['needs_review'] ?? false)
        <div style="text-align:center;font-weight:800;font-size:12px;color:#000;">⚠ OFFLINE SALE — PENDING REVIEW</div>
        <div class="receipt-divider">------------------------------------------------</div>
    @endif

    {{-- store letterhead --}}
    <div class="receipt-center">
        @if($store['show_store_name'] ?? true)
            <div style="font-weight:900;font-size:16px;letter-spacing:.5px;text-transform:uppercase;line-height:1.2;">{{ mb_strtoupper($store['name'] ?? '') }}</div>
        @endif
        @if($store['show_store_address'] ?? true)
            @if(! empty($store['address']))<div style="font-size:12px;font-weight:700;margin-top:2px;">{{ $store['address'] }}</div>@endif
        @endif
        @if($store['show_store_phone'] ?? true)
            @foreach($tels as $tel)
                <div style="font-size:12px;font-weight:700;">Tel: {{ $tel }}</div>
            @endforeach
        @endif
        @if(! empty($store['header_custom_text']))
            <div style="font-size:11.5px;font-weight:700;margin-top:2px;line-height:1.25;">{{ $store['header_custom_text'] }}</div>
        @endif
    </div>
    <div class="receipt-divider">------------------------------------------------</div>

    {{-- document meta --}}
    <div style="font-size:11.5px;font-weight:700;display:flex;justify-content:space-between;flex-wrap:wrap;">
        <span>{{ $r['title'] }}: {{ $r['number'] }}</span>
        <span>{{ $r['date'] }}</span>
    </div>
    @if(! empty($r['ref_number']))
        <div style="font-size:11.5px;font-weight:700;">Original Sale: {{ $r['ref_number'] }}</div>
    @endif
    @if(($store['show_cashier_name'] ?? true) && ! empty($r['cashier']))
        <div style="font-size:11.5px;font-weight:700;">Cashier: {{ $r['cashier'] }}</div>
    @endif
    <div class="receipt-divider">------------------------------------------------</div>

    {{-- column headers --}}
    <div style="display:flex;justify-content:space-between;font-weight:900;font-size:12px;">
        <span style="width:16%;">QTY</span>
        <span style="flex-grow:1;padding:0 4px;">ITEM DESCRIPTION</span>
        <span style="text-align:right;">AMOUNT</span>
    </div>
    <div class="receipt-divider">------------------------------------------------</div>

    {{-- items --}}
    @php($totalItemCount = 0)
    @if(($r['kind'] ?? 'sale') !== 'test')
        @foreach(($r['items'] ?? []) as $item)
            @php($qtyVal = (float)($item['qty'] ?? 1))
            @php($totalItemCount += $qtyVal)
            <div style="display:flex;justify-content:space-between;align-items:flex-start;font-size:12px;font-weight:700;margin:2px 0;">
                <span style="width:16%;">{{ rtrim(rtrim(number_format($qtyVal, 2), '0'), '.') }}x</span>
                <span style="flex-grow:1;padding:0 4px;word-break:break-word;">{{ \Illuminate\Support\Str::limit($item['name'], 30) }}</span>
                <span style="text-align:right;white-space:nowrap;">{{ $currency }} {{ number_format((float) $item['total'], 2) }}</span>
            </div>
            @if(($store['show_item_price_math'] ?? true) && $qtyVal > 1)
                <div style="font-size:11px;font-weight:600;padding-left:16%;color:#000;">
                    ({{ rtrim(rtrim(number_format($qtyVal, 2), '0'), '.') }} @ {{ $currency }} {{ number_format((float) $item['unit'], 2) }})
                </div>
            @endif
        @endforeach
    @else
        @php($totalItemCount = 1)
        <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;margin:2px 0;">
            <span style="width:16%;">1x</span>
            <span style="flex-grow:1;padding:0 4px;">Mic Foam</span>
            <span style="text-align:right;">{{ $currency }} 40.00</span>
        </div>
    @endif

    {{-- subtotal --}}
    <div style="margin-top:4px;">
        <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
            <span>Subtotal</span>
            <span>{{ $currency }} {{ number_format((float) (($r['kind'] ?? '') === 'test' ? 40 : ($r['subtotal'] ?? 0)), 2) }}</span>
        </div>
        @if(($store['show_discount'] ?? true) && (float) ($r['discount'] ?? 0) > 0)
            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
                <span>Discount</span>
                <span>-{{ $currency }} {{ number_format((float) $r['discount'], 2) }}</span>
            </div>
        @endif
        @if(($store['show_tax'] ?? true) && (float) ($r['tax'] ?? 0) > 0)
            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
                <span>VAT incl.</span>
                <span>{{ $currency }} {{ number_format((float) $r['tax'], 2) }}</span>
            </div>
        @endif
    </div>
    <div class="receipt-divider">------------------------------------------------</div>

    {{-- total due --}}
    <div style="display:flex;justify-content:space-between;font-weight:900;font-size:14px;letter-spacing:.3px;">
        <span>TOTAL DUE</span>
        <span>{{ $currency }} {{ number_format((float) (($r['kind'] ?? '') === 'test' ? 40 : ($r['total'] ?? 0)), 2) }}</span>
    </div>
    <div class="receipt-divider">------------------------------------------------</div>

    {{-- payments --}}
    @if($store['show_payment_breakdown'] ?? true)
        @if(($r['kind'] ?? '') === 'test')
            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
                <span>Paid (Cash)</span>
                <span>{{ $currency }} 40.00</span>
            </div>
        @else
            @foreach(($r['payments'] ?? []) as $p)
                @php($methodName = \Illuminate\Support\Str::title(str_replace('_', ' ', $p['method'])))
                <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
                    <span>Paid ({{ $methodName }})</span>
                    <span>{{ $currency }} {{ number_format((float) $p['amount'], 2) }}</span>
                </div>
                @if(! empty($p['ref']))<div style="padding-left:12px;font-size:11px;font-weight:600;">Ref: {{ $p['ref'] }}</div>@endif
            @endforeach
        @endif
    @endif
    @if($store['show_change_due'] ?? true)
        <div style="display:flex;justify-content:space-between;font-weight:800;font-size:12px;">
            <span>Change</span>
            <span>{{ $currency }} {{ number_format((float) ($r['change'] ?? 0), 2) }}</span>
        </div>
    @endif
    <div class="receipt-divider">------------------------------------------------</div>

    {{-- total items summary --}}
    <div class="receipt-center" style="font-size:12px;font-weight:700;margin:2px 0;">
        Total Items: {{ rtrim(rtrim(number_format($totalItemCount, 2), '0'), '.') }}
    </div>

    {{-- footer, signature, barcode --}}
    @php($footer = $store['footer_message'] ?? '')
    @if(! empty($footer))
        <div class="receipt-center" style="font-size:11.5px;font-weight:700;white-space:pre-line;line-height:1.3;margin-top:2px;">{{ $footer }}</div>
    @endif
    @if($store['show_customer_signature'] ?? false)
        <div class="signature-line" style="margin-top:14px;border-top:1.5px solid #000;width:70%;margin-inline:auto;text-align:center;font-size:11px;font-weight:700;padding-top:2px;">Customer Signature</div>
    @endif
    @if($store['show_barcode'] ?? true)
        <div class="barcode-wrap" style="margin-top:8px;text-align:center;">
            {!! barcode_svg($r['number'], $w - 0) !!}
            <div class="barcode-code" style="font-size:11.5px;font-weight:800;letter-spacing:1px;margin-top:2px;">{{ $r['number'] }}</div>
        </div>
    @endif
    <div class="receipt-divider">------------------------------------------------</div>
    <div style="height:2mm;"></div>
</div>
