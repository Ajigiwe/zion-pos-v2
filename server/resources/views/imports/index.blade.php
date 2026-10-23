@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Imports</h5><small class="text-muted ms-1">Bulk-load products or stock counts from CSV / XLSX.</small>
@endsection

@section('content')
<div style="max-width:840px;">
    <div class="feature-card p-3 mb-3">
        <div class="fw-bold small mb-2" style="letter-spacing:.5px;color:var(--muted);">IMPORT TYPE</div>
        <div class="d-flex gap-2 flex-wrap" role="group" id="imp-kind-row">
            <input type="radio" class="btn-check" name="imp-kind" id="k-products" value="products" checked>
            <label class="btn imp-type-card" for="k-products">
                <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg></span>
                <span class="tx"><span class="t">Products &amp; stock</span><span class="d">Create/update items with full details and opening quantities</span></span>
            </label>
            <input type="radio" class="btn-check" name="imp-kind" id="k-add" value="stock">
            <label class="btn imp-type-card" for="k-add">
                <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="7" width="16" height="13" rx="2"/><path d="M4 10h16"/><path d="M12 13.5v5M9.5 16h5"/></svg></span>
                <span class="tx"><span class="t">Add stock</span><span class="d">SKU + quantity rows — recorded as additive bulk-import movements</span></span>
            </label>
            <input type="radio" class="btn-check" name="imp-kind" id="k-set" value="stock">
            <label class="btn imp-type-card" for="k-set">
                <span class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="14" height="18" rx="2"/><path d="M8 3v2h6V3"/><path d="m8.5 12 2 2 4-4"/></svg></span>
                <span class="tx"><span class="t">Set to physical count</span><span class="d">SKU + counted rows — stock is recalculated to match what's on the shelf</span></span>
            </label>
        </div>
        <div class="text-muted mt-2" style="font-size:.78rem;" id="kind-help">
            One product per row: <code>sku, barcode, name, category, brand, cost_price, selling_price, tax_rate, reorder_level, quantity</code> — existing SKUs are updated; quantity records a bulk-import stock movement.
        </div>
    </div>

    <div class="feature-card p-3 mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 21h16"/></svg></span>
            <div class="fw-bold small">Spreadsheet file</div>
            <a class="small ms-auto d-inline-flex align-items-center gap-1" href="{{ route('imports.template', ['kind' => 'products']) }}" id="template-link">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v11"/><path d="m8 10 4 4 4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
                Download template CSV
            </a>
        </div>
        <form method="POST" action="{{ route('imports.products') }}" enctype="multipart/form-data" id="imp-form" class="m-0">
            @csrf
            <input type="hidden" name="mode" id="imp-mode" value="add">
            <label class="drop-zone" id="imp-drop">
                <input type="file" id="imp-file" name="file" accept=".csv,.xlsx,.xls" class="d-none">
                <span class="dz-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>
                </span>
                <span class="dz-text" id="dz-text">
                    <span class="t">Drop a CSV or XLSX file here, or click to browse</span>
                    <span class="d">max 5 MB · columns follow the template for the selected type</span>
                </span>
                <span class="chip-note">CHOOSE FILE</span>
            </label>
            <div class="d-flex justify-content-end align-items-center mt-3 gap-2">
                <button type="button" class="btn btn-sm btn-outline-accent d-inline-flex align-items-center gap-1" id="imp-analyze" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    Analyze
                </button>
                <button type="submit" class="btn btn-sm btn-accent d-inline-flex align-items-center gap-1" id="imp-start" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12h13"/><path d="m12 5 7 7-7 7"/></svg>
                    Start Import
                </button>
            </div>
        </form>
        <div id="imp-preview" class="mt-3"></div>
        <div id="imp-errors" class="alert alert-warning small mt-2 d-none" style="max-height:220px;overflow:auto;"><b>Issues found:</b><ul id="imp-error-list" class="mb-0 ms-3"></ul></div>
    </div>

    <div class="card feature-card">
        <div class="card-header small fw-bold bg-transparent d-flex align-items-center gap-2" style="padding:1rem 1.25rem;">
            <span class="icon-chip" style="width:28px;height:28px;flex-basis:28px;background:color-mix(in srgb, var(--muted) 12%, transparent);color:var(--muted);"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><path d="M9 6h6a3 3 0 0 1 0 9h-6"/></svg></span>
            Recent batches
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Batch</th><th>Type</th><th>File</th><th class="text-end">Rows</th><th class="text-end">OK</th><th class="text-end">Failed</th><th>By</th><th>When</th></tr></thead>
                <tbody>
                    @forelse($batches as $batch)
                        <tr>
                            <td class="small fw-semibold">{{ $batch->batch_number }}</td>
                            <td><span class="pill {{ $batch->import_type === 'PRODUCT_IMPORT' ? 'kind-products' : 'kind-stock' }}">{{ str_replace(['PRODUCT_IMPORT', 'BULK_STOCK'], ['Products', 'Stock'], $batch->import_type) }}</span></td>
                            <td class="small text-truncate" style="max-width:140px;">{{ $batch->file_name }}</td>
                            <td class="text-end small">{{ $batch->total_rows }}</td>
                            <td class="text-end small text-success">{{ $batch->successful_rows }}</td>
                            <td class="text-end small {{ $batch->failed_rows > 0 ? 'text-danger' : '' }}">{{ $batch->failed_rows }}</td>
                            <td class="small text-muted">{{ $batch->creator?->name }}</td>
                            <td class="small text-muted">{{ $batch->created_at->format('d M H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">
                            <div class="empty-state flex-column">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5"/><path d="M12 13v8"/></svg>
                                <span>No imports have run yet — analyze a file above to see a dry-run first.</span>
                            </div>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const KIND_HELP = {
        products: 'One product per row: <code>sku, barcode, name, category, brand, cost_price, selling_price, tax_rate, reorder_level, quantity</code> — existing SKUs are updated; quantity records a bulk-import stock movement.',
        stock: '<code>sku, quantity</code> rows. “Add stock” records additive BULK_IMPORT movements; “Set to physical count” recalculates so stock equals the scanned count via ADJUSTMENT movements.',
    };
    let kind = 'products';
    let mode = 'add';

    const file = document.getElementById('imp-file');
    const analyze = document.getElementById('imp-analyze');
    const start = document.getElementById('imp-start');
    const form = document.getElementById('imp-form');
    const actionProducts = '{{ route('imports.products') }}';
    const actionStock = '{{ route('imports.stock') }}';

    function setKind(k, m) {
        kind = k; mode = m;
        document.getElementById('imp-mode').value = m;
        document.getElementById('kind-help').innerHTML = KIND_HELP[k];
        document.getElementById('template-link').href =
            '{{ route('imports.template') }}' + (k === 'products' ? '?kind=products' : '?kind=stock');
        document.getElementById('imp-preview').innerHTML = '';
        document.getElementById('imp-errors').classList.add('d-none');
    }
    document.getElementById('k-products').addEventListener('change', () => setKind('products', 'add'));
    document.getElementById('k-add').addEventListener('change', () => setKind('stock', 'add'));
    document.getElementById('k-set').addEventListener('change', () => setKind('stock', 'set'));

    file.addEventListener('change', () => { analyze.disabled = !file.files.length; start.disabled = !file.files.length; showFileName(); });

    // Belt & braces: never submit without a file; warn on oversized files
    form.addEventListener('submit', (e) => {
        if (!file.files.length) { e.preventDefault(); flashWarn('Choose a file first.', 'warn'); return; }
        if (file.files[0].size > 5 * 1024 * 1024) {
            e.preventDefault();
            flashWarn('That file is over the 5 MB limit — split it or remove empty rows.', 'warn');
        }
    });

    // Drag & drop on the drop zone
    const dz = document.getElementById('imp-drop');
    ['dragover', 'dragenter'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add('dragover'); }));
    ['dragleave', 'drop'].forEach((ev) => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove('dragover'); }));
    dz.addEventListener('drop', (e) => {
        if (e.dataTransfer.files.length) {
            file.files = e.dataTransfer.files;
            file.dispatchEvent(new Event('change'));
        }
    });

    function showFileName() {
        const txt = document.getElementById('dz-text');
        if (file.files.length) {
            const f = file.files[0];
            const kb = f.size > 1024 * 1024 ? (f.size / 1024 / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
            txt.innerHTML = `<span class="t" style="color:var(--accent);">${escapeHtml(f.name)}</span><span class="d">${kb} · pick a different file anytime by dropping over this panel</span>`;
            txt.classList.add('chosen');
        } else {
            txt.innerHTML = '<span class="t">Drop a CSV or XLSX file here, or click to browse</span><span class="d">max 5 MB · columns follow the template for the selected type</span>';
            txt.classList.remove('chosen');
        }
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    analyze.addEventListener('click', async function () {
        if (!file.files.length) return;
        analyze.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        const body = new FormData();
        body.append('file', file.files[0]);
        body.append('kind', kind);
        try {
            const res = await fetch('{{ route('imports.preview') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body });
            const s = await res.json();
            document.getElementById('imp-preview').innerHTML = `
                <div class="row g-2 small">
                    <div class="col"><div class="detail-row"><span class="label">Rows</span><span class="value">${s.total}</span></div></div>
                    <div class="col"><div class="detail-row"><span class="label">Valid</span><span class="value">${s.valid}</span></div></div>
                    <div class="col"><div class="detail-row"><span class="label">${kind === 'products' ? 'New' : 'Applied'}</span><span class="value">${s.new}</span></div></div>
                    <div class="col"><div class="detail-row"><span class="label">Updates</span><span class="value">${s.updates}</span></div></div>
                    <div class="col"><div class="detail-row"><span class="label">Units in</span><span class="value">+${s.units_in}</span></div></div>
                </div>`;
            if (s.error_count > 0) {
                document.getElementById('imp-errors').classList.remove('d-none');
                document.getElementById('imp-error-list').innerHTML = s.errors.map(e => `<li>${e}</li>`).join('');
                if (s.error_count > s.errors.length) {
                    document.getElementById('imp-error-list').insertAdjacentHTML('beforeend', `<li>…and ${s.error_count - s.errors.length} more</li>`);
                }
            } else {
                document.getElementById('imp-errors').classList.add('d-none');
            }
        } catch (e) {
            document.getElementById('imp-preview').innerHTML = '<div class="text-danger small">Could not analyze the file.</div>';
        } finally {
            analyze.textContent = 'Analyze';
        }
    });

    form.action = actionProducts;
    document.querySelectorAll('input[name="imp-kind"]').forEach((el) => el.addEventListener('change', () => {
        form.action = el.value === 'products' ? actionProducts : actionStock;
    }));
})();
</script>
@endpush
