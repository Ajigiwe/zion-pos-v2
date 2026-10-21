@extends('layouts.app')

@section('page_title', $user->exists ? 'Edit staff — '.$user->name : 'New staff account')

@section('page_actions')
<a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">← Staff</a>
@endsection

@section('content')
<div style="max-width:640px;">
    <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card feature-card">
        @csrf
        @if($user->exists) @method('PUT') @endif
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label small">Full name *</label>
                <input name="name" required class="form-control" value="{{ old('name', $user->name) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small">Username (sign-in) *</label>
                <input name="username" required class="form-control" minlength="3" maxlength="60"
                       pattern="[A-Za-z0-9._\-]+" autocomplete="off" autocapitalize="none" spellcheck="false"
                       value="{{ old('username', $user->username) }}">
                <div class="form-text">Letters, numbers, dots, dashes, underscores — used at the login screen.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Email *</label>
                <input name="email" type="email" required class="form-control" value="{{ old('email', $user->email) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small role-label">Role *</label>
                <select name="role" class="form-select" required>
                    @php($currentRole = $user->exists ? $user->roles->first()?->name : 'cashier')
                    @foreach($roles as $role => $label)
                        <option value="{{ $role }}" {{ old('role', $currentRole) === $role ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-text">Superadmin: developer · Owner: full business control · Manager: day-to-day sales, stock &amp; reports · Cashier: till only</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Password {{ $user->exists ? '(leave blank to keep)' : '*' }}</label>
                <input name="password" type="password" class="form-control" minlength="6" {{ $user->exists ? '' : 'required' }}>
                <div class="form-text">At least 6 characters</div>
            </div>
            @if($user->exists)
                <div class="col-md-6">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label small" for="is_active">Account active</label>
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button class="btn btn-accent px-4">Save</button>
        </div>
    </form>
</div>
@endsection
