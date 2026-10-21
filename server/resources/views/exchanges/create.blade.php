@extends('layouts.app')

@section('page_title', 'New exchange')

@section('content')
<h1 class="h3 mb-3">Exchange (return ⇄ replacement)</h1>

<form method="GET" action="{{ route('exchanges.show') }}" class="row g-2 mb-3 col-lg-5">
    <div class="col">
        <input name="receipt" class="form-control form-control-lg" placeholder="Original receipt number"
               value="{{ $term }}" autofocus>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Find</button></div>
</form>

@isset($sale)
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold">{{ $sale->receipt_number }} — {{ money($sale->total) }}</h6>
            <form method="POST" action="{{ route('exchanges.store') }}" id="exchange-form">
                @csrf
                <input type="hidden" name="original_sale_id" value="{{ $sale->id }}">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-warning"><span class="badge bg-warning text-dark">1</span> Goods returned</h6>
                        <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Item</th><th class="text-end">Available</th><th style="width:90px">Qty</th></tr></thead>
                            <tbody>
                                @foreach($sale->items as $item)
                                    @php
                                        $already = $sale->refunds->flatMap->items->where('sale_item_id', $item->id)->sum('quantity');
                                        $remaining = (float) $item->quantity - (float) $already;
                                    @endphp
                                    <tr @if($remaining <= 0) class="opacity-50" @endif>
                                        <td>{{ $item->product?->name }} <span class="text-muted small">{{ $item->product?->sku }}</span></td>
                                        <td class="text-end small">{{ $remaining }}</td>
                                        <td>
                                            <input type="number" name="return_lines[{{ $item->id }}][quantity]" step="0.001"
                                                   min="0" max="{{ $remaining }}" class="form-control form-control-sm rl-qty"
                                                   placeholder="{{ $remaining }}" data-unit="{{ $item->unit_price }}" {{ $remaining <= 0 ? 'disabled' : '' }}>
                                            <input type="hidden" name="return_lines[{{ $item->id }}][sale_item_id]" value="{{ $item->id }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-success"><span class="badge bg-success">2</span> Replacement items</h6>
                        <div id="replacement-rows" class="vstack gap-2">
                            <div class="input-group input-group-sm rep-row">
                                <select name="replacement_lines[0][product_id]" class="form-select rep-pick">
                                    <option value="">Replacement product…</option>
                                    @foreach($catalog as $c)
                                        <option value="{{ $c->id }}" data-price="{{ \App\Models\Product::find($c->id)->selling_price }}">{{ $c->name }} ({{ $c->sku }})</option>
                                    @endforeach
                                </select>
                                <input name="replacement_lines[0][quantity]" type="number" step="0.001" min="0" value="" class="form-control rep-qty" style="max-width:90px">
                            </div>
                        </div>
                        <button type="button" id="add-rep" class="btn btn-sm btn-outline-secondary mt-2">+ Add another</button>
                    </div>
                </div>

                <div class="row g-3 mt-1 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Customer pays difference with</label>
                        <select name="difference_method" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile money</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank transfer</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-muted small">
                        Difference <strong id="ex-diff">—</strong> = replacement total − return value
                    </div>
                    <div class="col-md-5 d-flex justify-content-end">
                        <button class="btn btn-success px-4">Process exchange</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endisset
@endsection

@push('scripts')
<script>
(function () {
    const fmt = (n) => 'GHS ' + (Math.round(n * 100) / 100).toFixed(2);
    function recalc() {
        let ret = 0;
        document.querySelectorAll('.rl-qty').forEach((i) => {
            if (i.value && !i.disabled) ret += parseFloat(i.dataset.unit) * parseFloat(i.value);
        });
        let rep = 0;
        document.querySelectorAll('.rep-row').forEach((row) => {
            const pick = row.querySelector('.rep-pick');
            const opt = pick.selectedOptions[0];
            const q = parseFloat(row.querySelector('.rep-qty').value || 0);
            if (!opt || !opt.dataset.price || !q) return;
            rep += parseFloat(opt.dataset.price) * q;
        });
        const diff = Math.round((rep - ret) * 100) / 100;
        document.getElementById('ex-diff').textContent = diff >= 0 ? 'customer pays ' + fmt(diff) : 'cash back ' + fmt(-diff);
    }
    document.querySelectorAll('.rl-qty, .rep-qty').forEach((i) => i.addEventListener('input', recalc));
    document.querySelectorAll('.rep-pick').forEach((i) => i.addEventListener('change', recalc));

    let n = 1;
    document.getElementById('add-rep')?.addEventListener('click', () => {
        const first = document.querySelector('.rep-row');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach((i) => i.value = '');
        clone.querySelector('select').name = `replacement_lines[${n}][product_id]`;
        clone.querySelector('input').name = `replacement_lines[${n}][quantity]`;
        n++;
        document.getElementById('replacement-rows').appendChild(clone);
        recalc();
    });
})();
document.addEventListener('input', (e) => {
    if (e.target.classList?.contains('rl-qty') || e.target.classList?.contains('rep-qty') || e.target.classList?.contains('rep-pick')) {
    // recalc wires re-added rows via new listeners up top only—rebind cheaply:
        window.dispatchEvent(new Event('ex-recalc'));
    }
});
</script>
@endpush
