@extends('layouts.app')

@section('page_title', 'Reports')

@section('page_actions')
<div class="d-flex gap-2">
    <div class="d-flex gap-1" role="group">
        <input type="radio" class="btn-check" name="rep-range" id="rg-today" value="today" {{ $key === 'today' ? 'checked' : '' }}>
        <label class="btn btn-sm btn-outline-accent" for="rg-today">Today</label>
        <input type="radio" class="btn-check" name="rep-range" id="rg-7" value="7" {{ $key === '7' ? 'checked' : '' }}>
        <label class="btn btn-sm btn-outline-accent" for="rg-7">Last 7 days</label>
        <input type="radio" class="btn-check" name="rep-range" id="rg-30" value="30" {{ $key === '30' ? 'checked' : '' }}>
        <label class="btn btn-sm btn-outline-accent" for="rg-30">Last 30 days</label>
    </div>
    @if($salesCount > 0 || $refundsCount > 0)
        <a class="btn btn-sm btn-outline-accent" href="{{ route('reports.index', ['range' => $key, 'export' => 'csv']) }}">Export CSV</a>
    @endif
</div>
@endsection

@section('content')
<div class="mx-auto" style="max-width:1100px;">
@if($salesCount === 0 && $refundsCount === 0)
    <div class="card feature-card p-5 text-center">
        <div class="h4 mb-1">📊</div>
        <div class="fw-bold">No sales or refunds in this period.</div>
        <div class="text-muted small">Try another range.</div>
    </div>
@else
    {{-- summary cards --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card feature-card stat-card p-3 h-100">
                <div class="stat-label">Total sales</div>
                <div class="stat-value">{{ money($gross) }}</div>
                <div class="text-muted small">{{ $salesCount }} sale(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card feature-card stat-card p-3 h-100">
                <div class="stat-label">Discounts</div>
                <div class="stat-value">{{ money($discounts) }}</div>
                <div class="text-muted small">given at the till</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card feature-card stat-card p-3 h-100">
                <div class="stat-label">Refunds</div>
                <div class="stat-value {{ $refundTotal > 0.001 ? 'text-danger' : '' }}">{{ money($refundTotal) }}</div>
                <div class="text-muted small">{{ $refundsCount }} refund(s)</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card feature-card stat-card emphasized p-3 h-100">
                <div class="stat-label text-accent">Net sales</div>
                <div class="stat-value">{{ money($net) }}</div>
                <div class="text-muted small">sales − refunds</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Payments received --}}
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Payments received · {{ $label }}</div>
                <div class="card-body p-0">
                    @forelse($paidByMethod as $method => $m)
                        @if($m['amount'] != 0 || $m['count'] != 0)
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                                <span class="p-tile">{{ $method === 'cash' ? '💵' : '📱' }}</span>
                                <div class="flex-grow-1 small fw-semibold">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $method)) }}</div>
                                <div class="small">{{ money($m['amount']) }} <span class="text-muted">· {{ $m['count'] }}</span></div>
                            </div>
                        @endif
                    @empty
                        <div class="text-muted small text-center py-4">No payments recorded in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Refunds paid out --}}
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Refunds paid out</div>
                <div class="card-body p-0">
                    @forelse($refundedByMethod as $method => $m)
                        @if($m['amount'] != 0 || $m['count'] != 0)
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" style="border-color:var(--hairline);">
                                <span class="p-tile" style="color:#DC2626;background:rgba(220,38,38,.1);">↩</span>
                                <div class="flex-grow-1 small fw-semibold">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $method)) }}</div>
                                <div class="small text-danger">{{ money($m['amount']) }} <span class="text-muted">· {{ $m['count'] }}</span></div>
                            </div>
                        @endif
                    @empty
                        <div class="text-muted small text-center py-4">No refunds recorded in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- tables --}}
    @php($emptyMap = [
        'byCashier' => ['Sales by cashier', ['Cashier','Sell'], 'No sales in this period.'],
    ])
    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Sales by cashier</div>
                <div class="card-body p-0 table-responsive">
                    @forelse($byCashier as $c)
                        <table class="table table-sm mb-0"><thead class="d-none"></thead>
                        <tbody>
                        <tr><td>{{ $c['name'] }}</td><td class="text-end">{{ $c['count'] }}</td><td class="text-end">{{ money($c['total']) }}</td></tr>
                        </tbody></table>
                    @empty
                        <div class="text-muted small text-center py-4">No sales in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Sales by product</div>
                <div class="card-body p-0">
                    @forelse($topProducts as $p)
                        <table class="table table-sm mb-0"><tbody>
                        <tr><td class="text-muted">{{ $p['sku'] }}</td><td>{{ $p['name'] }}</td><td class="text-end">{{ rtrim(rtrim(number_format($p['qty'], 3), '0'), '.') }}</td><td class="text-end">{{ money($p['revenue']) }}</td></tr>
                        </tbody></table>
                    @empty
                        <div class="text-muted small text-center py-4">No products sold in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Refunds by cashier</div>
                <div class="card-body p-0">
                    @forelse($refundsByCashier as $c)
                        <table class="table table-sm mb-0"><tbody>
                        <tr><td>{{ $c['name'] }}</td><td class="text-end">{{ $c['count'] }}</td><td class="text-end">{{ money($c['total']) }}</td></tr>
                        </tbody></table>
                    @empty
                        <div class="text-muted small text-center py-4">No refunds in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Refunds by product</div>
                <div class="card-body p-0">
                    @forelse($refundedProducts as $p)
                        <table class="table table-sm mb-0"><tbody>
                        <tr><td>{{ $p['name'] }}</td><td class="text-end">{{ rtrim(rtrim(number_format($p['qty'], 3), '0'), '.') }}</td><td class="text-end">{{ money($p['amount']) }}</td></tr>
                        </tbody></table>
                    @empty
                        <div class="text-muted small text-center py-4">No refunded products in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card feature-card h-100">
                <div class="card-header small fw-bold">Refund reasons</div>
                <div class="card-body p-0">
                    @forelse($reasons as $r)
                        <table class="table table-sm mb-0"><tbody>
                        <tr><td>{{ $r['reason'] }}</td><td class="text-end">{{ $r['count'] }}</td><td class="text-end">{{ money($r['amount']) }}</td></tr>
                        </tbody></table>
                    @empty
                        <div class="text-muted small text-center py-4">No refunds in this period.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('input[name="rep-range"]').forEach(function (el) {
    el.addEventListener('change', function () {
        window.location = '{{ route('reports.index') }}?range=' + el.value;
    });
});
</script>
@endpush
