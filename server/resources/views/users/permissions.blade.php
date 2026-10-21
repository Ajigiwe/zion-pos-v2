@extends('layouts.app')

@section('page_title', 'Permissions — ' . $user->name)

@section('page_actions')
<a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">← Staff</a>
@endsection

@section('content')
<div class="mx-auto" style="max-width:860px;">
    <div class="card feature-card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <div>
                        <div class="fw-bold">{{ $user->name }}</div>
                        <div class="text-muted small">{{ $user->email }}</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($user->roles as $role)
                        <span class="pill {{ $role->name === 'superadmin' ? 'ok' : '' }}">{{ $role->name === 'superadmin' ? 'Superadmin' : ($role->name === 'admin' ? 'Owner' : ucwords(str_replace('_', ' ', $role->name))) }}</span>
                    @endforeach
                </div>
            </div>
            <div class="text-muted small mt-2">
                These switches are <strong>extra grants on top of the role</strong>. Permissions already included in the
                role show as locked (<em>via role</em>) — they stay unless the role itself changes. Unchecking a custom
                grant removes only that personal grant.
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('users.permissions.update', $user) }}">
        @csrf @method('PUT')
        @php
            $groups = [
                'Dashboard' => ['dashboard.view'],
                'Products & catalog' => ['products.view', 'products.manage'],
                'Inventory' => ['inventory.view', 'inventory.adjust'],
                'Till & sales' => ['pos.view', 'pos.use'],
                'Refunds & exchanges' => ['refunds.view', 'refunds.request', 'refunds.approve', 'exchanges.process'],
                'Imports' => ['imports.view', 'imports.run'],
                'Reports & audit' => ['reports.view', 'audit.view'],
                'Administration — superadmin territory' => ['users.manage', 'settings.manage'],
            ];
        @endphp
        @foreach($groups as $groupLabel => $groupPerms)
            <div class="card feature-card mb-3">
                <div class="card-header small fw-bold">{{ $groupLabel }}</div>
                <div class="card-body vstack gap-2 py-2">
                    @foreach($groupPerms as $perm)
                        @continue(! in_array($perm, $names))
                        @php($isRole = in_array($perm, $rolePerms))
                        @php($isDirect = in_array($perm, $direct))
                        <label class="switch-row {{ $isRole ? 'perm-inherited' : '' }}" @if($isRole) title="Included via role — cannot be toggled here" @endif>
                            <span class="switch-info">
                                <span class="switch-title">{{ $info[$perm][0] }}</span>
                                <span class="switch-desc">{{ $info[$perm][1] }}</span>
                            </span>
                            @if($isRole)
                                <span class="via-role-chip">via role</span>
                                <input type="checkbox" checked disabled>
                            @else
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" name="grant[]" value="{{ $perm }}" {{ $isDirect ? 'checked' : '' }}>
                                </div>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="d-flex justify-content-end mb-2">
            <button class="btn btn-accent px-4">Save grants</button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .perm-inherited { opacity: .62; cursor: not-allowed; }
    .perm-inherited:hover { border-color: var(--soft); }
    .switch-info { display: flex; flex-direction: column; gap: .05rem; min-width: 0; flex-grow: 1; }
    .switch-title { font-size: .86rem; font-weight: 600; line-height: 1.25; }
    .switch-desc { font-size: .74rem; color: var(--muted); line-height: 1.25; }
    .via-role-chip {
        font-size: .68rem; font-weight: 700; letter-spacing: .04em;
        color: var(--muted); border: 1px solid var(--hairline); background: var(--chip);
        border-radius: 999px; padding: .1rem .5rem; flex-shrink: 0;
    }
</style>
@endpush
