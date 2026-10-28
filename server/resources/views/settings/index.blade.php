@extends('layouts.app')

@section('page_title')
    <h5 class="mb-0">Settings</h5><small class="text-muted ms-1">Manage store identity, receipt layout, staff roles, stock thresholds and data backups.</small>
@endsection

@section('content')
<div class="settings-tabs mb-4" id="settings-tabs">
    @php($gates = [
        'all' => true, 'store' => true, 'hardware' => true, 'appearance' => true,
        'network' => true, 'users' => auth()->id() !== null, 'backup' => true,
        'audit' => auth()->user()->role_label() === 'Superadmin' || auth()->user()->role_label() === 'Owner',
    ])
    <button class="tab active" data-cat="all">All Settings</button>
    <button class="tab" data-cat="store">Store &amp; Receipts</button>
    <button class="tab" data-cat="hardware">Printers &amp; Hardware</button>
    <button class="tab" data-cat="appearance">Appearance &amp; Themes</button>
    <button class="tab" data-cat="network">Registers</button>
    <button class="tab" data-cat="users">Users &amp; Roles</button>
    <button class="tab" data-cat="backup">Inventory &amp; Backup</button>
    @if($gates['audit'])<button class="tab" data-cat="audit">Audit Log</button>@endif
</div>

<div class="row g-4" id="settings-cards">

    {{-- ========== MY ACCOUNT ========== --}}
    <div class="col-12 col-xl-6" data-cat="users">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c0-3.3 3.4-5.2 7.5-5.2s7.5 1.9 7.5 5.2"/></svg></span>
                <div><div class="fw-bold small">My account</div><div class="text-muted" style="font-size:.78rem;">Signed in as {{ auth()->user()->name }}</div></div>
            </div>
            <div class="card-body">
                <div class="detail-row mb-2">
                    <span class="label">Account</span>
                    <span class="value">{{ auth()->user()->email }}</span>
                </div>
                <div class="detail-row mb-2">
                    <span class="label">Role</span>
                    <span class="value">{{ auth()->user()->role_label() }}</span>
                </div>
                <button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#passwordModal">Change password…</button>
            </div>
        </div>
    </div>

    {{-- ========== USERS & ROLES ========== --}}
    @can('users.manage')
    <div class="col-12 col-xl-6" data-cat="users">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="icon-chip" style="background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3 2.9-4.8 6.5-4.8s6.5 1.8 6.5 4.8"/><path d="M15.5 4.7a3.5 3.5 0 0 1 0 6.6"/><path d="M17.5 15.4c2.4.7 4 2.3 4 4.6"/></svg></span>
                <div class="flex-grow-1 ms-2"><div class="fw-bold small">Users &amp; roles</div><div class="text-muted" style="font-size:.78rem;">Create cashier, manager and owner accounts.</div></div>
                <button class="btn btn-sm btn-accent" data-bs-toggle="modal" data-bs-target="#addUserModal">Add user</button>
            </div>
            <div class="card-body p-0">
                @forelse($users as $u)
                    <div class="d-flex align-items-center gap-2 px-3 py-2 {{ ! ($loop->last) ? 'border-bottom' : '' }}" style="border-color:var(--hairline);">
                        <span class="icon-chip" style="width:30px;height:30px;flex-basis:30px;background:color-mix(in srgb, #0284C7 14%, transparent);color:#0284C7;font-weight:700;">{{ mb_strtoupper(substr($u->name, 0, 1)) }}</span>
                        <div class="flex-grow-1 min-w-0 {{ $u->is_active ? '' : 'text-muted text-decoration-line-through' }}">
                            <div class="small fw-semibold">{{ $u->name }} @if($u->id === auth()->id())<span class="text-muted">{{ '(you)' }}</span>@endif</div>
                            <div class="text-muted" style="font-size:.75rem;">{{ $u->email }}</div>
                        </div>
                        <span class="pill">{{ $u->role_label() }}</span>
                        <form method="POST" action="{{ route('users.toggle', $u->id) }}">
                            @csrf
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" {{ $u->is_active ? 'checked' : '' }}
                                       onchange="this.form.submit()" {{ $u->id === auth()->id() ? 'disabled title="You cannot disable your own account."' : '' }}>
                            </div>
                        </form>
                    </div>
                @empty
                    <div class="text-center text-muted py-4 small">No users found.</div>
                @endforelse
            </div>
            <div class="card-footer small">
                <a href="{{ route('users.index') }}">Open user management →</a>
            </div>
        </div>
    </div>

    {{-- ========== ROLES REFERENCE ========== --}}
    <div class="col-12" data-cat="users">
        <div class="card feature-card">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, #7C3AED 12%, transparent);color:#7C3AED;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 2.8V11c0 4.4-3 8.6-7 10-4-1.4-7-5.6-7-10V5.8z"/><path d="m9 11.5 2 2 4-4"/></svg></span>
                <div><div class="fw-bold small">Roles &amp; permissions</div><div class="text-muted" style="font-size:.78rem;">What each sign-in role can do.</div></div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($roles as $role)
                        <div class="col-12 col-lg-6">
                            <div class="fw-semibold small">{{ $role[0] }}</div>
                            <div class="text-muted" style="font-size:.78rem;">{{ $role[1] }}</div>
                            <div class="d-flex flex-wrap gap-1 mt-1">
                                @foreach($role[2] as $perm)<span class="pill">{{ $perm }}</span>@endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endcan

    {{-- ========== STORE DETAILS ========== --}}
    @can('settings.manage')
    <div class="col-12 col-xl-6" data-cat="store">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, #0284C7 10%, transparent);color:#0284C7;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 4h15L21 9"/><path d="M3 9c0 1.4 1.1 2.5 2.5 2.5S8 10.4 8 9c0 1.4 1.1 2.5 2.5 2.5S13 10.4 13 9c0 1.4 1.1 2.5 2.5 2.5S18 10.4 18 9c0 1.4 1.1 2.5 2.5 2.5S21 10.4 21 9"/><path d="M5 12.5V20h14v-7.5"/><path d="M10 20v-4.5h4V20"/></svg></span>
                <div class="flex-grow-1"><div class="fw-bold small">Store details</div><div class="text-muted" style="font-size:.78rem;">Name, address and phone printed on receipts.</div></div>
                <button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#storeModal">Edit</button>
            </div>
            <div class="card-body">
                <div class="detail-row mb-2"><span class="label">Name</span><span class="value">{{ $store['name'] }}</span></div>
                <div class="detail-row mb-2"><span class="label">Address</span><span class="value">{{ $store['address'] ?: '—' }}</span></div>
                <div class="detail-row"><span class="label">Phone</span><span class="value">{{ $store['phone'] ?: '—' }}</span></div>
            </div>
        </div>
    </div>

    {{-- ========== RECEIPT CUSTOMIZATION ========== --}}
    <div class="col-12 col-xl-6" data-cat="store">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-chip" style="background:color-mix(in srgb, #0D9488 12%, transparent);color:#0D9488;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1.5L8 22l2-1.5L12 22l2-1.5L16 22l2-1.5L20 22V2l-2 1.5L16 2l-2 1.5L12 2l-2 1.5L8 2 6 3.5z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg></span>
                    <div><div class="fw-bold small">Receipt Customization ({{ $store['paper_width'] ?? '80' }}mm)</div><div class="text-muted" style="font-size:.78rem;">Toggle sections and customize text on printed &amp; digital receipts.</div></div>
                </div>
                <button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#receiptPreviewModal">Live Preview</button>
            </div>
            <form method="POST" action="{{ route('settings.receipt') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="fw-bold" style="font-size:13px;color:var(--muted);letter-spacing:.5px;">HEADER &amp; STORE BRANDING</div>
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-4"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_store_name" value="1" id="r-sn" {{ ($store['show_store_name'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-sn">Show Store Name</label></div></div>
                        <div class="col-12 col-md-4"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_store_address" value="1" id="r-sa" {{ ($store['show_store_address'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-sa">Show Store Address</label></div></div>
                        <div class="col-12 col-md-4"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_store_phone" value="1" id="r-sp" {{ ($store['show_store_phone'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-sp">Show Store Phone Number</label></div></div>
                        <div class="col-12"><label class="form-label small mb-1">Header Slogan / Motto (Optional)</label>
                            <input class="form-control form-control-sm" name="receipt[headerCustomText]" value="{{ old('receipt.headerCustomText', $store['header_custom_text']) }}" placeholder="e.g., Dealers in Quality Musical Instruments &amp; Audio Equipment"></div>
                    </div>

                    <div class="fw-bold" style="font-size:13px;color:var(--muted);letter-spacing:.5px;">ITEM &amp; LINE BREAKDOWN</div>
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_cashier_name" value="1" id="r-ch" {{ ($store['show_cashier_name'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-ch">Show Cashier Name</label></div></div>
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_item_price_math" value="1" id="r-math" {{ ($store['show_item_price_math'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-math">Show Unit Price Math (Qty × Unit Price)</label></div></div>
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_tax" value="1" id="r-tax" {{ ($store['show_tax'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-tax">Show Tax / VAT Line</label></div></div>
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_discount" value="1" id="r-disc" {{ ($store['show_discount'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-disc">Show Discount Line</label></div></div>
                    </div>

                    <div class="fw-bold" style="font-size:13px;color:var(--muted);letter-spacing:.5px;">PAYMENT &amp; SETTLEMENT</div>
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_payment_breakdown" value="1" id="r-pay" {{ ($store['show_payment_breakdown'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-pay">Show Payment Method Breakdown</label></div></div>
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_change_due" value="1" id="r-chg" {{ ($store['show_change_due'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-chg">Show Change Due</label></div></div>
                    </div>

                    <div class="fw-bold" style="font-size:13px;color:var(--muted);letter-spacing:.5px;">FOOTER, BARCODE &amp; POLICY</div>
                    <div class="row g-2 mb-2">
                        <div class="col-12"><label class="form-label small mb-1">Footer Message / Return Policy</label>
                            <textarea class="form-control form-control-sm" rows="2" name="receipt[footerMessage]">{{ old('receipt.footerMessage', $store['footer_message']) }}</textarea></div>
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_barcode" value="1" id="r-bc" {{ ($store['show_barcode'] ?? true) ? 'checked' : '' }}><label class="form-check-label small" for="r-bc">Show Receipt Barcode (Code128)</label></div></div>
                        <div class="col-12 col-md-6"><div class="switch-row"><input class="form-check-input" type="checkbox" name="show_customer_signature" value="1" id="r-sig" {{ ($store['show_customer_signature'] ?? false) ? 'checked' : '' }}><label class="form-check-label small" for="r-sig">Show Customer Signature Line</label></div></div>
                    </div>

                    <div class="fw-bold" style="font-size:13px;color:var(--muted);letter-spacing:.5px;">PAPER ROLL &amp; MARGIN CALIBRATION</div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1 d-block">Paper roll</label>
                            <div class="d-flex gap-1" role="group">
                                <input type="radio" class="btn-check" name="paper_width" id="pw80" value="80" {{ ($store['paper_width'] ?? '80') === '80' ? 'checked' : '' }}>
                                <label class="btn btn-sm btn-outline-accent" for="pw80">80mm Standard Roll</label>
                                <input type="radio" class="btn-check" name="paper_width" id="pw58" value="58" {{ ($store['paper_width'] ?? '80') === '58' ? 'checked' : '' }}>
                                <label class="btn btn-sm btn-outline-accent" for="pw58">58mm Slim Roll</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-0 d-flex justify-content-between">Printable Side Margin <span>{{ $store['side_margin'] ?? 14 }} mm</span></label>
                            <input type="range" class="form-range" name="side_margin" min="2" max="28" step="1" value="{{ $store['side_margin'] ?? 14 }}">
                            <div class="text-muted" style="font-size:.72rem;">Pull text inward if edges get cut off on physical paper</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-0 d-flex justify-content-between">Bottom Paper Feed Space <span>{{ $store['bottom_feed_space'] ?? 50 }} mm</span></label>
                            <input type="range" class="form-range" name="bottom_feed_space" min="20" max="100" step="5" value="{{ $store['bottom_feed_space'] ?? 50 }}">
                            <div class="text-muted" style="font-size:.72rem;">Advance paper past printer tear bar / cutter</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="reset" class="btn btn-sm btn-outline-secondary" onclick="return location.reload();">Reset to Defaults</button>
                    <button class="btn btn-sm btn-accent">Save Receipt Settings</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========== PRINTER ========== --}}
    <div class="col-12 col-xl-6" data-cat="hardware">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="icon-chip" style="background:color-mix(in srgb, #EA580C 12%, transparent);color:#EA580C;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 13h10v8H7z"/><circle cx="17.5" cy="11" r=".9" fill="currentColor" stroke="none"/></svg></span>
                <div class="flex-grow-1"><div class="fw-bold small">Receipt Printers &amp; Hardware</div><div class="text-muted" style="font-size:.78rem;">Configure direct printing, and test receipt output.</div></div>
            </div>
            <form method="POST" action="{{ route('settings.printer') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="detail-row mb-2 align-items-center">
                        <div class="flex-grow-0">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" name="printer[directPrinting]" value="1" id="dp" {{ old('printer.directPrinting', \App\Models\Setting::get('printer.directPrinting', 'true')) !== 'false' ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div>
                            <div class="fw-semibold small">Instant Direct Thermal Printing</div>
                            <div class="text-muted" style="font-size:.75rem;">Auto-fires printing right after a sale. For it to come out <b>without a dialog</b>: set the thermal printer as the Windows <b>default printer</b>, and launch the till's Chrome with <code>--kiosk-printing</code> (add it to the shortcut's target). Chrome chooses the printer at the OS level; websites cannot list or silently pick devices.</div>
                        </div>
                    </div>
                    <label class="form-label small mb-1" style="letter-spacing:.5px;color:var(--muted);font-weight:700;">CONNECTED PRINTERS</label>
                    <div class="detail-row mb-2 align-items-center">
                        <span class="icon-chip" style="width:28px;height:28px;flex-basis:28px;background:color-mix(in srgb, var(--accent) 12%, transparent);color:var(--accent);"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 13h10v8H7z"/><circle cx="17.5" cy="11" r=".9" fill="currentColor" stroke="none"/></svg></span>
                        <input class="form-control form-control-sm" name="printer[selectedName]" value="{{ old('printer.selectedName', \App\Models\Setting::get('printer.selectedName', 'System Default Printer')) }}" placeholder="System Default Printer" style="max-width:230px;">
                        <span class="pill ms-auto">BROWSER SPOOLER</span>
                    </div>
                    <div class="text-muted" style="font-size:.75rem;">The selected name is stored for reference only (a web app cannot query installed hardware) — the OS default printer and Chrome <code>--kiosk-printing</code> decide where receipts land. <span class="badge text-bg-secondary">SCANNERS</span> USB/Bluetooth keyboard-wedge scanners need no setup: focus the POS search or Scan dialog, scan — the code arrives typed with Enter.</div>
                </div>
                <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                    <button type="button" onclick="printTestReceiptDirect(event)" class="btn btn-sm btn-accent" id="btn-print-test">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 13h10v8H7z"/></svg>
                        Print Test Receipt
                    </button>
                    <a href="{{ route('settings.printer.test') }}" class="btn btn-sm btn-outline-secondary" title="View test receipt page">
                        Open Test Page
                    </a>
                    <span class="text-muted small flex-grow-1" style="min-width:220px;">Sends an {{ $store['paper_width'] ?? '80' }}mm sample to verify cutter, font and communication.</span>
                    <button class="btn btn-sm btn-outline-accent">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ========== REGISTERS ========== --}}
    <div class="col-12 col-xl-6" data-cat="network">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, #2563EB 12%, transparent);color:#2563EB;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.5 2.6 2.5 15.4 0 18"/><path d="M12 3c-2.5 2.6-2.5 15.4 0 18"/></svg></span>
                <div class="flex-grow-1"><div class="fw-bold small">Registers</div><div class="text-muted" style="font-size:.78rem;">All tills share one live database; a till that loses connection queues its sales offline until it reconnects.</div></div>
            </div>
            <form method="POST" action="{{ route('settings.network') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <label class="form-label small mb-1">This till's name (stamped on receipts and in the audit log)</label>
                    <input class="form-control form-control-sm" name="pos_till_id" value="{{ old('pos_till_id', config('pos.till_id')) }}" required style="max-width:280px;">
                    <div class="text-muted mt-2" style="font-size:.75rem;">Give each computer a different name (e.g. Front Counter, Store Room) so sales and till reports stay separated.</div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-sm btn-accent">Save</button></div>
            </form>
        </div>
    </div>

    {{-- ========== THEME ========== --}}
    @can('settings.manage')
    <div class="col-12 col-xl-6" data-cat="appearance">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-chip" style="background:color-mix(in srgb, #7C3AED 12%, transparent);color:#7C3AED;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18c1.4 0 2.2-.8 2.2-2 0-1.2-.9-1.6-.4-2.7.4-1 1.5-.9 2.7-1.1A7 7 0 0 0 21 12a8.2 8.2 0 0 0-9-9z"/><circle cx="8.5" cy="9" r="1.2" fill="currentColor" stroke="none"/><circle cx="12" cy="7" r="1.2" fill="currentColor" stroke="none"/><circle cx="15.5" cy="9" r="1.2" fill="currentColor" stroke="none"/><circle cx="7.5" cy="13.3" r="1.2" fill="currentColor" stroke="none"/></svg></span>
                    <div><div class="fw-bold small">Appearance &amp; Themes</div><div class="text-muted" style="font-size:.78rem;">Customize dark mode, system brightness, and store brand colors.</div></div>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.themes.reset') }}">Reset Defaults</a>
            </div>
            <form method="POST" action="{{ route('settings.theme') }}" id="theme-form">
                @csrf @method('PUT')
                <input type="hidden" name="theme_preset" id="theme-preset-input" value="{{ $theme['preset'] }}">
                <input type="hidden" name="theme_color" id="theme-color-input" value="">
                <div class="card-body">
                    <div class="fw-bold small mb-2" style="letter-spacing:.5px;color:var(--muted);">THEME MODE</div>
                    <div class="row g-2 mb-3">
                        @foreach([['light','Light Mode','Clean & daylight ready'],['dark','Dark Mode','Low-glare night aesthetic'],['system','System Default','Sync with OS brightness']] as $mode)
                            <div class="col-4">
                                <label class="p-2 d-block rounded h-100" style="border:1px solid var(--hairline);cursor:pointer;">
                                    <input type="radio" class="me-1" name="theme_mode" value="{{ $mode[0] }}" {{ $theme['mode'] === $mode[0] ? 'checked' : '' }}>
                                    <span class="small fw-semibold">{{ $mode[1] }}</span>
                                    <div class="text-muted" style="font-size:.72rem;">{{ $mode[2] }}</div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="fw-bold small" style="letter-spacing:.5px;color:var(--muted);">ACCENT BRAND PALETTE</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="preset-swatches">
                        @foreach($theme['presets'] as $id => $p)
                            <button type="button" class="swatch btn btn-sm" data-preset="{{ $id }}"
                                style="border:1px solid var(--hairline); {{ $theme['preset'] === $id ? 'border-color:'.$p[1].';border-width:2px;' : '' }}">
                                <span style="display:inline-block;width:24px;height:24px;border-radius:50%;background:{{ $p[1] }};{{ $theme['preset'] === $id ? 'box-shadow:0 0 0 2px #fff inset,0 0 0 3px '.$p[1] : '' }}"></span>
                                <div class="small" style="font-size:.7rem;">{{ $p[0] }}</div>
                            </button>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2 align-items-center mb-3">
                        <div class="text-muted small">or</div>
                        <input class="form-control form-control-sm" id="custom-hex" placeholder="Custom Hex Color — e.g. 16A34A" style="max-width:220px;" value="{{ $theme['preset'] === 'custom' ? ltrim($theme['accent'], '#') : '' }}">
                        <button type="button" class="btn btn-sm btn-outline-accent" id="apply-custom">Apply Color</button>
                    </div>
                    <div class="fw-bold small mb-2" style="letter-spacing:.5px;color:var(--muted);">LIVE PREVIEW</div>
                    <div class="d-flex">
                        <div class="p-2" style="width:150px;border-radius:8px 0 0 8px;background:var(--card);border:1px solid var(--hairline);border-right:0;">
                            <div class="fw-bold small text-accent mb-2">POS Till</div>
                            <div class="p-1 rounded small text-white mb-1" style="background:var(--accent);">Register</div>
                            <div class="p-1 rounded small" style="color:var(--muted);">Stock</div>
                            <div class="p-1 rounded small" style="color:var(--muted);">Reports</div>
                        </div>
                        <div class="flex-grow-1 p-3" style="border-radius:0 8px 8px 0;background:var(--chip);border:1px solid var(--hairline);">
                            <div class="fw-semibold small">Yamaha Pacifica 112V</div>
                            <div class="text-muted" style="font-size:.72rem;">Electric Guitar · SKU: GT-112V · Unit Price: {{ money(2450) }}</div>
                            <span class="pill ok">IN STOCK</span>
                            <div class="d-flex gap-2 mt-2">
                                <span class="btn btn-sm btn-accent" style="pointer-events:none;">Complete Sale</span>
                                <span class="btn btn-sm btn-outline-accent" style="pointer-events:none;">Hold Cart</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end"><button class="btn btn-sm btn-accent">Save Appearance</button></div>
            </form>
        </div>
    </div>

    {{-- ========== LOW STOCK THRESHOLD ========== --}}
    <div class="col-12 col-xl-6" data-cat="backup">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="icon-chip" style="background:color-mix(in srgb, #CA8A04 14%, transparent);color:#CA8A04;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19z"/><path d="M12 9.5v4.5"/><path d="M12 17h.01"/></svg></span>
                    <div><div class="fw-bold small">Low stock alerts</div></div>
                </div>
                <button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#thresholdModal">Edit</button>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="label">Threshold</span>
                    <span class="value">{{ rtrim(rtrim(number_format($threshold, 2), '0'), '.') }} units or fewer</span>
                </div>
                <div class="text-muted mt-2" style="font-size:.75rem;">Products at or below this quantity are flagged on the Dashboard. Products with their own reorder level use that instead.</div>
            </div>
        </div>
    </div>

    {{-- ========== BACKUP ========== --}}
    <div class="col-12 col-xl-6" data-cat="backup">
        <div class="card feature-card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <span class="icon-chip" style="background:color-mix(in srgb, #059669 12%, transparent);color:#059669;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5.5" rx="8" ry="2.8"/><path d="M4 5.5V18.2c0 1.6 3.6 2.9 8 2.9s8-1.3 8-2.9V5.5"/><path d="M4 11.9c0 1.6 3.6 2.9 8 2.9s8-1.3 8-2.9"/></svg></span>
                <div><div class="fw-bold small">Database backup</div><div class="text-muted" style="font-size:.78rem;">Export a copy of the database, or replace this store's data from a backup.</div></div>
            </div>
            <div class="card-body">
                <div class="d-flex gap-2 mb-2 flex-wrap">
                    <form method="POST" action="{{ route('settings.backup') }}">@csrf
                        <button class="btn btn-sm btn-accent">Export backup…</button>
                    </form>
                    <button class="btn btn-sm btn-outline-accent" data-bs-toggle="modal" data-bs-target="#restoreModal">Restore from backup…</button>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#wipeModal">Wipe all data…</button>
                    @role('superadmin')
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#wipeShopModal">Wipe one shop…</button>
                    @endrole
                </div>
                @role('superadmin')
                    <div class="border-top pt-2 mt-2">
                        <div class="fw-bold small" style="letter-spacing:.5px;color:var(--muted);">PER-SHOP</div>
                        <div class="text-muted" style="font-size:.75rem;">Backup or restore a single shop's data. Wipe already works per shop.</div>
                        <form method="POST" action="{{ route('settings.shopBackup') }}" class="d-flex gap-2 mt-2 align-items-center">
                            @csrf
                            <select name="shop_id" class="form-select form-select-sm" style="max-width:220px;">
                                @foreach(\App\Models\Shop::query()->ordered()->get() as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }})</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn-accent text-nowrap">Export shop backup…</button>
                        </form>
                        <button class="btn btn-sm btn-outline-accent mt-2" data-bs-toggle="modal" data-bs-target="#shopRestoreModal">Restore one shop…</button>
                    </div>
                @endrole
                <div class="table-responsive">
                <table class="table table-sm small mb-0">
                    <thead><tr><th>File</th><th class="text-end">Size</th><th class="text-end">When</th><th></th></tr></thead>
                    <tbody>
                        @forelse($backups as $b)
                            <tr>
                                <td class="text-truncate" style="max-width:150px;">{{ $b['file'] }}</td>
                                <td class="text-end">{{ round($b['size'] / 1024) }} KB</td>
                                <td class="text-end text-muted">{{ \Carbon\Carbon::createFromTimestamp($b['time'])->format('d M H:i') }}</td>
                                <td class="text-end"><a href="{{ route('settings.backups.download', $b['file']) }}" class="btn btn-sm btn-outline-secondary">Download</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No backups yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ========== RECENT AUDIT ========== --}}
    @if($recentAudit->isNotEmpty())
        <div class="col-12" data-cat="audit">
            <div class="card feature-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="icon-chip" style="background:color-mix(in srgb, #7C3AED 12%, transparent);color:#7C3AED;"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/><path d="M12 14.5v3"/></svg></span>
                        <div><div class="fw-bold small">Audit log</div><div class="text-muted" style="font-size:.78rem;">Who did what, and when — this record cannot be edited.</div></div>
                    </div>
                    <a href="{{ route('audit.index') }}" class="small">Full audit →</a>
                </div>
                <div class="card-body p-0" style="max-height:440px;overflow-y:auto;">
                    @foreach($recentAudit as $entry)
                        <div class="d-flex align-items-center gap-2 px-3 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}" style="border-color:var(--hairline);">
                            @php($danger = in_array($entry->action, ['LOGIN_FAILED', 'USER_DEACTIVATED', 'STOCK_ADJUSTMENT', 'WIPE']))
                        @php($login = in_array($entry->action, ['LOGIN', 'LOGIN_FAILED']))
                        <span class="icon-chip" style="width:26px;height:26px;flex-basis:26px;background:{{ $danger ? 'rgba(239,68,68,.12)' : 'rgba(2,132,199,.12)' }};color:{{ $danger ? '#EF4444' : '#0284C7' }};">
                            @if($login)
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10.5" width="16" height="10.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 7.4-2.1"/><path d="M12 14.5v3"/></svg>
                            @else
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1.5L8 22l2-1.5L12 22l2-1.5L16 22l2-1.5L20 22V2l-2 1.5L16 2l-2 1.5L12 2l-2 1.5L8 2 6 3.5z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/></svg>
                            @endif
                        </span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="small fw-semibold">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', strtolower($entry->action))) }}</div>
                                <div class="text-muted" style="font-size:.75rem;">{{ $entry->user?->name ? '@'.$entry->user->name : '' }}{{ $entry->user && $entry->details ? ' · ' : '' }}{{ $entry->details }}</div>
                            </div>
                            <div class="text-muted" style="font-size:.72rem;">{{ $entry->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    @endcan
</div>

{{-- ================= MODALS ================= --}}
{{-- Store details modal --}}
<div class="modal fade" id="storeModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('settings.store') }}">
        @csrf @method('PUT')
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Edit store details</h6></div>
            <div class="modal-body">
                <label class="form-label small">Store name *</label>
                <input class="form-control" name="store[name]" required value="{{ old('store.name', $store['name']) }}">
                <label class="form-label small mt-2">Address</label>
                <textarea class="form-control" rows="2" name="store[address]">{{ old('store.address', $store['address']) }}</textarea>
                <div class="form-text">As printed under the store name on receipts</div>
                <label class="form-label small mt-2">Phone</label>
                <input class="form-control" name="store[phone]" value="{{ old('store.phone', $store['phone']) }}">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-accent">Save details</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Password modal --}}
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('account.password') }}">
        @csrf @method('PUT')
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Change password</h6></div>
            <div class="modal-body">
                <label class="form-label small">Current password</label>
                <input class="form-control" type="password" name="current_password" required>
                @error('current_password')<div class="text-danger small">Current password is incorrect.</div>@enderror
                <label class="form-label small mt-2">New password</label>
                <input class="form-control" type="password" name="password" required minlength="6">
                <div class="form-text">At least 6 characters</div>
                <label class="form-label small mt-2">Confirm new password</label>
                <input class="form-control" type="password" name="password_confirmation" required>
                @if($errors->has('password') && $errors->has('password_confirmation'))<div class="text-danger small">Passwords do not match.</div>@enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-accent">Change password</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Add user modal --}}
@can('users.manage')
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Add user</h6></div>
            <div class="modal-body">
                <label class="form-label small">Full name *</label>
                <input class="form-control" name="name" required>
                <label class="form-label small mt-2">Email (sign-in) *</label>
                <input class="form-control" type="email" name="email" required>
                <label class="form-label small mt-2">Role *</label>
                <select class="form-select" name="role">
                    <option value="cashier" selected>Cashier</option>
                    <option value="inventory_manager">Inventory Manager</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Owner</option>
                    <option value="superadmin">Superadmin</option>
                </select>
                <label class="form-label small mt-2">Password *</label>
                <input class="form-control" type="password" name="password" required minlength="6">
                <div class="form-text">At least 6 characters</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-accent">Create user</button>
            </div>
        </div>
    </form></div>
</div>
@endcan

{{-- Threshold modal --}}
<div class="modal fade" id="thresholdModal" tabindex="-1">
    <div class="modal-dialog modal-sm"><form method="POST" action="{{ route('settings.threshold') }}">
        @csrf @method('PUT')
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Edit low stock threshold</h6></div>
            <div class="modal-body">
                <label class="form-label small">Alert at units or fewer</label>
                <input class="form-control" type="number" step="0.001" min="0" name="threshold" value="{{ $threshold }}" required>
                <div class="form-text">Applies to products without their own reorder level</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-accent">Save</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Restore modal --}}
<div class="modal fade" id="restoreModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('settings.restore') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title text-warning">Restore database?</h6></div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-2">Restoring replaces ALL data (sales, products, stock, users and settings) with the contents of the backup file. You will be signed out.</div>
                <div class="text-muted small mb-2">Consider exporting a backup of the current data first.</div>
                <input type="file" class="form-control" name="sql" accept=".sql" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Restore</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Wipe modal --}}
<div class="modal fade" id="wipeModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('settings.wipe') }}">
        @csrf
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title text-danger">Wipe all data &amp; reset store?</h6></div>
            <div class="modal-body">
                <div class="alert alert-danger small mb-2">This will permanently erase ALL sales, stock history, products, categories, and audit logs.</div>
                <div class="fw-semibold small mb-1">Mandatory Backup:</div>
                <div class="small text-muted">A full database backup will automatically be exported and saved BEFORE any data is wiped, ensuring you never lose your records.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Backup &amp; Wipe All Data</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Wipe ONE shop modal (superadmin, multi-shop) --}}
<div class="modal fade" id="wipeShopModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('settings.wipeShop') }}">
        @csrf
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title text-danger">Wipe one shop's data</h6></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">Erases a single shop's products, categories, stock history, sales, refunds and imports. Other shops keep everything; staff, settings and the shop record stay. A full backup is written first.</div>
                <label class="form-label small fw-semibold">Shop</label>
                <select name="shop_id" class="form-select form-select-sm" required>
                    @foreach(\App\Models\Shop::query()->ordered()->get() as $s)
                        @if($s->id !== \App\Models\Shop::main()->id)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->code }}){{ $s->is_active ? '' : ' — inactive' }}</option>
                        @endif
                    @endforeach
                </select>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="wipe-shop-confirm" name="confirm" value="1" required>
                    <label class="form-check-label small text-danger" for="wipe-shop-confirm">I understand this permanently erases the selected shop's data.</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Backup &amp; Wipe This Shop</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Restore ONE shop modal (superadmin, multi-shop) — the file's header names its shop --}}
<div class="modal fade" id="shopRestoreModal" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('settings.shopRestore') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Restore one shop's data</h6></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">Upload a <strong>per-shop backup</strong> (.sql exported by the button above &mdash; the file itself knows which shop it belongs to). The shop's current products, stock, sales, refunds and imports are replaced with the backup's contents; everything else (users, settings, other shops, audit trail) is untouched.</div>
                <div class="alert alert-warning small">A FULL database backup is written automatically before anything is changed.</div>
                <input type="file" name="sql" accept=".sql" class="form-control form-control-sm" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-accent">Backup safety copy &amp; Restore shop</button>
            </div>
        </div>
    </form></div>
</div>

{{-- Receipt live preview modal — renders the actual receipt partial with Flutter sample data --}}
<div class="modal fade" id="receiptPreviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title">Live Receipt Preview ({{ $store['paper_width'] ?? '80' }}mm)</h6></div>
            <div class="modal-body" style="background:#F1F5F9;">
                @php($darkPreview = theme_info()['mode'] === 'dark')
                <div style="background:#F1F5F9;">
                    @include('receipts._thermal', ['store' => [
                        'name' => $store['name'], 'address' => $store['address'], 'phone' => $store['phone'],
                        'show_store_name' => $store['show_store_name'], 'show_store_address' => $store['show_store_address'],
                        'show_store_phone' => $store['show_store_phone'], 'header_custom_text' => $store['header_custom_text'],
                        'show_cashier_name' => $store['show_cashier_name'], 'show_item_price_math' => $store['show_item_price_math'],
                        'show_tax' => $store['show_tax'], 'show_discount' => $store['show_discount'],
                        'show_payment_breakdown' => $store['show_payment_breakdown'], 'show_change_due' => $store['show_change_due'],
                        'footer_message' => $store['footer_message'], 'show_barcode' => $store['show_barcode'],
                        'show_customer_signature' => $store['show_customer_signature'],
                        'paper_width' => $store['paper_width'], 'side_margin' => $store['side_margin'],
                        'bottom_feed_space' => min($store['bottom_feed_space'], 20), 'currency' => $store['currency'],
                    ], 'r' => [
                        'kind' => 'sale', 'title' => 'RECEIPT', 'number' => 'REC-2026-0089',
                        'date' => now()->format('Y-m-d H:i'), 'cashier' => auth()->user()->name.' ('.auth()->user()->role_label().')',
                        'items' => [
                            ['name' => 'Yamaha F310 Acoustic Guitar', 'qty' => 1, 'unit' => 90.0, 'total' => 90.0],
                            ['name' => "D'Addario Guitar Strings Set", 'qty' => 2, 'unit' => 25.0, 'total' => 50.0],
                        ],
                        'subtotal' => 140.0, 'discount' => 10.0, 'tax' => 17.5, 'total' => 147.5,
                        'payments' => [['method' => 'cash', 'amount' => 150.0, 'ref' => '']],
                        'change' => 2.5, 'needs_review' => false,
                    ]])
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close Preview</button></div>
        </div>
    </div>
</div>
@endcan

@endsection

@push('scripts')
<script>
// Settings tab filter: cards carry data-cat; tabs toggle visibility.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#settings-tabs .tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('#settings-tabs .tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            var cat = tab.dataset.cat;
            var all = cat === 'all';
            document.querySelectorAll('#settings-cards [data-cat]').forEach(function (card) {
                card.style.display = all || card.dataset.cat === cat ? '' : 'none';
            });
        });
    });
    // Preset swatches
    function applyPreset(btn) {
        document.querySelectorAll('.swatch').forEach(b => { b.style.borderWidth = '1px'; b.style.borderColor = ''; });
        btn.style.borderWidth = '2px'; btn.style.borderColor = 'var(--accent)';
        document.getElementById('theme-preset-input').value = btn.dataset.preset;
        document.getElementById('theme-color-input').value = '';
        // Live recolor of the page accent
        document.documentElement.style.setProperty('--accent', btn.dataset.color || btn.querySelector('[style*="background"]').style.backgroundColor);
    }
    document.querySelectorAll('.swatch').forEach(function (b) {
        b.dataset.color = getComputedStyle(b.querySelector('span')).backgroundColor;
        b.addEventListener('click', function () { applyPreset(b); });
    });
    var applyCustom = document.getElementById('apply-custom');
    if (applyCustom) applyCustom.addEventListener('click', function () {
        var hex = document.getElementById('custom-hex').value.trim().replace('#', '');
        if (!/^[0-9A-Fa-f]{6}$/.test(hex)) { alert('Enter a 6-digit RGB hex code (e.g. 16A34A).'); return; }
        document.getElementById('theme-preset-input').value = 'custom';
        document.getElementById('theme-color-input').value = '0xFF' + hex.toUpperCase();
        document.documentElement.style.setProperty('--accent', '#' + hex);
    });
});

window.printTestReceiptDirect = function (e) {
    if (e) e.preventDefault();
    var btn = document.getElementById('btn-print-test');
    var originalHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Printing...';
    }
    var frame = document.getElementById('test-receipt-frame');
    if (!frame) {
        frame = document.createElement('iframe');
        frame.id = 'test-receipt-frame';
        frame.style.position = 'fixed';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.style.opacity = '0';
        document.body.appendChild(frame);
    }
    frame.src = '{{ route('settings.printer.test') }}?direct=1&t=' + Date.now();
    setTimeout(function () {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }, 2000);
};
</script>
@endpush
