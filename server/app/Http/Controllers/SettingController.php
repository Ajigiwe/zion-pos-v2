<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index()
    {
        $user = auth()->user();

        return view('settings.index', [
            'store' => store_info(),
            'theme' => theme_info(),
            'threshold' => low_stock_threshold(),
            'backups' => $this->backups->list(),
            'users' => User::query()->with('roles:id,name')->orderBy('name')->get(),
            'recentAudit' => in_array($user->role_label(), ['Superadmin', 'Owner'])
                ? \App\Models\AuditLog::query()->with('user:id,name')->latest()->limit(200)->get()
                : collect(),
            'roles' => $this->roleReference(),
        ]);
    }

    /** Store details card (Flutter: store_profile_card). */
    public function updateStore(Request $request)
    {
        $data = $request->validate([
            'store.name' => ['required', 'string', 'max:255'],
            'store.address' => ['nullable', 'string', 'max:500'],
            'store.phone' => ['nullable', 'string', 'max:50'],
        ]);

        foreach (['store.name', 'store.address', 'store.phone'] as $key) {
            Setting::put($key, (string) data_get($data, $key, ''));
        }

        AuditLog::record('STORE_DETAILS_UPDATED', auth()->id(), 'setting', null,
            'Store details updated to “'.($data['store']['name'] ?? '').'”');

        return back()->with('success', 'Store details saved.');
    }

    /** Full receipt customization (Flutter: receipt_customization_card, 16 keys). */
    public function updateReceipt(Request $request)
    {
        $bool = fn (string $key) => $request->boolean($key) ? 'true' : 'false';
        $request->merge([
            'paper_width' => $request->input('paper_width') === '58' ? '58' : '80',
        ]);

        $data = $request->validate([
            'receipt.headerCustomText' => ['nullable', 'string', 'max:200'],
            'receipt.footerMessage' => ['nullable', 'string', 'max:500'],
            'paper_width' => ['required', 'in:58,80'],
            'side_margin' => ['required', 'numeric', 'min:2', 'max:36'],
            'bottom_feed_space' => ['required', 'numeric', 'min:10', 'max:120'],
        ]);

        $puts = [
            'receipt.showStoreName' => $bool('show_store_name'),
            'receipt.showStoreAddress' => $bool('show_store_address'),
            'receipt.showStorePhone' => $bool('show_store_phone'),
            'receipt.headerCustomText' => (string) data_get($data, 'receipt.headerCustomText', ''),
            'receipt.showCashierName' => $bool('show_cashier_name'),
            'receipt.showItemPriceMath' => $bool('show_item_price_math'),
            'receipt.showTax' => $bool('show_tax'),
            'receipt.showDiscount' => $bool('show_discount'),
            'receipt.showPaymentBreakdown' => $bool('show_payment_breakdown'),
            'receipt.showChangeDue' => $bool('show_change_due'),
            'receipt.footerMessage' => (string) data_get($data, 'receipt.footerMessage', ''),
            'receipt.showBarcode' => $bool('show_barcode'),
            'receipt.showCustomerSignature' => $bool('show_customer_signature'),
            'receipt.paperWidth' => $data['paper_width'],
            'receipt.sideMargin' => (string) $data['side_margin'],
            'receipt.bottomFeedSpace' => (string) $data['bottom_feed_space'],
        ];

        foreach ($puts as $key => $value) {
            Setting::put($key, $value);
        }

        AuditLog::record('SETTINGS', auth()->id(), 'setting', null, 'Receipt layout preferences updated');

        return back()->with('success', 'Receipt customization saved successfully.');
    }

    /** Printer card (Flutter: printer_settings_card). Web = browser spooler. */
    public function updatePrinter(Request $request)
    {
        $direct = $request->boolean('printer.directPrinting');

        // On the web the browser owns the printer list; keep parity keys but the
        // spooler decides the concrete device unless a named device is typed.
        Setting::put('printer.selectedUrl', (string) ($request->input('printer.selectedUrl') ?? ''));
        Setting::put('printer.selectedName', trim((string) ($request->input('printer.selectedName') ?? '')) ?: 'System Default Printer');
        Setting::put('printer.directPrinting', $direct ? 'true' : 'false');

        AuditLog::record('SETTINGS', auth()->id(), 'setting', null,
            'Active printer updated to "'.Setting::get('printer.selectedName', '').'" (direct: '.($direct ? 'true' : 'false').')');

        return back()->with('success', 'Printer settings saved.');
    }

    /** Theme card (Flutter: theme_settings_card — mode, preset, custom hex). */
    public function updateTheme(Request $request)
    {
        $mode = strtolower((string) $request->input('theme_mode', 'light'));
        abort_unless(in_array($mode, ['light', 'dark', 'system'], true), 422, 'Invalid theme mode.');

        $preset = (string) $request->input('theme_preset', 'emerald');
        $validPresets = array_keys(theme_info()['presets']);
        if (! in_array($preset, [...$validPresets, 'custom'], true)) {
            abort(422, 'Invalid preset.');
        }

        $color = '';
        $custom = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $request->input('theme_color', '')) ?? '');
        if ($preset === 'custom') {
            if (strlen($custom) === 8) {
                $custom = substr($custom, 2); // accept 8-digit ARGB inputs
            }
            if (strlen($custom) !== 6) {
                return back()->with('error', 'Enter a 6-digit RGB hex code (e.g. 16A34A).');
            }
            $color = '0xFF'.$custom;
        } else {
            $color = theme_info()['presets'][$preset][1];
        }

        Setting::put('theme_mode', $mode);
        Setting::put('theme_preset', $preset);
        Setting::put('theme_color', $color);

        AuditLog::record('SETTINGS', auth()->id(), 'setting', null, "Theme updated to {$mode} mode ({$preset})");

        return redirect()->route('settings.index')->with('success', 'Theme applied.');
    }

    /** Low stock threshold card. */
    public function updateThreshold(Request $request)
    {
        $data = $request->validate(['threshold' => ['required', 'numeric', 'min:0']]);

        Setting::put('inventory.lowStockThreshold', (string) $data['threshold']);

        AuditLog::record('SETTINGS', auth()->id(), 'setting', null, 'Low stock threshold set to '.$data['threshold'].' units');

        return back()->with('success', 'Low stock threshold saved.');
    }

    /** Reset card (Flutter: "Reset Defaults" header button). */
    public function resetTheme()
    {
        Setting::put('theme_mode', 'light');
        Setting::put('theme_preset', 'emerald');
        Setting::put('theme_color', '#16A34A');

        AuditLog::record('SETTINGS', auth()->id(), 'setting', null, 'Theme reset to defaults');

        return redirect()->route('settings.index')->with('success', 'Appearance reset to defaults.');
    }

    /** Network/station card — web keeps the till identity + connectivity mirror. */
    public function updateNetwork(Request $request)
    {
        $data = $request->validate([
            'pos_till_id' => ['required', 'string', 'max:32'],
        ]);

        Setting::put('pos.till_id_display', $data['pos_till_id']);
        AuditLog::record('SETTINGS', auth()->id(), 'setting', null, 'Station name set to '.$data['pos_till_id']);

        return back()->with('success', 'Station configuration updated.');
    }

    /** My account card — change own password (any signed-in user). */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        AuditLog::record('PASSWORD_CHANGED', auth()->id(), 'user', auth()->id(), 'Password changed (self)');

        return back()->with('success', 'Password changed.');
    }

    /** Wipe all data — parity with Flutter's "Backup & Wipe All Data". */
    public function wipe(Request $request)
    {
        $result = $this->backups->wipe();

        if ($result['ok']) {
            AuditLog::record('WIPE', auth()->id(), 'database', null, 'All transactional data wiped (backup: '.basename($result['file']).')');

            return redirect()->route('settings.index')->with('success',
                'Backup saved. All data wiped successfully — '.basename($result['file']));
        }

        return back()->with('error', 'Wipe failed: '.$result['error']);
    }

    /** Multi-shop: wipe ONE shop's transactional data; other shops untouched. */
    public function wipeShop(Request $request)
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer'],
            'confirm' => ['required', 'accepted'],
        ]);

        $shop = \App\Models\Shop::find($data['shop_id']);

        if (! $shop || $shop->id === \App\Models\Shop::main()->id) {
            return back()->with('error', 'Wipe a shop from here only when it is an ordinary shop — the main shop is cleared by the global wipe.');
        }

        if (\DB::table('products')->where('shop_id', $shop->id)->count() + \DB::table('sales')->where('shop_id', $shop->id)->count() === 0) {
            return back()->with('error', "'{$shop->name}' has no products or sales to wipe.");
        }

        $result = $this->backups->wipeShop($shop);

        if ($result['ok']) {
            AuditLog::record('WIPE_SHOP', auth()->id(), 'shop', $shop->id, "Shop '{$shop->name}' transactional data wiped (backup: ".basename($result['file']).')');

            return redirect()->route('settings.index')->with('success',
                "Backup saved. Shop '{$shop->name}' wiped — ".basename($result['file']));
        }

        return back()->with('error', 'Wipe failed: '.$result['error']);
    }

    /** Per-shop backup: data-only .sql of one shop's rows (audit/ledger history preserved). */
    public function shopBackup(Request $request)
    {
        $data = $request->validate(['shop_id' => ['required', 'integer']]);

        $shop = \App\Models\Shop::find($data['shop_id']);
        abort_unless($shop, 404);

        $result = $this->backups->createShop($shop);

        if ($result['ok']) {
            AuditLog::record('SHOP_BACKUP', auth()->id(), 'shop', $shop->id, "Shop '{$shop->name}' backed up: ".basename($result['file']));

            return back()->with('success', "Backup of '{$shop->name}' created: ".basename($result['file']));
        }

        return back()->with('error', 'Shop backup failed: '.$result['error']);
    }

    /** Per-shop restore: target shop comes from the file header; full safety backup runs first. */
    public function shopRestore(Request $request)
    {
        $result = $this->backups->restoreShop($request->file('sql'));

        if ($result['ok']) {
            AuditLog::record('SHOP_RESTORE', auth()->id(), 'shop', $result['shop']->id,
                "Shop '{$result['shop']->name}' restored from per-shop backup (safety backup: {$result['safety']})");

            return back()->with('success', "'{$result['shop']->name}' restored. Full safety backup saved as {$result['safety']}.");
        }

        return back()->with('error', 'Shop restore failed: '.$result['error']);
    }

    public function backup(Request $request)
    {
        $result = $this->backups->create();

        if ($result['ok']) {
            AuditLog::record('BACKUP', auth()->id(), 'backup', null, basename($result['file']));

            return back()->with('success', 'Backup created: '.basename($result['file']));
        }

        return back()->with('error', 'Backup failed: '.$result['error']);
    }

    public function downloadBackup(string $file): StreamedResponse
    {
        abort_if(basename($file) !== $file || ! \Illuminate\Support\Facades\Storage::disk('local')->exists('backups/'.$file), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download('backups/'.$file);
    }

    public function restore(Request $request)
    {
        if (env('APP_ENV') === 'production' && ! $request->boolean('confirm')) {
            return back()->with('error', 'Type the word RESTORE to confirm overwriting the live database.');
        }

        $result = $this->backups->restore($request->file('sql'));

        if ($result['ok']) {
            AuditLog::record('BACKUP', auth()->id(), 'restore', null, $request->file('sql')?->getClientOriginalName());

            return back()->with('success', 'Database restored — sign out and back in.');
        }

        return back()->with('error', 'Restore failed: '.$result['error']);
    }

    /** RolesReferenceCard data — parity with the Flutter role grid. */
    private function roleReference(): array
    {
        return [
            ['Superadmin', 'The developer account — oversees the entire system: users, settings, configuration and audit trail.',
                ['Make sales', 'Exchanges', 'Refunds', 'Change prices', 'Stock adjustments', 'Add / opening stock', 'Edit products', 'Delete products', 'View reports', 'Manage users', 'System configuration']],
            ['Owner', 'Runs the whole business: sales, exchanges, refunds, stock, products and reports.',
                ['Make sales', 'Exchanges', 'Refunds', 'Change prices', 'Stock adjustments', 'Add / opening stock', 'Edit products', 'Delete products', 'View reports']],
            ['Manager', 'Day-to-day operations: sales, refunds, exchanges, stock control and reports.',
                ['Make sales', 'Exchanges', 'Refunds', 'Stock adjustments', 'Edit products', 'View reports']],
            ['Inventory Manager', 'Handles stock: receiving goods, counts and adjustments; can also sell.',
                ['Make sales', 'Exchanges', 'Stock adjustments', 'Add / opening stock', 'Edit products']],
            ['Cashier', 'Makes sales and exchanges at the register — no refunds or stock edits.',
                ['Make sales', 'Exchanges']],
        ];
    }
}
