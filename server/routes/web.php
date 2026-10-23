<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});
Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')->name('logout');

// ─── Authenticated app ───────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // POS / till
    Route::get('pos', [POSController::class, 'index'])->middleware('permission:pos.view')->name('pos');
    Route::get('pos/history', [POSController::class, 'history'])->middleware('permission:pos.view')->name('pos.history');
    Route::get('pos/history/{sale}', [POSController::class, 'detail'])->middleware('permission:pos.view')->name('pos.detail');
    Route::post('pos', [POSController::class, 'store'])->middleware('permission:pos.use')->name('pos.store');

    // Sales history + receipts
    Route::get('sales', [SaleController::class, 'index'])->middleware('permission:pos.view')->name('sales.index');
    Route::get('sales/{sale}/receipt', [ReceiptController::class, 'show'])->middleware('permission:pos.view')->name('receipts.show');
    Route::get('sales/{sale}/receipt/pdf', [ReceiptController::class, 'pdf'])->middleware('permission:pos.view')->name('receipts.pdf');

    // Products + taxonomy
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::middleware('permission:products.manage')->group(function () {
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });
    Route::get('taxonomy', [TaxonomyController::class, 'index'])->middleware('permission:products.manage')->name('taxonomy.index');
    Route::post('taxonomy/{type}', [TaxonomyController::class, 'store'])->middleware('permission:products.manage')->name('taxonomy.store');
    Route::put('taxonomy/{type}/{id}', [TaxonomyController::class, 'update'])->middleware('permission:products.manage')->name('taxonomy.update');
    Route::delete('taxonomy/{type}/{id}', [TaxonomyController::class, 'destroy'])->middleware('permission:products.manage')->name('taxonomy.destroy');

    // Inventory ledger + adjustments
    Route::get('inventory', [InventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::get('inventory/opening', [InventoryController::class, 'openingWizard'])->middleware('permission:inventory.view')->name('inventory.opening');
    Route::post('inventory/opening', [InventoryController::class, 'openingRecord'])->middleware('permission:inventory.adjust')->name('inventory.opening.record');
    Route::get('inventory/sheet', [InventoryController::class, 'sheet'])->middleware('permission:inventory.view')->name('inventory.sheet');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.adjust')->name('inventory.adjust');

    // Imports
    Route::get('imports', [ImportController::class, 'index'])->middleware('permission:imports.view')->name('imports.index');
    Route::get('imports/template', [ImportController::class, 'template'])->middleware('permission:imports.view')->name('imports.template');
    Route::post('imports/preview', [ImportController::class, 'preview'])->middleware('permission:imports.run')->name('imports.preview');
    Route::post('imports/apply', [ImportController::class, 'commit'])->middleware('permission:imports.run')->name('imports.apply');
    Route::post('imports/products', [ImportController::class, 'products'])->middleware('permission:imports.run')->name('imports.products');
    Route::post('imports/stock', [ImportController::class, 'stock'])->middleware('permission:imports.run')->name('imports.stock');

    // Refunds + exchanges
    Route::get('refunds', [RefundController::class, 'index'])->middleware('permission:refunds.view')->name('refunds.index');
    Route::get('refunds/find', [RefundController::class, 'find'])->middleware('permission:refunds.request')->name('refunds.find');
    Route::get('refunds/{sale}/flow', [RefundController::class, 'flow'])->middleware('permission:pos.view')->name('refunds.flow');
    Route::post('refunds', [RefundController::class, 'store'])->middleware('permission:refunds.request')->name('refunds.store');
    Route::post('refunds/{refund}/approve', [RefundController::class, 'approve'])->middleware('permission:refunds.approve')->name('refunds.approve');
    Route::post('refunds/{refund}/reject', [RefundController::class, 'reject'])->middleware('permission:refunds.approve')->name('refunds.reject');
    Route::get('exchanges', [ExchangeController::class, 'show'])->middleware('permission:exchanges.process')->name('exchanges.show');
    Route::post('exchanges', [ExchangeController::class, 'store'])->middleware('permission:exchanges.process')->name('exchanges.store');

    // Reports + audit
    Route::get('reports', [ReportController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('reports/inventory/export', [ReportController::class, 'exportInventory'])->middleware('permission:reports.view')->name('reports.export.inventory');
    Route::get('audit', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit.index');

    // Users (superadmin only)
    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.manage')->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->middleware('permission:users.manage')->name('users.create');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.manage')->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage')->name('users.update');
    Route::get('users/{user}/permissions', [UserController::class, 'permissions'])->middleware('permission:users.manage')->name('users.permissions');
    Route::put('users/{user}/permissions', [UserController::class, 'updatePermissions'])->middleware('permission:users.manage')->name('users.permissions.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage')->name('users.destroy');

    // Settings (superadmin only)
    Route::get('settings', [SettingController::class, 'index'])->middleware('permission:settings.manage')->name('settings.index');
    Route::put('settings/store', [SettingController::class, 'updateStore'])->middleware('permission:settings.manage')->name('settings.store');
    Route::put('settings/receipt', [SettingController::class, 'updateReceipt'])->middleware('permission:settings.manage')->name('settings.receipt');
    Route::put('settings/printer', [SettingController::class, 'updatePrinter'])->middleware('permission:settings.manage')->name('settings.printer');
    Route::get('settings/printer/test', ReceiptController::class.'@testReceipt')->middleware('permission:pos.view')->name('settings.printer.test');
    Route::put('settings/theme', [SettingController::class, 'updateTheme'])->middleware('permission:settings.manage')->name('settings.theme');
    Route::get('settings/theme/reset', [SettingController::class, 'resetTheme'])->middleware('permission:settings.manage')->name('settings.themes.reset');
    Route::put('settings/threshold', [SettingController::class, 'updateThreshold'])->middleware('permission:settings.manage')->name('settings.threshold');
    Route::put('settings/network', [SettingController::class, 'updateNetwork'])->middleware('permission:settings.manage')->name('settings.network');
    Route::post('settings/backup', [SettingController::class, 'backup'])->middleware('permission:settings.manage')->name('settings.backup');
    Route::get('settings/backups/{file}', [SettingController::class, 'downloadBackup'])->middleware('permission:settings.manage')->name('settings.backups.download');
    Route::post('settings/restore', [SettingController::class, 'restore'])->middleware('permission:settings.manage')->name('settings.restore');
    Route::post('settings/wipe', [SettingController::class, 'wipe'])->middleware('permission:settings.manage')->name('settings.wipe');

    // Own account — every signed-in user can change their own password
    Route::put('account/password', [SettingController::class, 'changePassword'])->name('account.password');

    // Users: quick active/inactive toggle from the settings card
    Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->middleware('permission:users.manage')->name('users.toggle');

    // PWA sync endpoints (same-origin, session auth, CSRF via meta tag)
    Route::get('api/snapshot', [\App\Http\Controllers\SyncController::class, 'snapshot'])->name('api.snapshot');
    Route::post('api/sales', [\App\Http\Controllers\SyncController::class, 'storeSale'])->name('api.sales');
    Route::get('api/ping', [\App\Http\Controllers\SyncController::class, 'ping'])->name('api.ping');

    // Developer self-deploy (repository is the source of truth; superadmin only)
    Route::get('deploy', [DeployController::class, 'index'])->middleware('role:superadmin')->name('deploy.index');
    Route::post('deploy', [DeployController::class, 'run'])->middleware('role:superadmin')->name('deploy.run');
});
