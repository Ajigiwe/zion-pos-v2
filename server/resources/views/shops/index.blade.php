@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Shops</h5><small class="text-muted ms-1">Create trading locations, keep staff assignments and profiles.</small>
@endsection

@section('content')
<div class="row g-3">

    {{-- create / edit --}}
    <div class="col-12 col-xl-4">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9 12 3l9 6"/><path d="M5 11v10h14V11"/><path d="M9 21v-6h6v6"/></svg></span>
                <div>
                    <div class="fw-bold small">New shop</div>
                    <div class="text-muted" style="font-size:.75rem;">Each shop has its own products, stock and sales history — SKU/barcode codes may repeat across shops, never within one.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('shops.store') }}" id="shop-form">
                @csrf
                <div class="card-body">
                    <label class="form-label small mb-1" style="letter-spacing:.5px;color:var(--muted);font-weight:700;">SHOP NAME</label>
                    <input class="form-control form-control-sm" name="name" required maxlength="255" value="{{ old('name', $edit?->name ?? '') }}" placeholder="e.g. Zion Barbershop">
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <label class="form-label small mb-1" style="letter-spacing:.5px;color:var(--muted);font-weight:700;">CODE (RECEIPTS)</label>
                            <input class="form-control form-control-sm text-uppercase" name="code" required maxlength="16" pattern="[A-Z0-9]{2,16}" value="{{ old('code', $edit?->code ?? '') }}" placeholder="e.g. BAR">
                            <div class="form-text" style="font-size:.7rem;">Used as the receipt prefix, e.g. BAR-SALE-000001.</div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1" style="letter-spacing:.5px;color:var(--muted);font-weight:700;">PHONE</label>
                            <input class="form-control form-control-sm" name="phone" maxlength="50" value="{{ old('phone', $edit?->phone ?? '') }}" placeholder="Store phone">
                        </div>
                    </div>
                    <label class="form-label small mb-1 mt-2" style="letter-spacing:.5px;color:var(--muted);font-weight:700;">ADDRESS</label>
                    <input class="form-control form-control-sm" name="address" maxlength="255" value="{{ old('address', $edit?->address ?? '') }}" placeholder="Street, town">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="shop-active" checked>
                        <label class="form-check-label small" for="shop-active">Active — visible in the switcher and open for trading</label>
                    </div>
                    @if($errors->any())
                        <div class="alert alert-danger small mt-3 mb-0"><ul class="mb-0 ms-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                    @endif
                </div>
                <div class="card-footer d-flex gap-2">
                    <button class="btn btn-sm btn-accent flex-grow-1" id="shop-submit">Create shop</button>
                </div>
            </form>
        </div>
    </div>

    {{-- list --}}
    <div class="col-12 col-xl-8">
        <div class="card feature-card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, #0284C7 12%, transparent);color:#0284C7;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9 12 3l9 6"/><path d="M5 11v10h14V11"/><path d="M9 21v-6h6v6"/></svg></span>
                <div class="fw-bold small flex-grow-1">All shops</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm small align-middle mb-0">
                        <thead><tr><th>Shop</th><th>Address</th><th class="text-end">Staff</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        @forelse($shops as $shop)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $shop->name }}</div>
                                    <div class="text-muted" style="font-size:.72rem;">code {{ $shop->code }}</div>
                                </td>
                                <td class="text-truncate" style="max-width:180px;">{{ $shop->address ?: '—' }}<div class="text-muted" style="font-size:.72rem;">{{ $shop->phone ?: '' }}</div></td>
                                <td class="text-end">{{ $shop->users_count }}</td>
                                <td><span class="pill {{ $shop->is_active ? 'kind-products' : 'kind-stock' }}">{{ $shop->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('shops.switch') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                                        <button class="btn btn-sm btn-outline-accent" {{ ($shop->is_active && auth()->user()->hasRole('superadmin')) ? '' : 'disabled' }}>Switch</button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#shopModal-{{ $shop->id }}">Staff</button>
                                    <form method="POST" action="{{ route('shops.toggle', $shop) }}" class="d-inline">
                                        @csrf
                                        @if($shop->is_active)
                                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Deactivate \'{{ $shop->name }}\'? Tills in this shop stop serving; data stays intact.');">Deactivate</button>
                                        @else
                                            <button class="btn btn-sm btn-outline-primary">Activate</button>
                                        @endif
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No shops yet — create the first one.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- staff assignment modals --}}
@foreach($shops as $shop)
    <div class="modal fade" id="shopModal-{{ $shop->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content feature-card">
                <div class="modal-header"><h6 class="modal-title">Staff at {{ $shop->name }}</h6></div>
                <div class="modal-body">
                    <table class="table table-sm small mb-2">
                        <thead><tr><th>Name</th><th>Role</th><th class="text-end"></th></tr></thead>
                        <tbody>
                        @forelse($members = $staff->filter(fn ($u) => $u->shops->contains('id', $shop->id)) as $member)
                            <tr>
                                <td>{{ $member->name }}</td>
                                <td class="text-muted">{{ $member->role_label() }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('shops.staff', $shop) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $member->id }}">
                                        <input type="hidden" name="action" value="detach">
                                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-2">Nobody is assigned here yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    <form method="POST" action="{{ route('shops.staff', $shop) }}" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="action" value="attach">
                        <select name="user_id" class="form-select form-select-sm">
                            @foreach($staff->filter(fn ($u) => ! $u->shops->contains('id', $shop->id)) as $fresh)
                                <option value="{{ $fresh->id }}">{{ $fresh->name }} ({{ $fresh->role_label() }})</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-accent text-nowrap">Assign</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

@endsection
