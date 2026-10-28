@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Products</h5><small class="text-muted ms-1">{{ number_format($products->total()) }} item(s)</small>
@endsection

@section('page_actions')
<div class="d-flex gap-2">
    <a href="{{ route('taxonomy.index') }}" class="btn btn-sm btn-outline-accent">Categories &amp; suppliers</a>
    @can('products.manage')
        <a href="{{ route('products.create') }}" class="btn btn-sm btn-accent">+ New product</a>
    @endcan
</div>
@endsection

@section('content')
{{-- filter bar — Flutter parity: Active / Hidden / All segments --}}
<form method="GET" class="mb-3">
    <div class="feature-card p-2 d-flex flex-wrap gap-2 align-items-end">
        <div class="d-flex gap-1" role="group">
            <input type="radio" class="btn-check" name="status" data-live-change id="st-active" value="active" {{ $status === 'active' ? 'checked' : '' }}>
            <label class="btn btn-sm btn-outline-accent" for="st-active">Active</label>
            <input type="radio" class="btn-check" name="status" data-live-change id="st-hidden" value="hidden" {{ $status === 'hidden' ? 'checked' : '' }}>
            <label class="btn btn-sm btn-outline-accent" for="st-hidden">Hidden <span class="text-muted">({{ $hiddenCount }})</span></label>
            <input type="radio" class="btn-check" name="status" data-live-change id="st-all" value="all" {{ $status === 'all' ? 'checked' : '' }}>
            <label class="btn btn-sm btn-outline-accent" for="st-all">All</label>
        </div>
        <div class="flex-grow-1" style="min-width:200px;">
            <input name="q" data-live-search class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="Search by product name, SKU or barcode…" autocomplete="off">
        </div>
        <div>
            <select name="category_id" data-live-change class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ ($filters['category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" name="low" value="1" data-live-change id="low-stock" {{ ($filters['low'] ?? '') === '1' ? 'checked' : '' }}>
            <label class="form-check-label small" for="low-stock">Low stock only</label>
        </div>
        <button class="btn btn-sm btn-accent">Filter</button>
    </div>
</form>

<div class="card feature-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th style="width:34%;">Name</th><th>Category</th><th>Type</th>
                <th class="text-end">Cost</th><th class="text-end">Price</th><th class="text-end">On hand</th><th style="width:190px;"></th>
            </tr></thead>
            <tbody>
            @forelse($products as $product)
                @php($rule = $product->reorder_level > 0 ? $product->reorder_level : low_stock_threshold())
                @php($low = $product->stock_cached <= $rule)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="p-tile" style="width:30px;height:30px;flex-basis:30px;">{{ $product->tracking_type === 'serialized' ? '🎫' : '📦' }}</span>
                            <div class="min-w-0">
                                <div class="fw-semibold d-flex align-items-center gap-2">
                                    {{ $product->name }}
                                    @unless($product->is_active)<span class="pill off">inactive</span>@endunless
                                </div>
                                <div class="text-muted" style="font-size:.72rem;">{{ $product->sku }} · {{ $product->brand?->name ?? 'No brand' }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="small">{{ $product->category?->name ?? '—' }}</td>
                    <td><span class="pill">{{ $product->tracking_type === 'serialized' ? 'Serialized' : 'Quantity' }}</span></td>
                    <td class="text-end small text-muted">{{ money($product->cost_price) }}</td>
                    <td class="text-end fw-bold">{{ money($product->selling_price) }}</td>
                    <td class="text-end">
                        <span class="stock-chip {{ $product->stock_cached <= 0 ? 'out' : '' }} {{ $low ? '' : '' }} {{ $low && $product->stock_cached > 0 ? 'bg-warning bg-opacity-25' : '' }}">
                            {{ rtrim(rtrim((string) $product->stock_cached, '0'), '.') }}
                        </span>
                        @if($low && $product->stock_cached <= 0) <span class="text-danger">⛔</span>@elseif($low) <span class="text-warning">⚠️</span>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        @php($dp = [
                            'sku' => $product->sku, 'barcode' => $product->barcode, 'name' => $product->name,
                            'category' => $product->category?->name, 'brand' => $product->brand?->name,
                            'cost' => (float) $product->cost_price, 'price' => (float) $product->selling_price,
                            'tax' => (float) $product->tax_rate, 'reorder' => (float) $product->reorder_level,
                            'stock' => (float) $product->stock_cached, 'type' => $product->tracking_type,
                            'status' => $product->is_active,
                            'margin' => ((float) $product->selling_price - (float) $product->cost_price) / max(0.01, (float) $product->selling_price) * 100,
                            'desc' => $product->description,
                        ])
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal" data-product='@json($dp)'>Details</button>
                        @can('products.manage')
                            <a class="btn btn-sm btn-outline-accent" href="{{ route('products.edit', $product) }}">Edit</a>
                            <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline" onsubmit="return confirm('Delete {{ e($product->name) }}?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Delete (inactive if it has sold)">✕</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4">
                    <div class="mb-1">📦</div>
                    <div class="small fw-semibold">No products found</div>
                    <div class="text-muted" style="font-size:.78rem;">Import a catalog or add one manually.</div>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $products->links() }}</div>
</div>

{{-- detail dialog (Flutter product detail parity) --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog" style="max-width:440px;">
        <div class="modal-content feature-card">
            <div class="modal-header"><h6 class="modal-title" id="d-name"></h6></div>
            <div class="modal-body small">
                <div class="detail-row mb-2"><span class="label">SKU</span><span class="value" id="d-sku"></span></div>
                <div class="detail-row mb-2"><span class="label">Barcode</span><span class="value" id="d-barcode"></span></div>
                <div class="detail-row mb-2"><span class="label">Category</span><span class="value" id="d-cat"></span></div>
                <div class="detail-row mb-2"><span class="label">Brand</span><span class="value" id="d-brand"></span></div>
                <div class="detail-row mb-2"><span class="label">Type</span><span class="value" id="d-type"></span></div>
                <div class="detail-row mb-2"><span class="label">Status</span><span class="value" id="d-status"></span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Cost</span><b id="d-cost"></b></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Selling price</span><b id="d-price"></b></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Margin</span><b id="d-margin"></b></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">VAT rate</span><span id="d-tax"></span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">Reorder at</span><span id="d-reorder"></span></div>
                <div class="d-flex justify-content-between py-1"><span class="text-muted">On hand</span><b id="d-stock"></b></div>
                <div class="text-muted mt-2" style="font-size:.8rem;" id="d-desc"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('detailModal').addEventListener('show.bs.modal', function (e) {
    const d = JSON.parse(e.relatedTarget.dataset.product);
    document.getElementById('d-name').textContent = d.name;
    document.getElementById('d-sku').textContent = d.sku;
    document.getElementById('d-barcode').textContent = d.barcode || '—';
    document.getElementById('d-cat').textContent = d.category || '—';
    document.getElementById('d-brand').textContent = d.brand || '—';
    document.getElementById('d-type').textContent = d.type === 'serialized' ? 'Serialized' : 'Quantity';
    document.getElementById('d-status').textContent = d.status ? 'Active' : 'Hidden';
    document.getElementById('d-cost').textContent = '{{ \App\Models\Setting::get('currency', 'GHS') }} ' + d.cost.toFixed(2);
    document.getElementById('d-price').textContent = '{{ \App\Models\Setting::get('currency', 'GHS') }} ' + d.price.toFixed(2);
    document.getElementById('d-margin').textContent = d.margin.toFixed(1) + '%';
    document.getElementById('d-tax').textContent = d.tax + '% VAT incl.';
    document.getElementById('d-reorder').textContent = d.reorder || 'Global threshold';
    document.getElementById('d-stock').textContent = d.stock;
    document.getElementById('d-desc').textContent = d.desc || '';
});
</script>
@endpush
