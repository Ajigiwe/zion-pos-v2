@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Inventory</h5><small class="text-muted ms-1">Every change is a ledger movement — stock is never edited directly.</small>
@endsection

@section('page_actions')
<div class="d-flex gap-2 flex-wrap">
    @can('inventory.adjust')<a href="{{ route('inventory.opening') }}" class="btn btn-sm btn-outline-accent">Opening stock…</a>@endcan
    @can('inventory.adjust')<button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#invModal"
        data-btn="purchase">Add stock</button>@endcan
    @can('inventory.adjust')<button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#invModal"
        data-btn="adjustment">Adjust</button>@endcan
    @can('inventory.adjust')<button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#invModal"
        data-btn="damage">Damage</button>@endcan
    <a href="{{ route('inventory.sheet') }}" target="_blank" class="btn btn-sm btn-outline-accent">Stock sheet</a>
</div>
@endsection

@section('content')
{{-- movement form (mode=add|adjust|damage) --}}
<div class="mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted" style="letter-spacing:.5px;">MOVEMENT TYPE</label>
            <select name="type" data-live-change class="form-select form-select-sm">
                <option value="">All types</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" {{ ($filter['type'] ?? '') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label small text-muted" style="letter-spacing:.5px;">SEARCH PRODUCT</label>
            <div class="input-group input-group-sm">
                <input name="search" data-live-search class="form-control" placeholder="Search by product name, SKU or barcode…" value="{{ $filter['search'] ?? '' }}" autocomplete="off">
            </div>
        </div>
        <div class="col-6 col-md-2"><input name="from" type="date" class="form-control form-control-sm" value="{{ $filter['from'] ?? '' }}" title="From"></div>
        <div class="col-6 col-md-2"><input name="to" type="date" class="form-control form-control-sm" value="{{ $filter['to'] ?? '' }}" title="To"></div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary flex-fill">Filter</button>
            <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear">✖</a>
        </div>
    </form>
</div>

<div class="card feature-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr>
                <th style="width:30%;">Product</th><th style="width:16%;">Type</th>
                <th class="text-end" style="width:80px;">Qty</th><th style="width:130px;">Date</th><th>Reason</th>
            </tr></thead>
            <tbody>
                @forelse($movements as $movement)
                    @php($label = \App\Enums\MovementType::tryFrom($movement->movement_type)?->label() ?? $movement->movement_type)
                    @php($inc = (float) $movement->quantity > 0)
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <span class="icon-chip" style="width:28px;height:28px;flex-basis:28px;background:{{ $inc ? 'color-mix(in srgb, var(--accent) 14%, transparent)' : 'rgba(220,38,38,.12)' }};color:{{ $inc ? 'var(--accent)' : '#DC2626' }};">{{ $inc ? '⤦' : '⤴' }}</span>
                            @can('inventory.adjust')
                                <button type="button" class="border-0 bg-transparent p-0 shadow-none d-block text-start" data-open-stock="{{ $movement->product_id }}" title="Adjust stock for this product">
                                    <span class="small fw-semibold text-break text-decoration-underline text-decoration-opacity-50">{{ $movement->product?->name }}</span>
                                </button>
                            @else
                                <span class="small fw-semibold text-break">{{ $movement->product?->name }}</span>
                            @endcan
                        </td>
                        <td><span class="pill">{{ $label }}</span></td>
                        <td class="text-end fw-bold small text-nowrap {{ $inc ? 'text-success' : 'text-danger' }}">{{ $inc ? '+' : '−' }}{{ rtrim(rtrim((string) abs((float) $movement->quantity), '0'), '.') }}</td>
                        <td class="small text-muted">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                        <td class="small text-muted text-break">{{ $movement->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4">
                        <div class="mb-1">📋</div>
                        <div class="small fw-semibold">No stock movements yet</div>
                        <div class="text-muted" style="font-size:.78rem;">Record opening stock or add stock to build the ledger.</div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add / Adjust / Damage modal --}}
<div class="modal fade" id="invModal" tabindex="-1">
    <div class="modal-dialog" style="max-width:420px;">
        <div class="modal-content feature-card">
            <div class="modal-header"><h6 class="modal-title" id="inv-title">Add stock</h6></div>
            <form method="POST" action="{{ route('inventory.adjust') }}">
                @csrf
                <input type="hidden" name="movement_type" id="inv-type" value="purchase">
                <input type="hidden" name="direction" id="inv-direction" value="in">
                <div class="modal-body">
                    <label class="form-label small" id="inv-product-label">Product *</label>
                    <input type="hidden" name="product_id" id="inv-product" value="">
                    <div class="position-relative">
                        <input type="text" id="inv-product-search" class="form-control form-control-sm" placeholder="Search by name or SKU…" autocomplete="off" spellcheck="false" required style="color:var(--text);">
                        <div id="inv-product-list" class="list-group position-absolute w-100 d-none shadow-sm" style="max-height:240px;overflow-y:auto;z-index:1055;"></div>
                    </div>

                    <label class="form-label small mt-2" id="inv-qty-label">Units received *</label>
                    <input name="quantity" id="inv-qty" type="number" step="0.001" min="0.001" required class="form-control form-control-sm">
                    <div class="form-text" id="inv-qty-help">Records a PURCHASE movement (+quantity)</div>

                    <label class="form-label small mt-2" id="inv-reason-label">Reason *</label>
                    <input name="reason" id="inv-reason" class="form-control form-control-sm" placeholder="">
                    <div class="text-danger small" id="inv-error" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-accent">Record movement</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const INV_MODES = {
    purchase:   { title: 'Add stock', q: 'Units received *',   qh: 'Records a PURCHASE movement (+quantity)', reason: 'Reason', prefill: '' },
    adjustment: { title: 'Adjust to physical count', q: 'Physical count *', qh: 'Records an ADJUSTMENT for the difference', reason: 'Reason *', prefill: 'Physical stock count' },
    damage:     { title: 'Damage / write-off', q: 'Units damaged *',  qh: 'Records a DAMAGE movement (−quantity)', reason: 'Reason *', prefill: '' },
};

// Searchable product picker: name-or-SKU filter, click or Enter to choose,
// fills the hidden product_id for the normal form submit.
const INV_PRODUCTS = @json($pickerProducts);
const invSearch = document.getElementById('inv-product-search');
const invList = document.getElementById('inv-product-list');
const invProduct = document.getElementById('inv-product');
let invPick = null;

function invEscape(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function invRender(q) {
    const term = q.trim().toLowerCase();
    const matches = term ? INV_PRODUCTS.filter((p) => p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term)) : INV_PRODUCTS;
    const shown = matches.slice(0, 40);
    invList.innerHTML = shown.length
        ? shown.map((p) => {
            const st = Number(p.stock_cached || 0);
            const stTxt = Number.isInteger(st) ? String(st) : st.toFixed(1).replace(/\.0$/, '');
            return `<button type="button" class="list-group-item list-group-item-action small px-3 py-1" data-id="${p.id}">${invEscape(p.name)} <span class="text-muted">· ${invEscape(p.sku || 'no SKU')} · stock ${stTxt}</span></button>`;
        }).join('')
        : '<div class="list-group-item small text-muted px-3 py-2">No product matches this search.</div>';
    invList.classList.remove('d-none');
}

function invChoose(p) {
    invPick = p;
    invProduct.value = p.id;
    const st = Number(p.stock_cached || 0);
    const stTxt = Number.isInteger(st) ? String(st) : st.toFixed(1).replace(/\.0$/, '');
    invSearch.value = p.name + ' (stock: ' + stTxt + ')';
    invList.classList.add('d-none');
}

invSearch.addEventListener('focus', () => invRender(invSearch.value));
invSearch.addEventListener('input', () => { invPick = null; invProduct.value = ''; invRender(invSearch.value); });
invSearch.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const first = invList.querySelector('[data-id]');
        if (first) { invChoose(INV_PRODUCTS.find((p) => String(p.id) === first.dataset.id)); }
    } else if (e.key === 'Escape') {
        invList.classList.add('d-none');
    }
});
invList.addEventListener('click', (e) => {
    const b = e.target.closest('[data-id]');
    if (b) { invChoose(INV_PRODUCTS.find((p) => String(p.id) === b.dataset.id)); }
});
document.addEventListener('click', (e) => {
    if (!e.target.closest('#inv-product-search') && !e.target.closest('#inv-product-list')) invList.classList.add('d-none');
});
document.querySelectorAll('[data-btn]').forEach((b) => b.addEventListener('click', () => setInvMode(b.dataset.btn)));

function setInvMode(btnKey) {
    const mode = INV_MODES[btnKey];
    document.getElementById('inv-title').textContent = mode.title;
    document.getElementById('inv-type').value = btnKey;
    document.getElementById('inv-direction').value = mode.title === 'Damage / write-off' ? 'out' : 'in';
    document.getElementById('inv-qty-label').textContent = mode.q;
    document.getElementById('inv-qty-help').textContent = mode.qh;
    const reasonLabel = document.getElementById('inv-reason-label');
    reasonLabel.textContent = mode.reason;
    document.getElementById('inv-reason').value = mode.prefill;
    // reset the product picker for a fresh selection
    invPick = null;
    invProduct.value = '';
    invSearch.value = '';
    invList.classList.add('d-none');
}

// Clicking a product name in the ledger opens the movement modal with the
// product already chosen (Adjust/switch mode buttons stay available).
document.querySelectorAll('[data-open-stock]').forEach((b) => b.addEventListener('click', () => {
    const p = INV_PRODUCTS.find((x) => String(x.id) === b.dataset.openStock);
    if (!p) return;
    setInvMode('purchase');
    invChoose(p);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('invModal')).show();
}));

// hidden inputs skip native validation - guard the submit ourselves
document.querySelector('#invModal form').addEventListener('submit', (e) => {
    const err = document.getElementById('inv-error');
    if (!invProduct.value) {
        err.textContent = 'Choose a product from the search list.';
        err.style.display = 'block';
        e.preventDefault();
    } else {
        err.style.display = 'none';
    }
});
</script>
@endpush
