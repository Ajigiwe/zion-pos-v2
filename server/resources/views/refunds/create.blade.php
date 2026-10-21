@extends('layouts.app')

@section('page_title', 'New refund')

@section('content')
<h1 class="h3 mb-3">Refund a sale</h1>

<form method="GET" action="{{ route('refunds.find') }}" class="row g-2 mb-3 col-lg-5">
    <div class="col">
        <input name="receipt" class="form-control form-control-lg" placeholder="Receipt number (e.g. SALE-000001)"
               value="{{ request()->input('receipt') }}" autofocus>
    </div>
    <div class="col-auto"><button class="btn btn-primary">Find</button></div>
</form>

@isset($sale)
    <div class="card border-0 shadow-sm col-lg-8">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="fw-bold mb-0">{{ $sale->receipt_number }} <span class="text-muted small">({{ $sale->created_at->format('d M Y') }})</span></h6>
                    <div class="small text-muted">Total {{ money($sale->total) }} · Payments: {{ $sale->payments->map(fn ($p) => str_replace('_', ' ', $p->payment_method).' '.money($p->amount))->implode(', ') }}</div>
                </div>
                <span class="badge {{ $sale->needs_review ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ $sale->needs_review ? 'needs review' : 'completed' }}</span>
            </div>
        </div>
        <form method="POST" action="{{ route('refunds.store') }}">
            @csrf
            <input type="hidden" name="original_sale_id" value="{{ $sale->id }}">
            <div class="card-body pt-0">
                <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Item</th><th class="text-end">Sold</th><th class="text-end">Already refunded</th><th style="width:110px">Return qty</th></tr></thead>
                    <tbody>
                        @foreach($sale->items as $item)
                            @php
                                $already = $sale->refunds->flatMap->items->where('sale_item_id', $item->id)->sum('quantity');
                                $remaining = (float) $item->quantity - (float) $already;
                            @endphp
                            <tr>
                                <td>{{ $item->product?->name }} <span class="text-muted small">{{ $item->product?->sku }}</span></td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end text-muted">{{ $already }}</td>
                                <td>
                                    @if($remaining <= 0)
                                        <span class="text-muted small">fully refunded</span>
                                    @else
                                        <input type="number" name="lines[{{ $item->id }}][quantity]" step="0.001"
                                               min="0" max="{{ $remaining }}" value=""
                                               class="form-control form-control-sm" placeholder="{{ $remaining }}">
                                        <input type="hidden" name="lines[{{ $item->id }}][sale_item_id]" value="{{ $item->id }}">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>

                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small">Refund method</label>
                        <select name="method" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile money</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank transfer</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">Reason</label>
                        <input name="reason" class="form-control" placeholder="defective, customer changed mind…">
                    </div>
                </div>
                @unless(auth()->user()->can('refunds.approve'))
                    <div class="alert alert-info small mt-3 mb-0">
                        Your refund will be <strong>sent for admin approval</strong>; stock returns only after it is approved.
                    </div>
                @endunless
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button class="btn btn-success px-4">Submit refund</button>
            </div>
        </form>
    </div>
@endisset
@endsection
