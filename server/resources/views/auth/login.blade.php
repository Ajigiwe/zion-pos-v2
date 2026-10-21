@extends('layouts.guest')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0 feature-card" style="border-radius:16px;">
            <div class="card-body p-4">
                <div class="text-center mb-3">
                    <span class="icon-chip mx-auto mb-2" style="width:46px;height:46px;flex-basis:46px;background:color-mix(in srgb, var(--accent) 15%, transparent);color:var(--accent);">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>
                    </span>
                    <h4 class="mt-1 mb-0">{{ store_info()['name'] }}</h4>
                    <p class="text-muted small mb-0">Point of Sale</p>
                </div>
                @if($errors->any())
                    <div class="alert alert-danger py-2 small">{{ $errors->first('username') ?: $errors->first('password') }}</div>
                @endif
                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small" for="username">Username</label>
                        <input id="username" type="text" name="username" value="{{ old('username') }}"
                               class="form-control" required autofocus autocomplete="username"
                               autocapitalize="none" autocorrect="off" spellcheck="false">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="password">Password</label>
                        <div class="input-group">
                            <input id="password" type="password" name="password"
                                   class="form-control" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary" type="button" id="pw-eye" tabindex="-1" aria-label="Show password">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-accent w-100 mt-2" style="height:42px;font-weight:700;">Sign in</button>
                </form>
                <div class="text-center mt-3">
                    <span class="status-pill online"><span class="dot"></span>ONLINE HOST</span>
                </div>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">Online-first POS · works offline at the till</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('pw-eye');
    if (!btn) return;
    var pw = document.getElementById('password');
    var show = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    var hide = btn.innerHTML;
    btn.addEventListener('click', function () {
        pw.type = pw.type === 'password' ? 'text' : 'password';
        btn.innerHTML = pw.type === 'password' ? hide : show;
    });
})();
</script>
@endpush
