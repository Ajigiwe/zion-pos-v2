@extends('layouts.app')

@section('page_title', 'Receipt — '.$r['number'])

@section('page_actions')
<div class="no-print d-flex gap-2">
    <button onclick="window.print()" class="btn btn-sm btn-accent">Print</button>
    <a href="{{ route('receipts.pdf', $sale) }}" class="btn btn-sm btn-outline-secondary">PDF copy</a>
</div>
@endsection

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card feature-card" id="receipt-wrap">
            <div class="card-body" style="background:#F1F5F9;">
                @include('receipts._thermal', ['r' => $r, 'store' => $store])
            </div>
        </div>

        @if(! empty($r['refunds']))
            <div class="card feature-card mt-3 no-print">
                <div class="card-header small fw-bold">Approved refunds on this sale</div>
                <ul class="list-group list-group-flush">
                    @foreach($r['refunds'] as $rf)
                        <li class="list-group-item small">
                            {{ $rf['number'] }} — {{ money($rf['amount']) }} @if($rf['reason'])<span class="text-muted">· {{ $rf['reason'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
