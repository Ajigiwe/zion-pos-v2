@extends('layouts.app')

@section('page_title', 'Audit log')

@section('content')
<h1 class="h3 mb-3">Audit log</h1>

<form method="GET" class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Action</label>
            <select name="action" class="form-select">
                <option value="">All</option>
                @foreach($actions as $a)<option value="{{ $a }}" {{ $filters['action'] ?? '' === $a ? 'selected' : '' }}>{{ $a }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label small">From</label><input type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}"></div>
        <div class="col-md-3"><label class="form-label small">To</label><input type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}"></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary">Filter</button></div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>Device</th></tr></thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small text-muted text-nowrap">{{ $log->created_at->format('d M H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><span class="badge {{ str_contains($log->action, 'DELETE') || str_contains($log->action, 'OVERSELL') || str_contains($log->action, 'REJECT') ? 'text-bg-danger' : 'text-bg-secondary' }}">{{ $log->action }}</span></td>
                        <td class="small text-muted">{{ $log->entity_type ? $log->entity_type.' #'.$log->entity_id : '—' }}</td>
                        <td class="small">{{ $log->details ?? '' }}</td>
                        <td class="small text-muted">{{ $log->device_id ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No audit entries match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
