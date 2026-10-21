@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Staff &amp; access</h5><small class="text-muted ms-1">{{ $users->count() }} account(s)</small>
@endsection

@section('page_actions')
<a href="{{ route('users.create') }}" class="btn btn-sm btn-accent">+ New staff account</a>
@endsection

@section('content')
<div class="card feature-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th style="width:250px;"></th></tr></thead>
            <tbody>
            @foreach($users as $user)
                @php($roleKey = $user->roles->first()?->name ?? 'unassigned')
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-chip" style="width:30px;height:30px;flex-basis:30px;background:color-mix(in srgb, #0284C7 14%, transparent);color:#0284C7;font-weight:700;">{{ mb_strtoupper(substr($user->name, 0, 1)) }}</span>
                            <span class="fw-semibold {{ $user->is_active ? '' : 'text-decoration-line-through text-muted' }}">{{ $user->name }} @if($user->id === auth()->id())<span class="text-muted small">(you)</span>@endif</span>
                            @if($user->username)<span class="pill">{{ $user->username }}</span>@endif
                        </div>
                    </td>
                    <td class="text-muted small">{{ $user->email }}</td>
                    <td><span class="pill {{ $roleKey === 'superadmin' ? 'ok' : '' }}">{{ $user->role_label() }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('users.toggle', $user) }}">
                            @csrf
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" {{ $user->is_active ? 'checked' : '' }} onchange="this.form.submit()"
                                       {{ $user->id === auth()->id() ? 'disabled title="You cannot disable your own account."' : '' }}>
                            </div>
                        </form>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('users.permissions', $user) }}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                            Grants</a>
                        <a class="btn btn-sm btn-outline-accent" href="{{ route('users.edit', $user) }}">Manage</a>
                        @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('Delete {{ e($user->name) }}?')">
                                @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
