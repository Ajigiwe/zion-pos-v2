@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0 d-inline">Dashboard</h5><small class="text-muted ms-1">{{ $day->format('d ') }}{{ $day->translatedFormat('F') }} {{ $day->format('Y') }}</small>
@endsection

@section('page_actions')
<a href="{{ url()->current() }}" class="btn btn-sm btn-outline-accent d-inline-flex align-items-center gap-1">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
    Refresh
</a>
@endsection

@section('content')
<div class="row g-3">
    {{-- takings stat card (hero, emphasized) --}}
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card stat-card hero p-3 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="stat-label">Today's takings</div>
                    <div class="stat-value">{{ money($takings) }}</div>
                    <div class="hero-sub">Cash + Mobile Money · after refunds</div>
                </div>
                <span class="icon-chip hero-chip">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 13h20"/></svg>
                </span>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="card feature-card stat-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="stat-label">Cash</div>
                    <div class="stat-value">{{ money($cash) }}</div>
                    <div class="text-muted stat-sub">{{ $cashCount }} payment{{ $cashCount === 1 ? '' : 's' }} today</div>
                </div>
                <span class="icon-chip" style="background:color-mix(in srgb, #0284C7 12%, transparent);color:#0284C7;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h-.01"/></svg>
                </span>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3 col-xl-3">
        <div class="card feature-card stat-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="stat-label">Mobile Money</div>
                    <div class="stat-value">{{ money($momo) }}</div>
                    <div class="text-muted stat-sub">{{ $momoCount }} payment{{ $momoCount === 1 ? '' : 's' }} today</div>
                </div>
                <span class="icon-chip" style="background:color-mix(in srgb, #0D9488 12%, transparent);color:#0D9488;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2.5"/><path d="M11 18.5h2"/></svg>
                </span>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
        <div class="card feature-card stat-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <div class="stat-label">Refunds today</div>
                    <div class="stat-value {{ $refundsCount > 0 ? 'text-danger' : '' }}">{{ money($refundsCount ? $cashRefunded + $momoRefunded : 0) }}</div>
                    <div class="text-muted stat-sub">{{ $refundsCount }} refund{{ $refundsCount === 1 ? '' : 's' }} total</div>
                </div>
                <span class="icon-chip" style="background:rgba(220,38,38,.12);color:#DC2626;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                </span>
            </div>
            <div class="row g-2 mt-2 small">
                <div class="col-6"><span class="text-muted">Cash:</span> {{ money($cashRefunded) }}</div>
                <div class="col-6"><span class="text-muted">MoMo:</span> {{ money($momoRefunded) }}</div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mt-4 mb-2">
    <div class="d-flex align-items-center gap-2">
        <span class="section-title">Sales today</span>
        <span class="pill ok">{{ $salesCount }} completed</span>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mt-3 mb-2">
    <div class="d-flex align-items-center gap-2">
        <span class="section-title">Low stock alerts</span>
        @if($lowStock->isNotEmpty())<span class="pill {{ ($lowStock->first()->stock_cached ?? 1) <= 0 ? 'danger' : 'warn' }}">{{ $lowStock->count() }} item{{ $lowStock->count() === 1 ? '' : 's' }}</span>@endif
    </div>
    @can('inventory.view')<a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-accent d-inline-flex align-items-center gap-1">Open inventory
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
    </a>@endcan
</div>
@php($canAdjust = auth()->user()->hasRole(['superadmin', 'admin', 'manager', 'inventory_manager']) || auth()->user()->can('inventory.adjust'))

<div class="card feature-card list-group list-group-flush low-stock-list">
    @forelse($lowStock as $product)
        @php($rule = $product->reorder_level > 0 ? $product->reorder_level : $threshold)
        @if($canAdjust)
            @php($sp = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'stock' => (float) $product->stock_cached, 'reorder' => (float) $rule])
            <button type="button" 
                class="list-group-item list-group-item-action px-3 py-2 low-stock-row d-flex align-items-center gap-2 {{ $product->stock_cached <= 0 ? 'is-out' : 'is-warn' }} is-clickable"
                data-bs-toggle="modal"
                data-bs-target="#stockEditModal"
                data-product='@json($sp)'
                title="Click to update stock for {{ $product->name }}">
                <span class="ls-icon">
                    @if($product->stock_cached <= 0)
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                    @else
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    @endif
                </span>
                <div class="flex-grow-1 min-w-0 text-start">
                    <div class="small fw-semibold text-truncate">{{ $product->name }}</div>
                    <div class="ls-hint">{{ $product->stock_cached <= 0 ? 'Out of stock' : 'Reorder at '.rtrim(rtrim(number_format((float) $rule, 2), '0'), '.') }}</div>
                </div>
                <span class="ls-action-hint d-none d-sm-inline-flex align-items-center gap-1">Edit stock ✎</span>
                <span class="ls-count {{ $product->stock_cached <= 0 ? 'out' : 'warn' }}">{{ rtrim(rtrim(number_format((float) $product->stock_cached, 2), '0'), '.') }} left</span>
            </button>
        @else
            <div class="list-group-item px-3 py-2 low-stock-row d-flex align-items-center gap-2 {{ $product->stock_cached <= 0 ? 'is-out' : 'is-warn' }}">
                <span class="ls-icon">
                    @if($product->stock_cached <= 0)
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg>
                    @else
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.6 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    @endif
                </span>
                <div class="flex-grow-1 min-w-0">
                    <div class="small fw-semibold text-truncate">{{ $product->name }}</div>
                    <div class="ls-hint">{{ $product->stock_cached <= 0 ? 'Out of stock' : 'Reorder at '.rtrim(rtrim(number_format((float) $rule, 2), '0'), '.') }}</div>
                </div>
                <span class="ls-count {{ $product->stock_cached <= 0 ? 'out' : 'warn' }}">{{ rtrim(rtrim(number_format((float) $product->stock_cached, 2), '0'), '.') }} left</span>
            </div>
        @endif
    @empty
        <div class="list-group-item px-3 py-4 text-center low-healthy">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <div class="small fw-semibold mt-1">All stock levels are healthy.</div>
        </div>
    @endforelse
</div>

@if($canAdjust)
{{-- Stock edit modal for owner & admin --}}
<div class="modal fade" id="stockEditModal" tabindex="-1" aria-labelledby="stockEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px;">
        <div class="modal-content feature-card shadow border-0">
            <div class="modal-header border-bottom py-3">
                <div class="min-w-0">
                    <h6 class="modal-title fw-bold mb-0" id="stockEditModalLabel">Quick Stock Edit</h6>
                    <div class="text-muted small text-truncate" id="sem-sku-display"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sem-form" method="POST" action="{{ route('inventory.adjust') }}">
                @csrf
                <input type="hidden" name="product_id" id="sem-product-id" value="">
                <input type="hidden" name="movement_type" id="sem-movement-type" value="purchase">
                <input type="hidden" name="direction" id="sem-direction" value="in">

                <div class="modal-body p-3">
                    {{-- Product overview banner --}}
                    <div class="p-2 rounded mb-3" style="background:var(--soft);border:1px solid var(--hairline);">
                        <div class="fw-bold text-truncate mb-1" id="sem-name-display" style="font-size:14px;"></div>
                        <div class="d-flex align-items-center justify-content-between small">
                            <span>Current on hand: <strong id="sem-stock-badge" class="badge"></strong></span>
                            <span class="text-muted" id="sem-reorder-display"></span>
                        </div>
                    </div>

                    {{-- Mode toggles: Add Stock vs Set Physical Count vs Damage --}}
                    <label class="form-label small fw-bold mb-1">Adjustment Action</label>
                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="sem-mode" id="sem-mode-purchase" value="purchase" checked autocomplete="off">
                        <label class="btn btn-sm btn-outline-accent" for="sem-mode-purchase">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M12 5v14M5 12h14"/></svg>Add Restock
                        </label>

                        <input type="radio" class="btn-check" name="sem-mode" id="sem-mode-adjust" value="adjustment" autocomplete="off">
                        <label class="btn btn-sm btn-outline-accent" for="sem-mode-adjust">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Set Count
                        </label>

                        <input type="radio" class="btn-check" name="sem-mode" id="sem-mode-damage" value="damage" autocomplete="off">
                        <label class="btn btn-sm btn-outline-accent" for="sem-mode-damage">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="me-1"><path d="M18 6 6 18M6 6l12 12"/></svg>Damage/Loss
                        </label>
                    </div>

                    {{-- Quantity input --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-bold mb-0" id="sem-qty-label">Units to add *</label>
                            <span class="small text-muted" id="sem-preview"></span>
                        </div>
                        <input type="number" step="0.5" min="0.001" name="quantity" id="sem-quantity" class="form-control form-control-lg fw-bold" placeholder="e.g. 10" required autofocus>
                        <div class="form-text small" id="sem-help">Records incoming restock inventory (+quantity).</div>

                        {{-- Quick tap buttons for adding --}}
                        <div class="d-flex gap-1 mt-2 flex-wrap" id="sem-quick-buttons">
                            <button type="button" class="btn btn-sm btn-outline-secondary sem-quick-btn" data-add="1">+1</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary sem-quick-btn" data-add="5">+5</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary sem-quick-btn" data-add="10">+10</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary sem-quick-btn" data-add="20">+20</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary sem-quick-btn" data-add="50">+50</button>
                        </div>
                    </div>

                    {{-- Reason --}}
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-1" id="sem-reason-label">Reason / Notes</label>
                        <input type="text" name="reason" id="sem-reason" class="form-control form-control-sm" placeholder="e.g. Restock shipment received" value="Restock / Replenishment">
                    </div>

                    {{-- Error box --}}
                    <div class="alert alert-danger py-2 px-3 small d-none" id="sem-error"></div>
                </div>

                <div class="modal-footer d-flex justify-content-between p-3 border-top">
                    @can('products.manage')
                        <a id="sem-edit-product-btn" href="#" class="btn btn-sm btn-link text-decoration-none p-0 text-accent">Edit product details →</a>
                    @else
                        <div></div>
                    @endcan
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-accent px-3 fw-semibold" id="sem-submit-btn">Save Stock</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    let currentProduct = null;
    const modalEl = document.getElementById('stockEditModal');
    if (!modalEl) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const form = document.getElementById('sem-form');
    const productIdInput = document.getElementById('sem-product-id');
    const movementTypeInput = document.getElementById('sem-movement-type');
    const directionInput = document.getElementById('sem-direction');
    const nameDisplay = document.getElementById('sem-name-display');
    const skuDisplay = document.getElementById('sem-sku-display');
    const stockBadge = document.getElementById('sem-stock-badge');
    const reorderDisplay = document.getElementById('sem-reorder-display');
    const qtyLabel = document.getElementById('sem-qty-label');
    const qtyInput = document.getElementById('sem-quantity');
    const helpDisplay = document.getElementById('sem-help');
    const previewDisplay = document.getElementById('sem-preview');
    const reasonInput = document.getElementById('sem-reason');
    const quickButtons = document.getElementById('sem-quick-buttons');
    const errorBox = document.getElementById('sem-error');
    const submitBtn = document.getElementById('sem-submit-btn');
    const editProductBtn = document.getElementById('sem-edit-product-btn');

    modalEl.addEventListener('show.bs.modal', function(e) {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Stock';

        const btn = e.relatedTarget;
        if (!btn || !btn.dataset.product) return;

        currentProduct = JSON.parse(btn.dataset.product);
        productIdInput.value = currentProduct.id;
        nameDisplay.textContent = currentProduct.name;
        skuDisplay.textContent = 'SKU: ' + currentProduct.sku;

        const isOut = currentProduct.stock <= 0;
        stockBadge.textContent = currentProduct.stock + ' on hand';
        stockBadge.className = 'badge ' + (isOut ? 'bg-danger' : 'bg-warning text-dark');
        reorderDisplay.textContent = 'Reorder alert at ' + currentProduct.reorder;

        if (editProductBtn) {
            editProductBtn.href = '/products/' + currentProduct.id + '/edit';
        }

        // Reset to purchase mode
        document.getElementById('sem-mode-purchase').checked = true;
        setMode('purchase');
        qtyInput.value = '';
        setTimeout(() => qtyInput.focus(), 250);
    });

    function setMode(mode) {
        if (!currentProduct) return;
        errorBox.classList.add('d-none');
        movementTypeInput.value = mode;

        if (mode === 'purchase') {
            directionInput.value = 'in';
            qtyLabel.textContent = 'Units received / restocked *';
            qtyInput.placeholder = 'e.g. 10';
            helpDisplay.textContent = 'Adds incoming inventory units to current stock (+quantity).';
            reasonInput.value = 'Restock / Replenishment';
            quickButtons.classList.remove('d-none');
        } else if (mode === 'adjustment') {
            directionInput.value = 'in';
            qtyLabel.textContent = 'Exact physical count *';
            qtyInput.placeholder = 'e.g. ' + Math.max(0, currentProduct.stock);
            helpDisplay.textContent = 'Calculates difference automatically to reach this physical count.';
            reasonInput.value = 'Physical stock count adjustment';
            quickButtons.classList.add('d-none');
        } else if (mode === 'damage') {
            directionInput.value = 'out';
            qtyLabel.textContent = 'Units damaged / written-off *';
            qtyInput.placeholder = 'e.g. 1';
            helpDisplay.textContent = 'Deducts damaged or lost units from stock (−quantity).';
            reasonInput.value = 'Damaged / Broken goods';
            quickButtons.classList.add('d-none');
        }
        updatePreview();
    }

    document.querySelectorAll('input[name="sem-mode"]').forEach(radio => {
        radio.addEventListener('change', function() {
            setMode(this.value);
        });
    });

    // Quick add buttons
    document.querySelectorAll('.sem-quick-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const add = parseFloat(this.dataset.add) || 0;
            const cur = parseFloat(qtyInput.value) || 0;
            qtyInput.value = cur + add;
            updatePreview();
        });
    });

    qtyInput.addEventListener('input', updatePreview);

    function updatePreview() {
        if (!currentProduct) return;
        const mode = movementTypeInput.value;
        const val = parseFloat(qtyInput.value);
        if (isNaN(val) || val <= 0) {
            previewDisplay.textContent = '';
            return;
        }

        if (mode === 'purchase') {
            const next = currentProduct.stock + val;
            previewDisplay.innerHTML = 'Result: <strong>' + next + '</strong> on hand';
        } else if (mode === 'adjustment') {
            const diff = val - currentProduct.stock;
            const diffText = diff >= 0 ? '+' + diff : String(diff);
            previewDisplay.innerHTML = 'Adjustment: <strong>' + diffText + '</strong> units';
        } else if (mode === 'damage') {
            const next = Math.max(0, currentProduct.stock - val);
            previewDisplay.innerHTML = 'Result: <strong>' + next + '</strong> on hand';
        }
    }

    // Fast AJAX submission with instant UI update
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorBox.classList.add('d-none');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';

        const formData = new FormData(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (!res.ok) {
                let msg = data.message || 'Error updating stock.';
                if (data.errors) {
                    msg = Object.values(data.errors).flat().join('<br>');
                }
                errorBox.innerHTML = msg;
                errorBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Stock';
                return;
            }

            // Success! Update row in DOM
            const newStock = Number(data.new_stock);
            const row = document.querySelector(`.low-stock-row[data-product*='"id":${currentProduct.id},']`)
                || document.querySelector(`.low-stock-row[data-product*='"id": ${currentProduct.id},']`);

            if (row) {
                if (newStock > currentProduct.reorder) {
                    row.style.transition = 'all .3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(20px)';
                    setTimeout(() => row.remove(), 300);
                } else {
                    currentProduct.stock = newStock;
                    row.dataset.product = JSON.stringify(currentProduct);
                    row.className = row.className.replace(/is-(out|warn)/g, newStock <= 0 ? 'is-out' : 'is-warn');
                    const countEl = row.querySelector('.ls-count');
                    if (countEl) {
                        countEl.className = 'ls-count ' + (newStock <= 0 ? 'out' : 'warn');
                        countEl.textContent = newStock + ' left';
                    }
                    const hintEl = row.querySelector('.ls-hint');
                    if (hintEl) {
                        hintEl.textContent = newStock <= 0 ? 'Out of stock' : 'Reorder at ' + currentProduct.reorder;
                    }
                }
            }

            modal.hide();

            if (window.flashInfo) {
                window.flashInfo(data.message || 'Stock updated successfully!');
            } else {
                location.reload();
            }
        } catch (err) {
            console.error('Stock adjust error:', err);
            form.submit();
        }
    });
})();
</script>
@endpush
@endif
@endsection

