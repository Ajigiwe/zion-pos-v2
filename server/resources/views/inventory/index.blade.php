@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Inventory</h5><small class="text-muted ms-1">Every change is a ledger movement — stock is never edited directly.</small>
@endsection

@section('page_actions')
<div class="d-flex gap-2">
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
        <div class="col-md-3">
            <label class="form-label small text-muted" style="letter-spacing:.5px;">MOVEMENT TYPE</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All types</option>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" {{ ($filter['type'] ?? '') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted" style="letter-spacing:.5px;">SEARCH PRODUCT</label>
            <div class="input-group input-group-sm">
                <input name="search" class="form-control" placeholder="Search by product name, SKU or barcode…" value="{{ $filter['search'] ?? '' }}">
            </div>
        </div>
        <div class="col-md-2"><input name="from" type="date" class="form-control form-control-sm" value="{{ $filter['from'] ?? '' }}" title="From"></div>
        <div class="col-md-2"><input name="to" type="date" class="form-control form-control-sm" value="{{ $filter['to'] ?? '' }}" title="To"></div>
        <div class="col-md-2 d-flex gap-2">
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
                            <span class="small fw-semibold">{{ $movement->product?->name }}</span>
                        </td>
                        <td><span class="pill">{{ $label }}</span></td>
                        <td class="text-end fw-bold small {{ $inc ? 'text-success' : 'text-danger' }}">{{ $inc ? '+' : '−' }}{{ rtrim(rtrim((string) abs((float) $movement->quantity), '0'), '.') }}</td>
                        <td class="small text-muted">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                        <td class="small text-muted">{{ $movement->reason ?? '—' }}</td>
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
                    <select name="product_id" id="inv-product" class="form-select form-select-sm" required>
                        <option value="">Select…</option>
                        @foreach(\App\Models\Product::query()->where('is_active', true)->orderBy('name')->get(['id','name','stock_cached']) as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}  (stock: {{ rtrim(rtrim((string) $p->stock_cached, '0'), '.') }})</option>
                        @endforeach
                    </select>

                    <label class="form-label small mt-2" id="inv-qty-label">Units received *</label>
                    <input name="quantity" id="inv-qty" type="number" step="0.5" min="0.001" required class="form-control form-control-sm">
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
document.querySelectorAll('[data-btn]').forEach((b) => b.addEventListener('click', () => {
    const mode = INV_MODES[b.dataset.btn];
    document.getElementById('inv-title').textContent = mode.title;
    document.getElementById('inv-type').value = b.dataset.btn;
    document.getElementById('inv-direction').value = mode.title === 'Damage / write-off' ? 'out' : 'in';
    document.getElementById('inv-qty-label').textContent = mode.q;
    document.getElementById('inv-qty-help').textContent = mode.qh;
    const reasonLabel = document.getElementById('inv-reason-label');
    reasonLabel.textContent = mode.reason;
    document.getElementById('inv-reason').value = mode.prefill;
}));
</script>
@endpush
