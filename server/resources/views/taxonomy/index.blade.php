@extends('layouts.app')

@section('page_title', ucfirst($tab).'s — catalog setup')

@section('content')
<h1 class="h3 mb-3">Catalog setup</h1>

<ul class="nav nav-tabs mb-3">
    @foreach(['category' => 'Categories', 'brand' => 'Brands', 'supplier' => 'Suppliers'] as $type => $label)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $type ? 'active' : '' }}" href="{{ route('taxonomy.index', ['tab' => $type]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="card border-0 shadow-sm col-lg-7 mb-3">
    <form method="POST" action="{{ route('taxonomy.store', $tab) }}" class="card-body gs-2 row g-2 align-items-end">
        @csrf
        <div class="col-md-4">
            <label class="form-label small">Name *</label>
            <input name="name" required class="form-control">
        </div>
        @if($tab === 'supplier')
            <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone"></div>
            <div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="Email"></div>
            <div class="col-md-3"><input name="address" class="form-control" placeholder="Address"></div>
        @else
            <div class="col-md-8">
                <input name="description" class="form-control" placeholder="Description (optional)">
            </div>
        @endif
        <div class="col-md-12 col-lg-4">
            <button class="btn btn-primary w-100">Add {{ $tab }}</button>
        </div>
    </form>
</div>

<div class="card border-0 shadow-sm col-lg-7">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr>
            <th>Name</th>
            @if($tab === 'supplier')<th>Phone</th><th>Email</th>@else<th class="text-end">Products</th>@endif
            <th></th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>
                    <form method="POST" action="{{ route('taxonomy.update', [$tab, $row->id]) }}" class="d-flex gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $row->name }}" class="form-control form-control-sm">
                        @if($tab === 'supplier')
                            <input name="phone" value="{{ $row->phone }}" class="form-control form-control-sm" style="max-width:9rem" placeholder="phone">
                            <input name="email" type="email" value="{{ $row->email }}" class="form-control form-control-sm" style="max-width:11rem" placeholder="email">
                        @else
                            <input name="description" value="{{ $row->description }}" class="form-control form-control-sm" placeholder="description">
                        @endif
                        <button class="btn btn-sm btn-outline-primary">Save</button>
                    </form>
                </td>
                @if($tab === 'supplier')
                    <td class="text-muted">{{ $row->phone }}</td>
                    <td class="text-muted">{{ $row->email }}</td>
                @else
                    <td class="text-end text-muted">{{ $row->products_count }}</td>
                @endif
                <td class="text-end">
                    <form method="POST" action="{{ route('taxonomy.destroy', [$tab, $row->id]) }}" onsubmit="return confirm('Delete {{ e($row->name) }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted py-4">Nothing here yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
