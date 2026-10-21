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
<div class="card feature-card list-group list-group-flush low-stock-list">
    @forelse($lowStock as $product)
        @php($rule = $product->reorder_level > 0 ? $product->reorder_level : $threshold)
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
    @empty
        <div class="list-group-item px-3 py-4 text-center low-healthy">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent);"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            <div class="small fw-semibold mt-1">All stock levels are healthy.</div>
        </div>
    @endforelse
</div>
@endsection
