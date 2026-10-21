@extends('layouts.app')

@section('page_title', 'Sales')

@push('styles')
<style>
    #pos-col-products { display: flex; flex-direction: column; min-width: 0; }
    #pos-list { overflow-y: auto; min-height: 0; }
    #pos-col-cart { width: 360px; flex-shrink: 0; display: flex; flex-direction: column; min-height: 0; }
    @media (max-width: 1199.98px) { #pos-col-cart { width: 340px; } }
    @media (max-width: 991.98px) {
        #pos-register { flex-direction: column; height: auto !important; }
        #pos-col-cart { width: 100%; max-width: 560px; margin: auto; }
        #pos-col-products { min-height: 340px; }
        #pos-list { max-height: 46vh; }
    }
    /* Summary row guards: label and amount never collide when squeezed */
    #pos-col-cart .rowline > .amt { white-space: nowrap; }
    /* Short viewports: compress the cart's fixed bottom cluster so Complete sale always stays visible */
    @media (max-height: 780px) {
        .hero-total { padding: .5rem .8rem; }
        .hero-total #pos-total { font-size: 1.25rem !important; }
        #pos-checkout { height: 38px !important; }
        .pos-summary .rowline { padding: .28rem 0; }
        #pos-col-cart .pos-summary { margin-top: .35rem; padding-top: .2rem; }
        #pos-payment-entry { margin-bottom: .5rem !important; }
        #pos-col-cart .cart-empty { padding: 1.2rem 0; }
    }
</style>
@endpush

@section('page_actions')
<div class="d-flex gap-1" role="group">
    <input type="radio" class="btn-check" name="pos-mode" id="mode-new" value="new" checked>
    <label class="btn btn-sm btn-outline-accent" for="mode-new"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h2l2.6 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L20 7H5"/></svg> New sale</label>
    <input type="radio" class="btn-check" name="pos-mode" id="mode-history" value="history">
    <label class="btn btn-sm btn-outline-accent" for="mode-history"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg> History</label>
</div>
@endsection

@section('content')
{{-- ============ NEW SALE (register) ============ --}}
<div id="pos-register" class="d-flex gap-3" style="height:100%;">
    {{-- left: search + categories + product list --}}
    <div id="pos-col-products" class="flex-grow-1 min-w-0">
        <div class="input-group input-group-lg mb-2">
            <span class="input-group-text bg-transparent" style="color:var(--muted);padding-inline:.9rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </span>
            <input id="pos-search" class="form-control" placeholder="Search product or scan barcode — press Enter to ring up" autocomplete="off" autofocus>
            <button id="pos-scan-btn" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scanModal" title="Scan Barcode">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8V5a2 2 0 0 1 2-2h3"/><path d="M16 3h3a2 2 0 0 1 2 2v3"/><path d="M21 16v3a2 2 0 0 1-2 2h-3"/><path d="M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M3 12h18"/></svg>
            </button>
        </div>
        <div class="chip-row mb-2" id="pos-category-row"></div>
        <div id="pos-list" class="card list-group list-group-flush feature-card overflow-auto"></div>
    </div>

    {{-- right: cart panel (Flutter width 390) --}}
    <div id="pos-col-cart" class="card feature-card p-3 d-flex flex-column">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold d-inline-flex align-items-center gap-2">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);"><path d="M4 6h16l-1.5 11.5a2 2 0 0 1-2 1.5h-9a2 2 0 0 1-2-1.5z"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                Current Sale
            </span>
            <span class="pill" id="pos-item-count">0 items</span>
        </div>
        <div id="pos-cart" class="flex-grow-1 overflow-auto" style="min-height:0;">
            <div class="text-muted small text-center py-4">No items yet — tap a product or scan.</div>
        </div>

        <div class="mt-2 pos-summary">
            <div class="rowline"><span>Subtotal</span><span class="amt" id="pos-subtotal">GHS 0.00</span></div>
            <div class="rowline"><span>Discount</span>
                <span class="amt input-group input-group-sm" style="max-width:118px;">
                    <input id="pos-discount" class="form-control text-end" type="number" min="0" step="0.01" value="0">
                    <span class="input-group-text" id="pos-currency-label">{{ $store['currency'] }}</span>
                </span>
            </div>
            <div class="rowline text-muted"><span>VAT (included)</span><span class="amt" id="pos-tax">GHS 0.00</span></div>
            <div class="hero-total d-flex justify-content-between align-items-center my-1" style="padding:.5rem .8rem;">
                <div class="d-flex align-items-baseline gap-2 min-w-0">
                    <span class="text-accent total-label" style="white-space:nowrap;">TOTAL DUE</span>
                    <span class="text-muted" style="font-size:.72rem;white-space:nowrap;" id="pos-units">0 units</span>
                </div>
                <div style="font-size:1.35rem;font-weight:900;" id="pos-total">GHS 0.00</div>
            </div>

            <div id="pos-payments" class="d-flex flex-wrap gap-1 mb-1"></div>
            <div id="pos-pay-row" class="d-flex justify-content-between small fw-bold text-danger mb-1">
                <span>Remaining Balance</span><span id="pos-remaining">GHS 0.00</span>
            </div>
            <div id="pos-change-box" class="change-box d-none align-items-center gap-2 p-2 mb-2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h-.01"/></svg>
                <div>
                    <div class="label">CHANGE DUE</div>
                    <div class="amount" id="pos-change">GHS 0.00</div>
                </div>
            </div>

            {{-- payment capture: method chips + amount share one row; quick chips above --}}
            <div id="pos-method-chips" class="d-flex gap-1 flex-wrap"></div>
            <div id="pos-quick-chips" class="d-flex flex-wrap mb-1" style="gap:.35rem;"></div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1" id="pos-payment-entry">
                <input id="pos-pay-amount" class="form-control form-control-sm text-end" style="width:96px;" type="number" min="0" step="0.01" placeholder="Amount received">
                <input id="pos-pay-ref" class="form-control form-control-sm" placeholder="Payer / txn ID" style="display:none;width:150px;">
                <button id="pos-pay-add" class="btn btn-sm btn-accent flex-shrink-0 ms-auto" style="min-width:56px;">Add</button>
            </div>

            <button id="pos-checkout" class="btn btn-accent w-100" style="height:40px;font-weight:700;" disabled>Complete sale</button>
        </div>
    </div>
</div>

{{-- ============ HISTORY ============ --}}
<div id="pos-history" class="d-none card feature-card" style="max-width:760px;">
    <div class="p-2" id="pos-history-list"></div>
</div>

{{-- ============ SCAN DIALOG (Flutter BarcodeScanDialog parity) ============ --}}
<div class="modal fade" id="scanModal" tabindex="-1">
    <div class="modal-dialog" style="max-width:480px;">
        <div class="modal-content feature-card p-3" style="border-radius:16px;">
            <div class="d-flex align-items-start justify-content-between">
                <div class="d-flex gap-2 align-items-center">
                    <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><circle cx="11" cy="11" r="4.5"/><path d="m17.5 17.5 2.5 2.5"/></svg>
                    </span>
                    <div>
                        <div class="fw-bold">Scan Barcode / Fast Ring-Up</div>
                        <div class="text-muted" style="font-size:.75rem;">Point USB/Bluetooth scanner or enter barcode</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="scan-visual" style="min-height:140px;">
                <div class="scan-box my-2">
                    <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
                    <div class="laser"></div>
                    <div class="scan-ready">READY TO SCAN</div>
                </div>
            </div>
            <div id="scan-matched" class="d-none"></div>
            <div id="scan-error" class="alert alert-danger py-2 small d-none" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;"></div>
            <label class="form-label small fw-semibold">Barcode / SKU / Item Code</label>
            <div class="input-group input-group-sm mb-3">
                <input id="scan-input" class="form-control" placeholder="e.g. 8901234567890" autocomplete="off">
                <button class="btn btn-outline-secondary" id="scan-clear" title="Clear">✕</button>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-sm btn-accent" id="scan-find">Find Product</button>
            </div>
        </div>
    </div>
</div>

{{-- offline queue payload data --}}
<meta name="pos-products" content="{{ base64_encode(json_encode($products->map(fn ($p) => [
    'id' => $p->id, 'sku' => $p->sku, 'barcode' => $p->barcode, 'name' => $p->name,
    'price' => (float) $p->selling_price, 'stock' => (float) $p->stock_cached,
    'tax_rate' => (float) $p->tax_rate, 'category' => $p->category?->name,
    'brand' => $p->brand?->name, 'serialized' => $p->tracking_type === 'serialized',
])->values()->all())) }}">
<meta name="pos-categories" content="{{ base64_encode(json_encode($categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all())) }}">
<script>
    window.POS_STORE = @json($store);
    window.POS_TILL = @json($till);
</script>
@endsection

@push('scripts')
<script src="/js/pos.js"></script>
@endpush
