@extends('layouts.app')

@section('page_title', $mode === 'refund' ? 'Refund items' : 'Exchange items')

@php($shopping = $mode === 'exchange')

@section('content')
<div style="max-width:680px;">
    {{-- 0. sale header --}}
    <div class="card feature-card p-2 mb-3 d-flex align-items-center gap-2">
        <span class="p-tile">🧾</span>
        <div>
            <div class="fw-bold small">Sale {{ $sale->receipt_number }}</div>
            <div class="text-muted" style="font-size:.72rem;">{{ $sale->created_at->format('Y-m-d H:i') }}</div>
        </div>
    </div>

    {{-- 1. select items being returned --}}
    <div class="card feature-card mb-3">
        <div class="card-header small fw-bold">1. Select items being returned</div>
        <div class="card-body p-0">
            @foreach($lines as $line)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-semibold">{{ $line['name'] }}</div>
                        <div class="text-muted" style="font-size:.72rem;">{{ money($line['unit']) }} · {{ $line['remaining'] }} returnable</div>
                    </div>
                    <div class="cart-qty d-inline-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-dec="{{ $line['id'] }}" disabled>−</button>
                        <span class="px-1 fw-semibold" style="min-width:18px;text-align:center;" id="qty-{{ $line['id'] }}">0</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-inc="{{ $line['id'] }}" {{ $line['remaining'] <= 0 ? 'disabled' : '' }}>+</button>
                    </div>
                </div>
            @endforeach
            <div class="px-3 py-2 d-flex justify-content-between">
                <span class="text-muted small">Return Credit:</span>
                <span class="fw-bold text-accent" id="return-credit">{{ money(0) }}</span>
            </div>
        </div>
    </div>

    @if($shopping)
        {{-- 2. select replacement product (exchange only) --}}
        <div class="card feature-card mb-3">
            <div class="card-header small fw-bold">2. Select replacement product</div>
            <div class="card-body">
                <input id="repl-search" class="form-control form-control-sm mb-2" placeholder="Type product name or SKU to add…">
                <div id="repl-results" style="max-height:240px;overflow-y:auto;"></div>
            </div>
            <div class="card-body pt-0" id="repl-cart"></div>
        </div>
    @endif

    {{-- 3. settlement --}}
    <div class="card feature-card mb-3">
        <div class="card-header small fw-bold">{{ $shopping ? '3. Exchange Settlement' : '2. Refund Settlement' }}</div>
        <div class="card-body">
            @if($shopping)
                <div class="d-flex justify-content-between small py-1"><span>Replacement Total:</span><span id="repl-total">{{ money(0) }}</span></div>
                <div class="d-flex justify-content-between small py-1"><span>Return Credit:</span><span id="repl-credit">- {{ money(0) }}</span></div>
                <div class="divider my-2"></div>
                <div class="d-flex justify-content-between small fw-bold py-1"><span>Balance:</span><span id="repl-balance">{{ money(0) }}</span></div>

                <div id="pay-panel" class="rounded p-2 my-2" style="background:color-mix(in srgb, var(--accent) 8%, transparent);">
                    <div class="small fw-semibold mb-1">Customer pays difference:</div>
                    <div class="small"><span id="pay-amount">{{ money(0) }}</span></div>
                    <label class="form-label small mb-0 mt-2">Payment method</label>
                    <div class="d-flex gap-1 mb-1">
                        <input type="radio" class="btn-check" name="diff_method" id="m-cash" value="cash" checked><label class="btn btn-sm btn-outline-accent" for="m-cash">💵 Cash</label>
                        <input type="radio" class="btn-check" name="diff_method" id="m-momo" value="mobile_money"><label class="btn btn-sm btn-outline-accent" for="m-momo">📱 Mobile Money</label>
                    </div>
                    <label class="form-label small mb-0">Balance payment amount</label>
                    <input id="balance-amount" class="form-control form-control-sm" type="number" min="0" step="0.01" value="0">
                </div>

                <div id="cash-panel" class="change-box d-none align-items-center gap-2 p-2">
                    <span>💵</span>
                    <div>
                        <div class="label">Refund Cash Difference</div>
                        <div class="small">Replacement is cheaper — return <b id="cash-amount">{{ money(0) }}</b> in cash to the customer.</div>
                    </div>
                </div>
                <div id="even-panel" class="p-2 rounded d-none" style="background:#DCFCE7;color:#166534;font-weight:600;">
                    ✅ Even Exchange — No additional payment required.
                </div>
            @else
                <label class="form-label small mb-1">Refund method</label>
                <div class="d-flex gap-1 mb-2">
                    <input type="radio" class="btn-check" name="refund_method" id="rm-cash" value="cash" checked><label class="btn btn-sm btn-outline-accent" for="rm-cash"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h-.01"/></svg> Cash</label>
                    <input type="radio" class="btn-check" name="refund_method" id="rm-momo" value="mobile_money"><label class="btn btn-sm btn-outline-accent" for="rm-momo"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18.5h2"/></svg> Mobile Money</label>
                </div>
                <label class="form-label small mb-1">Reason (optional)</label>
                <textarea id="refund-reason" class="form-control form-control-sm" rows="2" maxlength="500"></textarea>
            @endif
        </div>
        <div class="card-footer">
            <button id="submit-flow" class="btn btn-accent w-100" disabled>{{ $shopping ? 'Complete Exchange' : 'Refund '.money(0) }}</button>
        </div>
    </div>
</div>

<form id="flow-form" method="POST" action="{{ $mode === 'refund' ? route('refunds.store') : route('exchanges.store') }}">
    @csrf
    <input type="hidden" name="original_sale_id" value="{{ $sale->id }}">
</form>
@endsection

@push('scripts')
<script>
(function () {
    const moneyFmt = (v) => `{{ $currency }} ${Number(v || 0).toFixed(2)}`;
    const CATALOG = @json($catalogForJs);
    const LINES = @json($lines);
    const EXCHANGE = {{ $shopping ? 'true' : 'false' }};

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const returns = {};    // sale_item_id -> qty return
    const replacements = {}; // product_id -> qty

    const state = () => ({
        returnsTotal: LINES.reduce((s, l) => s + (returns[l.id] || 0) * l.unit, 0),
        replTotal: Object.entries(replacements).reduce((s, [id, qty]) => {
            const p = CATALOG.find(x => x.id === Number(id));
            return s + (p ? p.price * qty : 0);
        }, 0),
    });

    function render() {
        // line steppers
        LINES.forEach((l) => {
            document.getElementById('qty-' + l.id).textContent = returns[l.id] || 0;
            const btn = document.querySelector(`[data-dec="${l.id}"]`);
            btn.disabled = !(returns[l.id] > 0);
        });
        const st = state();
        document.getElementById('return-credit').textContent = moneyFmt(st.returnsTotal);

        if (EXCHANGE) {
            // replacement cart summary
            const cart = document.getElementById('repl-cart');
            const entries = Object.entries(replacements);
            cart.innerHTML = entries.length
                ? entries.map(([id, qty]) => {
                    const p = CATALOG.find(x => x.id === Number(id));
                    if (!p) return '';
                    return `<div class="d-flex align-items-center gap-2 py-1 border-bottom" style="border-color:var(--hairline);">
                        <div class="flex-grow-1 min-w-0"><div class="small fw-semibold">${p.name}</div><div class="text-muted" style="font-size:.72rem;">${moneyFmt(p.price)} · ${p.stock} in stock</div></div>
                        <div class="cart-qty d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-rdec="${id}">−</button>
                            <span class="px-1" id="rq-${id}">${qty}</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-rinc="${id}" ${qty >= p.stock ? 'disabled' : ''}>+</button>
                        </div>
                    </div>`;
                }).join('')
                : '<div class="text-muted small py-2">No replacement products yet. Use the search bar above to find and add replacement items.</div>';
            cart.querySelectorAll('[data-rinc]').forEach(b => b.addEventListener('click', () => {
                const id = b.dataset.rinc;
                const p = CATALOG.find(x => x.id === Number(id));
                replacements[id] = Math.min((replacements[id] || 0) + 1, p ? p.stock : 0);
                render();
            }));
            cart.querySelectorAll('[data-rdec]').forEach(b => b.addEventListener('click', () => {
                const id = b.dataset.rdec;
                replacements[id] = (replacements[id] || 0) - 1;
                if (replacements[id] <= 0) delete replacements[id];
                render();
            }));

            // settlement
            const balance = st.replTotal - st.returnsTotal;
            document.getElementById('repl-total').textContent = moneyFmt(st.replTotal);
            document.getElementById('repl-credit').textContent = '- ' + moneyFmt(st.returnsTotal);
            document.getElementById('repl-balance').textContent = moneyFmt(Math.abs(balance));
            const pay = document.getElementById('pay-panel'), cash = document.getElementById('cash-panel'), even = document.getElementById('even-panel');
            pay.classList.toggle('d-none', balance <= 0);
            cash.classList.toggle('d-none', balance >= 0);
            even.classList.toggle('d-none', Math.abs(balance) > 0.005);
            if (balance > 0) {
                document.getElementById('pay-amount').textContent = moneyFmt(balance);
                if (parseFloat(document.getElementById('balance-amount').value) === 0) {
                    document.getElementById('balance-amount').value = balance.toFixed(2);
                }
            } else {
                document.getElementById('cash-amount').textContent = moneyFmt(Math.abs(balance));
            }
        }

        // submit label + enable
        const any = Object.values(returns).some(q => q > 0);
        const btn = document.getElementById('submit-flow');
        if (EXCHANGE) {
            const balance = st.replTotal - st.returnsTotal;
            btn.textContent = balance > 0 ? `Complete Exchange (Pay ${moneyFmt(balance)})`
                : balance < 0 ? `Complete Exchange (Refund ${moneyFmt(-balance)})` : 'Complete Exchange';
        } else {
            btn.textContent = `Refund ${moneyFmt(st.returnsTotal)}`;
        }
        btn.disabled = !any;
    }

    function bind() {
        document.querySelectorAll('[data-inc]').forEach(b => b.addEventListener('click', () => {
            const l = LINES.find(x => x.id === Number(b.dataset.inc));
            if (!l) return;
            if ((returns[l.id] || 0) < l.remaining) { returns[l.id] = (returns[l.id] || 0) + 1; render(); }
        }));
        document.querySelectorAll('[data-dec]').forEach(b => b.addEventListener('click', () => {
            const l = LINES.find(x => x.id === Number(b.dataset.dec));
            if (l && (returns[l.id] || 0) > 0) { returns[l.id]--; if (!returns[l.id]) delete returns[l.id]; render(); }
        }));

        if (EXCHANGE) {
            const input = document.getElementById('repl-search');
            const results = document.getElementById('repl-results');
            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                if (!q) { results.innerHTML = '<div class="text-muted small py-2">Use the search bar above to find and add replacement items.</div>'; return; }
                const hits = CATALOG.filter(p => p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q));
                results.innerHTML = hits.length
                    ? hits.map(p => `
                        <div class="d-flex align-items-center gap-2 py-1 border-bottom" style="border-color:var(--hairline);">
                            <div class="flex-grow-1 min-w-0">
                                <div class="small fw-semibold">${p.name}</div>
                                <div class="text-muted" style="font-size:.72rem;">${moneyFmt(p.price)} · ${p.stock > 0 ? 'stock ' + p.stock : '<span style="color:#DC2626;">Out of stock</span>'}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-accent" data-add="${p.id}" ${p.stock <= 0 ? 'disabled' : ''}>Add</button>
                        </div>`).join('')
                    : '<div class="text-muted small py-2">No matching products found.</div>';
                results.querySelectorAll('[data-add]').forEach(b => b.addEventListener('click', () => {
                    const p = CATALOG.find(x => x.id === Number(b.dataset.add));
                    if (!p || p.stock <= 0) return;
                    replacements[b.dataset.add] = Math.min((replacements[b.dataset.add] || 0) + 1, p.stock);
                    render();
                }));
            });
        }

        document.getElementById('submit-flow').addEventListener('click', submit);
        render();
    }

    function submit() {
        const lines = Object.entries(returns).map(([id, qty]) => ({ sale_item_id: id, quantity: qty }));
        if (!lines.length) { alert('Select at least one item to refund.'); return; }

        const form = document.getElementById('flow-form');
        if (EXCHANGE) {
            const repl = Object.entries(replacements).map(([id, qty]) => ({ product_id: id, quantity: qty }));
            if (!repl.length) { alert('Choose a replacement product.'); return; }
            const st = state();
            const balance = st.replTotal - st.returnsTotal;
            let method = document.querySelector('input[name="diff_method"]:checked').value;
            let paid = parseFloat(document.getElementById('balance-amount').value || '0');
            if (balance > 0.005 && paid < balance - 0.005) {
                alert(`The customer must pay ${moneyFmt(balance)} — only ${moneyFmt(paid)} captured.`);
                return;
            }
            addField(form, 'replacement_lines', JSON.stringify(repl));
            addField(form, 'return_lines', JSON.stringify(lines));
            addField(form, 'difference_method', method);
            if (method === 'mobile_money') addField(form, 'difference_reference', '');
        } else {
            addField(form, 'lines', JSON.stringify(lines));
            addField(form, 'method', document.querySelector('input[name="refund_method"]:checked').value);
            addField(form, 'reason', document.getElementById('refund-reason').value.trim());
        }
        form.submit();
    }

    function addField(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = name; input.value = value;
        form.appendChild(input);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
})();
</script>
@endpush
