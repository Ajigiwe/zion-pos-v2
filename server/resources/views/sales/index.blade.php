@extends('layouts.app')

@section('page_title', 'Sales history')

@section('content')
<h1 class="h3 mb-3">Sales history</h1>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Receipt no. / amount</label>
            <input name="q" class="form-control" value="{{ $filters['q'] ?? '' }}">
        </div>
        <div class="col-md-3">
            <label class="form-label small">Month</label>
            <input type="month" name="month" class="form-control" value="{{ $filters['month'] ?? '' }}">
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary">Filter</button></div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Receipt</th><th>Cashier</th><th>Items</th><th>Payments</th><th>Source</th><th>Status</th><th class="text-end">Total</th><th></th></tr></thead>
            <tbody>
            @forelse($sales as $sale)
                <tr>
                    <td class="fw-semibold">{{ $sale->receipt_number }}</td>
                    <td>{{ $sale->cashier?->name ?? '—' }}</td>
                    <td>{{ $sale->items->count() }}</td>
                    <td class="small text-muted">{{ implode(' + ', $sale->payments->map(fn ($p) => str_replace('_', ' ', $p->payment_method))->all()) }}</td>
                    <td><span class="badge {{ $sale->source === 'web' ? 'text-bg-secondary' : 'text-bg-info' }}">{{ $sale->source }}</span></td>
                    <td>
                        @if($sale->needs_review)<span class="badge text-bg-danger">review</span>@endif
                        @if($sale->refunds()->where('status', 'APPROVED')->exists())<span class="badge text-bg-warning">refunded</span>@endif
                    </td>
                    <td class="text-end fw-bold">{{ money($sale->total) }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('receipts.show', $sale) }}">Receipt</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No sales yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $sales->links() }}</div>
</div>
@endsection
