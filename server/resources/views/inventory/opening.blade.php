@extends('layouts.app')

@section('page_title', 'Opening stock')

@section('page_actions')
<a href="{{ route('inventory.index') }}" class="btn btn-sm btn-outline-secondary">← Back to ledger</a>
@endsection

@section('content')
<div style="max-width:680px;">
    <div class="text-muted small mb-3">
        Enter the starting quantity for each product. Only products with zero stock are listed; you can always add stock later.
    </div>

    @if($products->isEmpty())
        <div class="card feature-card p-5 text-center">
            <div class="text-success h4 mb-1">✓</div>
            <div class="fw-bold">No products need opening stock</div>
            <div class="text-muted small">Every product already has stock on the ledger.</div>
        </div>
    @else
        <form method="POST" action="{{ route('inventory.opening.record') }}">
            @csrf
            <div class="card feature-card">
                <div class="card-body p-0">
                    @foreach($products as $product)
                        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                            <span class="p-tile">📦</span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="small fw-semibold">{{ $product->name }}</div>
                                <div class="text-muted" style="font-size:.72rem;">{{ $product->sku }}</div>
                            </div>
                            <div class="input-group input-group-sm" style="max-width:140px;">
                                <input class="form-control text-end" type="number" min="0" step="0.5" name="quantities[{{ $product->id }}]" placeholder="0">
                                <span class="input-group-text">units</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted small">{{ $products->count() }} product(s) with zero stock</span>
                    <button class="btn btn-accent">Record opening stock</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
