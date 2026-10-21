@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Refunds &amp; Exchanges</h5>
@endsection

@section('page_actions')
<div class="d-flex gap-1" role="group">
    <input type="radio" class="btn-check" name="refund-mode" id="rmode-new" value="new" checked>
    <label class="btn btn-sm btn-outline-accent" for="rmode-new"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg> New</label>
    <input type="radio" class="btn-check" name="refund-mode" id="rmode-history" value="history">
    <label class="btn btn-sm btn-outline-accent" for="rmode-history"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg> History</label>
</div>
@endsection

@section('content')
{{-- ============ NEW: completed sale picker ============ --}}
<div id="refund-new" style="max-width:1000px;">
    @if($refunds->contains('status', 'PENDING') && $canApprove)
        <div class="card feature-card mb-3">
            <div class="card-header small fw-bold">Pending refund requests — your approval returns stock</div>
            <div class="card-body p-0">
                @foreach($refunds->where('status', 'PENDING') as $refund)
                    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                        <span class="flex-grow-1 small">
                            <b>{{ $refund->refund_number }}</b> — {{ money($refund->amount) }} ({{ $refund->originalSale?->receipt_number }})
                            <span class="text-muted">· by {{ $refund->cashier?->name }}</span>
                        </span>
                        <form method="POST" action="{{ route('refunds.approve', $refund) }}">
                            @csrf
                            <button class="btn btn-sm btn-accent">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('refunds.reject', $refund) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card feature-card">
        <div class="card-header small fw-bold">Completed sales</div>
        <div class="card-body p-0">
            @forelse($sales as $sale)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom {{ $loop->last ? '' : 'border-bottom' }}" style="border-color:var(--hairline);">
                    <span class="p-tile">🧾</span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-semibold">{{ $sale->receipt_number }} · {{ money($sale->total) }}</div>
                        <div class="text-muted" style="font-size:.72rem;">{{ $sale->created_at->format('Y-m-d H:i') }}</div>
                    </div>
                    @if($canRefund)
                        <a class="btn btn-sm btn-outline-accent" href="{{ route('refunds.flow', ['sale' => $sale->id, 'mode' => 'refund']) }}">Refund</a>
                    @endif
                    @if($canExchange)
                        <a class="btn btn-sm btn-accent" href="{{ route('refunds.flow', ['sale' => $sale->id, 'mode' => 'exchange']) }}">Exchange</a>
                    @endif
                </div>
            @empty
                <div class="text-center text-muted py-4 small">No sales yet.<br>Complete a sale before refunding or exchanging.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- ============ HISTORY: exchanges then refunds ============ --}}
<div id="refund-history" class="d-none" style="max-width:1000px;">
    <div class="card feature-card mb-3">
        <div class="card-header small fw-bold">Exchanges</div>
        <div class="card-body p-0">
            @forelse($exchanges as $ex)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                    <span class="p-tile">🔄</span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-semibold">{{ $ex->exchange_number }} — replacement {{ $ex->replacementSale?->receipt_number }}</div>
                        <div class="text-muted" style="font-size:.72rem;">Original {{ $ex->originalSale?->receipt_number }} · {{ $ex->created_at->format('Y-m-d H:i') }}</div>
                    </div>
                    <span class="pill {{ $ex->difference > 0 ? 'ok' : 'danger' }}">
                        {{ $ex->difference > 0 ? 'Paid'.': '.money($ex->difference) : 'Refunded: '.money(abs((float) $ex->difference)) }}
                    </span>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('receipts.show', $ex->replacementSale) }}" title="View Receipt">🧾</a>
                </div>
            @empty
                <div class="text-center text-muted py-4 small">No exchanges yet.</div>
            @endforelse
        </div>
    </div>

    <div class="card feature-card mb-3">
        <div class="card-header small fw-bold">Refunds</div>
        <div class="card-body p-0">
            @forelse($refunds->where('status', '!=', 'PENDING') as $refund)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                    <span class="p-tile">↩️</span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-semibold">{{ $refund->refund_number }} — {{ money($refund->amount) }} ({{ \Illuminate\Support\Str::title(str_replace('_', ' ', $refund->refund_method)) }})</div>
                        <div class="text-muted" style="font-size:.72rem;">Sale {{ $refund->originalSale?->receipt_number }} · {{ $refund->created_at->format('Y-m-d H:i') }}@if($refund->reason) · {{ $refund->reason }}@endif</div>
                    </div>
                    <span class="pill {{ $refund->status === 'APPROVED' ? 'ok' : ($refund->status === 'REJECTED' ? 'off' : '') }}">{{ $refund->status }}</span>
                </div>
            @empty
                <div class="text-center text-muted py-4 small">No refunds yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('rmode-new').addEventListener('change', () => {
    document.getElementById('refund-new').classList.remove('d-none');
    document.getElementById('refund-history').classList.add('d-none');
});
document.getElementById('rmode-history').addEventListener('change', () => {
    document.getElementById('refund-new').classList.add('d-none');
    document.getElementById('refund-history').classList.remove('d-none');
});
</script>
@endpush
