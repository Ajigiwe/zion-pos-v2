@extends('layouts.app')

@section('page_title', $product->exists ? 'Edit product — '.$product->sku : 'New product')

@section('page_actions')
<a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary">← Products</a>
@endsection

@section('content')
<div style="max-width:760px;">
    <form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" class="card feature-card">
        @csrf
        @if($product->exists) @method('PUT') @endif
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label small">SKU *</label>
                <input name="sku" required class="form-control" value="{{ old('sku', $product->sku) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small d-flex justify-content-between">Barcode *
                    <button type="button" class="btn btn-sm btn-link p-0 text-accent" id="gen-barcode">Auto-generate</button>
                </label>
                <div class="input-group input-group-sm">
                    <input name="barcode" id="barcode-input" class="form-control" value="{{ old('barcode', $product->barcode) }}" placeholder="EAN-13…">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#scanModal">📷 Scan</button>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Tracking</label>
                <div class="d-flex gap-1">
                    <input type="radio" class="btn-check" name="tracking_type" id="tt-qty" value="quantity" {{ old('tracking_type', $product->tracking_type) === 'quantity' ? 'checked' : '' }}>
                    <label class="btn btn-sm btn-outline-accent" for="tt-qty">📦 Quantity</label>
                    <input type="radio" class="btn-check" name="tracking_type" id="tt-ser" value="serialized" {{ old('tracking_type', $product->tracking_type) === 'serialized' ? 'checked' : '' }}>
                    <label class="btn btn-sm btn-outline-accent" for="tt-ser">🎫 Serial</label>
                </div>
            </div>
            <div class="col-md-8">
                <label class="form-label small">Product name *</label>
                <input name="name" required class="form-control" value="{{ old('name', $product->name) }}">
                <div class="form-text">Displayed exactly as typed — used for search and receipts</div>
            </div>
            @unless($product->exists)
                <div class="col-md-4">
                    <label class="form-label small">Initial stock (new products only)</label>
                    <input name="initial_stock" type="number" step="0.001" min="0" class="form-control" value="{{ old('initial_stock', 0) }}">
                    <div class="form-text">Recorded as an OPENING movement</div>
                </div>
            @endunless
            <div class="col-md-6">
                <label class="form-label small">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Brand</label>
                <select name="brand_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label small">Description</label>
                <textarea name="description" rows="2" class="form-control">{{ old('description', $product->description) }}</textarea>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Cost price *</label>
                <input name="cost_price" type="number" step="0.01" min="0" required class="form-control" value="{{ old('cost_price', $product->cost_price) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Selling price *</label>
                <input name="selling_price" type="number" step="0.01" min="0" required class="form-control" value="{{ old('selling_price', $product->selling_price) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Tax rate %</label>
                <input name="tax_rate" type="number" step="0.01" min="0" max="100" class="form-control" value="{{ old('tax_rate', $product->tax_rate) }}">
                <div class="form-text">Included VAT portion</div>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Reorder level</label>
                <input name="reorder_level" type="number" step="0.001" min="0" class="form-control" value="{{ old('reorder_level', $product->reorder_level) }}">
                <div class="form-text">0 = use global threshold</div>
            </div>
            @if($product->exists)
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active-switch" {{ old('is_active', $product->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="active-switch">Active — visible on the till and dashboard</label>
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-accent px-4">Save</button>
        </div>
    </form>
</div>

{{-- barcode scan picker (Flutter BarcodeScanDialog parity) --}}
<div class="modal fade" id="scanModal" tabindex="-1">
    <div class="modal-dialog" style="max-width:440px;">
        <div class="modal-content feature-card p-3">
            <div class="d-flex align-items-start justify-content-between">
                <div class="d-flex gap-2 align-items-center">
                    <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);">🔎</span>
                    <div>
                        <div class="fw-bold">Scan Barcode</div>
                        <div class="text-muted" style="font-size:.75rem;">Point USB/Bluetooth scanner or type it below</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="scan-box my-2">
                <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
                <div class="laser"></div>
                <div class="scan-ready">READY TO SCAN</div>
            </div>
            <input id="scan-form-input" class="form-control" placeholder="Barcode / SKU / Item code…">
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// EAN-13 with check digit, 20xx-prefixed like the Flutter generator.
function ean13generate() {
    let base = '20' + String(Math.floor(Math.random() * 1e9)).padStart(9, '0').slice(0, 9);
    let sum = 0;
    for (let i = 0; i < 12; i++) sum += Number(base[i]) * (i % 2 ? 3 : 1);
    base += String((10 - (sum % 10)) % 10);
    return base;
}
document.getElementById('gen-barcode').addEventListener('click', function () {
    document.getElementById('barcode-input').value = ean13generate();
});
// scan dialog auto-submits whenever the scanner sends Enter
const scanInput = document.getElementById('scan-form-input');
scanInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = scanInput.value.trim();
        if (code.length >= 4) { document.getElementById('barcode-input').value = code; bootstrap.Modal.getInstance(document.getElementById('scanModal')).hide(); }
    }
});
</script>
@endpush
