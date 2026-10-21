<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light" data-theme-mode="{{ theme_info()['mode'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-email" content="{{ auth()->user()->email ?? '' }}">
    <meta name="authed" content="{{ auth()->check() ? '1' : '0' }}">
    <title>{{ store_info()['name'] }} — Zion POS</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        :root { --accent: {{ theme_info()['accent'] }}; }
    </style>
    @stack('styles')
</head>
<body>
<script>
    // Theme mode: light / dark / system (Flutter parity — resolve once, react to OS change).
    (function () {
        var mode = document.documentElement.dataset.themeMode || 'light';
        var media = window.matchMedia('(prefers-color-scheme: dark)');
        function apply() {
            var dark = mode === 'dark' || (mode === 'system' && media.matches);
            document.documentElement.dataset.bsTheme = dark ? 'dark' : 'light';
        }
        apply();
        media.addEventListener('change', function () { if (mode === 'system') apply(); });
    })();
</script>
<div class="either d-flex">
    <div class="sidebar-backdrop" id="sidebar-backdrop" aria-hidden="true"></div>
    <aside class="sidebar d-flex flex-column flex-shrink-0">
        <div class="brand d-flex align-items-center gap-2 px-3 py-3">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" class="text-accent"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>
            <div class="brand-text">
                <div class="brand-name text-truncate">{{ store_info()['name'] }}</div>
                <div class="brand-sub">Point of Sale</div>
            </div>
        </div>
        <nav class="nav flex-column px-2 gap-1 pb-3">
            @can('dashboard.view')
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-12h8V3h-8v6z"/></svg>
                    <span class="nav-label">Dashboard</span>
                </a>
            @endcan
            @can('products.view')
                <a href="{{ route('products.index') }}" class="nav-link d-flex align-items-center {{ request()->routeIs('products.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20 6H4V3h16v3zm1 5v10H3V11h18zM8 15h8v-2H8v2z"/></svg>
                    <span class="nav-label">Products</span>
                    @if($lowStockCount > 0)<span class="badge badge-low rounded-pill ms-auto" title="{{ $lowStockCount == 1 ? '1 product is low on stock' : $lowStockCount.' products are low on stock' }}">{{ $lowStockCount }}</span>@endif
                </a>
            @endcan
            @can('inventory.view')
                <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M3 6l9-4 9 4v12l-9 4-9-4V6zm9-2L5.7 6.5 12 9.5l6.3-3L12 4z"/></svg>
                    <span class="nav-label">Inventory</span>
                </a>
            @endcan
            @can('pos.view')
                <a href="{{ route('pos') }}" class="nav-link {{ request()->routeIs('pos', 'sales.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 3A5.48 5.48 0 0 0 12 8.5c0 3.03 2.47 5.5 5.5 5.5H18v7h2V3h-2.5zM4.5 9A3.5 3.5 0 0 0 8 12.5h1v9h2v-9h1A3.5 3.5 0 0 0 15.5 9h-11z"/></svg>
                    <span class="nav-label">Sales</span>
                </a>
            @endcan
            @can('refunds.view')
                <a href="{{ route('refunds.index') }}" class="nav-link {{ request()->routeIs('refunds.*', 'exchanges.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5V1L7 6l5 5V7a7 7 0 1 1-7 7H3a9 9 0 1 0 9-9z"/></svg>
                    <span class="nav-label">Refunds</span>
                </a>
            @endcan
            @can('imports.view')
                <a href="{{ route('imports.index') }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6v-8h12v8zm0-10H6V4h7v6h5z"/></svg>
                    <span class="nav-label">Imports</span>
                </a>
            @endcan
            @if(auth()->check() && Gate::allows('pos.view') && Gate::allows('inventory.view'))
                <a href="{{ route('customers.index') }}" class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zm2 1a6 6 0 1 1-12 0 6 6 0 0 1 12 0zM2 21c0-3 4-4 10-4s10 1 10 4v1H2v-1z"/></svg>
                    <span class="nav-label">Customers</span>
                </a>
            @endif
            @can('reports.view')
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z"/></svg>
                    <span class="nav-label">Reports</span>
                </a>
            @endcan
            @can('users.manage')
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zm2 1a6 6 0 1 1-12 0 6 6 0 0 1 12 0zM2 21c0-3 4-4 10-4s10 1 10 4v1H2v-1z"/></svg>
                    <span class="nav-label">Staff</span>
                </a>
            @endcan
            @can('settings.manage')
                <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94a7.07 7.07 0 0 0 0-1.88l2.03-1.58-2-3.46-2.39.96a7.03 7.03 0 0 0-1.62-.94L14.8 2.9h-4l-.36 3.14a7.03 7.03 0 0 0-1.62.94l-2.39-.96-2 3.46 2.03 1.58a7.07 7.07 0 0 0 0 1.88L4.43 14.5l2 3.46 2.39-.96c.5.4 1.03.72 1.62.94l.36 3.14h4l.36-3.14a7.03 7.03 0 0 0 1.62-.94l2.39.96 2-3.46-2.03-1.56zM13 15a3 3 0 1 1 0-6 3 3 0 0 1 0 6z"/></svg>
                    <span class="nav-label">Settings</span>
                </a>
            @endcan
        </nav>
        <div id="sync-indicator" class="sync-footer" title="Online">
            <span id="sync-dot" class="sync-dot sync-online" title="Online"></span>
            <span id="pending-count" class="badge text-bg-secondary d-none">0 queued</span>
        </div>
        <button type="button" id="sidebar-toggle" class="sidebar-toggle-btn" title="Collapse menu">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            <span class="nav-label">Collapse</span>
        </button>
    </aside>

    <div class="main d-flex flex-column flex-grow-1 overflow-auto">
        <header class="topbar d-flex align-items-center justify-content-between px-4 py-2 flex-shrink-0">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button type="button" class="nav-round-btn mobile-menu-btn" id="mobile-menu" title="Menu" aria-label="Open menu" aria-expanded="false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <button type="button" class="nav-round-btn" onclick="history.back()" title="Back" aria-label="Go back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <a href="{{ url('/') }}" class="nav-round-btn {{ url()->current() === url('/') ? 'is-here' : '' }}" title="Home" aria-label="Go to dashboard">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                </a>
                <div class="topbar-title text-truncate">
                    @yield('page_title')
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                @yield('page_actions')
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        {{ auth()->user()->name }} <span class="text-muted small">({{ auth()->user()->role_label() }})</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if(auth()->user()->role_key() === 'superadmin')
                            <li><a class="dropdown-item" href="{{ route('users.index') }}">Staff &amp; access</a></li>
                            <li><a class="dropdown-item" href="{{ route('settings.index') }}">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#pwModal">Change password</button></li>
                        <li><form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item" type="submit">Sign out</button></form></li>
                    </ul>
                </div>
            </div>
        </header>

        {{-- Offline banner: only on the till, where queued sales actually matter. --}}
        @if(request()->routeIs('pos'))
        <div id="offline-banner" class="offline-banner d-none">
            Offline — sales are queued and will sync automatically when reconnected.
        </div>
        @endif

        <main class="px-4 py-3 flex-grow-1 overflow-auto">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

{{-- change password (topbar dropdown) --}}
<div class="modal fade" id="pwModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content feature-card">
            <div class="modal-header"><h6 class="modal-title">Change password</h6></div>
            <form method="POST" action="{{ route('account.password') }}">
                @csrf @method('PUT')
                <div class="modal-body">
                    <label class="form-label small">Current password</label>
                    <input class="form-control form-control-sm" type="password" name="current_password" required>
                    <label class="form-label small mt-2">New password</label>
                    <input class="form-control form-control-sm" type="password" name="password" required minlength="6">
                    <label class="form-label small mt-2">Confirm new password</label>
                    <input class="form-control form-control-sm" type="password" name="password_confirmation" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-accent">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/vendor/alpine.min.js" defer></script>
<script src="/js/offline.js"></script>
<script>
    // Collapsible sidebar: hamburger in topbar, preference remembered,
    // auto-collapses on narrow windows unless the user chose otherwise.
    (function () {
        var KEY = 'pos-sidebar-collapsed';
        function apply() {
            var stored = localStorage.getItem(KEY);
            var collapsed = stored !== null
                ? stored === '1'
                : window.matchMedia('(max-width: 991.98px)').matches;
            document.body.classList.toggle('sidebar-collapsed', collapsed);
            var btn = document.getElementById('sidebar-toggle');
            if (btn) {
                btn.title = collapsed ? 'Expand menu' : 'Collapse menu';
                btn.setAttribute('aria-label', btn.title);
                var lbl = btn.querySelector('.nav-label');
                if (lbl) lbl.textContent = collapsed ? 'Expand' : 'Collapse';
            }
        }
        var btn = document.getElementById('sidebar-toggle');
        if (btn) {
            btn.addEventListener('click', function () {
                localStorage.setItem(KEY, document.body.classList.contains('sidebar-collapsed') ? '0' : '1');
                apply();
            });
        }
        // Hover titles come from the (possibly hidden) labels.
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.sidebar .nav-link').forEach(function (a) {
                var t = a.querySelector('.nav-label');
                if (t && t.textContent.trim()) a.setAttribute('title', t.textContent.trim());
            });
        });
        window.matchMedia('(max-width: 991.98px)').addEventListener('change', apply);
        apply();
    })();

    // Mobile drawer: hamburger slides the sidebar in; backdrop or tapping a
    // nav link closes it. Escape works too, for keyboard users.
    (function () {
        var btn = document.getElementById('mobile-menu');
        var backdrop = document.getElementById('sidebar-backdrop');
        if (!btn || !backdrop) return;
        function open(state) {
            document.body.classList.toggle('sidebar-mobile-open', state);
            btn.setAttribute('aria-expanded', state ? 'true' : 'false');
        }
        btn.addEventListener('click', function () {
            open(!document.body.classList.contains('sidebar-mobile-open'));
        });
        backdrop.addEventListener('click', function () { open(false); });
        document.querySelectorAll('.sidebar .nav-link').forEach(function (a) {
            a.addEventListener('click', function () { open(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') open(false);
        });
    })();
</script>
@auth
<script>
    if (navigator.serviceWorker) {
        navigator.serviceWorker.register('/sw.js', {scope: '/'}).catch(console.error);
    }
</script>
@endauth
@stack('scripts')
</body>
</html>
