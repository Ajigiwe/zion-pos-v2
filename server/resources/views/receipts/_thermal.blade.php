{{-- Thermal receipt — Flutter receipt_document parity.
    Receives $r array: kind, title, number, date, cashier, ref_number, items[['name','qty','unit','total']],
    subtotal, discount, tax, total, payments[['method','amount','ref']], change, needs_review.
    Wrapper variables: $store (store_info()), $currency width/margins derived from $store. --}}
@php($w = (int) ($store['paper_width'] ?? '80'))
@php($side = max(2, min(36, (float) ($store['side_margin'] ?? 14))))
@php($feed = max(10, min(120, (float) ($store['bottom_feed_space'] ?? 50))))
@php($currency = $store['currency'] ?? 'GHS')
@php($tels = array_values(array_filter(array_map('trim', explode('/', $store['phone'] ?? '')))))
<div class="receipt" style="width:{{ $w }}mm;">
    @if($r['needs_review'] ?? false)
        <div style="text-align:center;font-weight:700;color:#B45309;">⚠ OFFLINE SALE — PENDING REVIEW</div>
        <hr>
    @endif

    {{--< store letterhead --}}
    <div class="receipt-center">
        @if($store['show_store_name'] ?? true)
            <div style="font-weight:800;font-size:14px;letter-spacing:.5px;text-transform:uppercase;">{{ mb_strtoupper($store['name'] ?? '') }}</div>
        @endif
        @if($store['show_store_address'] ?? true)
            @if(! empty($store['address']))<div style="font-size:10px;">{{ $store['address'] }}</div>@endif
        @endif
        @if($store['show_store_phone'] ?? true)
            @foreach($tels as $tel)
                <div style="font-size:10px;">Tel: {{ $tel }}</div>
            @endforeach
        @endif
        @if(! empty($store['header_custom_text']))
            <div style="font-size:9.5px;margin-top:2px;">{{ $store['header_custom_text'] }}</div>
        @endif
    </div>
    <div class="solid-rule" style="margin:4px 0;"></div>

    {{-- document meta --}}
    <div style="font-weight:700;font-size:11px;">{{ $r['title'] }}: {{ $r['number'] }}</div>
    @if(! empty($r['ref_number']))<div style="font-size:10px;">Original Sale: {{ $r['ref_number'] }}</div>@endif
    <div style="font-size:10px;">{{ $r['date'] }}</div>
    @if(($store['show_cashier_name'] ?? true) && ! empty($r['cashier']))
        <div style="font-size:10px;">Served by: {{ $r['cashier'] }}</div>
    @endif
    <hr>

    {{-- items --}}
    @if(($r['kind'] ?? 'sale') !== 'test')
        @foreach(($r['items'] ?? []) as $item)
            <div style="font-weight:600;">{{ \Illuminate\Support\Str::limit($item['name'], 30) }}</div>
            <div style="display:flex;justify-content:space-between;">
                @if($store['show_item_price_math'] ?? true)
                    <span style="color:#333;">{{ rtrim(rtrim(number_format((float) $item['qty'], 2), '0'), '.') }} x {{ $currency }} {{ number_format((float) $item['unit'], 2) }}</span>
                @else
                    <span>{{ rtrim(rtrim(number_format((float) $item['qty'], 2), '0'), '.') }} pcs</span>
                @endif
                <span>{{ $currency }} {{ number_format((float) $item['total'], 2) }}</span>
            </div>
        @endforeach
        <hr>
    @endif

    {{-- totals --}}
    <div style="display:flex;justify-content:space-between;"><span>Subtotal:</span><span>{{ $currency }} {{ number_format((float) ($r['subtotal'] ?? 0), 2) }}</span></div>
    @if(($store['show_discount'] ?? true) && (float) ($r['discount'] ?? 0) > 0)
        <div style="display:flex;justify-content:space-between;"><span>Discount:</span><span>-{{ $currency }} {{ number_format((float) $r['discount'], 2) }}</span></div>
    @endif
    @if(($store['show_tax'] ?? true) && (float) ($r['tax'] ?? 0) > 0)
        <div style="display:flex;justify-content:space-between;"><span>VAT incl.:</span><span>{{ $currency }} {{ number_format((float) $r['tax'], 2) }}</span></div>
    @endif
    <div style="display:flex;justify-content:space-between;font-weight:800;">
        <span>TOTAL:</span><span>{{ $currency }} {{ number_format((float) ($r['total'] ?? 0), 2) }}</span>
    </div>

    {{-- payments --}}
    @if($store['show_payment_breakdown'] ?? true)
        @foreach(($r['payments'] ?? []) as $p)
            <div style="display:flex;justify-content:space-between;">
                <span>{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $p['method'])) }}:</span>
                <span>{{ $currency }} {{ number_format((float) $p['amount'], 2) }}</span>
            </div>
            @if(! empty($p['ref']))<div style="padding-left:10px;font-size:10px;">Ref: {{ $p['ref'] }}</div>@endif
        @endforeach
    @endif
    @if(($store['show_change_due'] ?? true) && (float) ($r['change'] ?? 0) > 0)
        <div style="display:flex;justify-content:space-between;font-weight:700;"><span>Change:</span><span>{{ $currency }} {{ number_format((float) $r['change'], 2) }}</span></div>
    @endif
    <hr>

    {{-- footer, signature, barcode --}}
    @php($footer = $store['footer_message'] ?? '')
    @if(! empty($footer))
        <div class="receipt-center" style="font-size:9.5px;white-space:pre-line;">{{ $footer }}</div>
    @endif
    @if($store['show_customer_signature'] ?? false)
        <div class="signature-line">Customer Signature</div>
    @endif
    @if(($store['show_barcode'] ?? true) && ($r['kind'] ?? '') !== 'test')
        <div class="barcode-wrap" style="margin-top:6px;">
            {!! barcode_svg($r['number'], $w - 0) !!}
            <div class="barcode-code">{{ $r['number'] }}</div>
        </div>
    @else
        <div style="height:{{ $feed }}mm;"></div>
    @endif
    @if(($store['show_barcode'] ?? true) && ($r['kind'] ?? '') !== 'test')
        <div style="height:{{ $feed * 0.3 }}mm;"></div>
    @else
        <div style="height:{{ $feed * 0.3 }}mm;"></div>
    @endif
</div>
