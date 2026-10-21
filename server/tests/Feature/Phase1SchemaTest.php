<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 1 acceptance: schema, roles, and seed data work end to end.
 * Runs on SQLite (phpunit.xml) to prove the schema is portable to MySQL.
 */
class Phase1SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_and_permissions_seed(): void
    {
        $this->seed();

        foreach (['superadmin', 'admin', 'cashier'] as $name) {
            $this->assertDatabaseHas('roles', ['name' => $name]);
        }

        $superadmin = Role::findByName('superadmin');
        $admin = Role::findByName('admin');
        $cashier = Role::findByName('cashier');

        $this->assertGreaterThan(10, $superadmin->permissions->count());
        $this->assertTrue($admin->hasPermissionTo('products.manage'));
        $this->assertFalse($admin->hasPermissionTo('users.manage'));
        $this->assertTrue($cashier->hasPermissionTo('pos.use'));
        $this->assertFalse($cashier->hasPermissionTo('inventory.adjust'));
    }

    public function test_default_categories_and_settings_seed(): void
    {
        $this->seed();

        $this->assertSame(13, Category::count());
        $this->assertDatabaseHas('categories', ['name' => 'Amplifiers']);
        $this->assertSame('Zion Musical Centre', Setting::get('store.name'));
    }

    public function test_staff_accounts_get_their_roles(): void
    {
        $this->seed();

        $this->assertTrue(User::whereEmail('admin@zionpos.test')->first()->hasRole('superadmin'));
        $this->assertTrue(User::whereEmail('manager@zionpos.test')->first()->hasRole('admin'));
        $this->assertTrue(User::whereEmail('cashier@zionpos.test')->first()->hasRole('cashier'));
    }

    public function test_stock_ledger_and_sales_tables_accept_rows(): void
    {
        $this->seed();

        $product = Product::create([
            'sku' => 'AMP-001',
            'barcode' => '1234567890',
            'name' => 'Cort AF510 Acoustic Guitar',
            'cost_price' => 1500.00,
            'selling_price' => 2800.00,
            'tax_rate' => 0,
            'reorder_level' => 2,
            'stock_cached' => 5,
        ]);
        $cashier = User::whereEmail('cashier@zionpos.test')->first();

        $sale = Sale::create([
            'receipt_number' => 'SALE-0001',
            'cashier_id' => $cashier->id,
            'subtotal' => 2800.00,
            'total' => 2800.00,
        ]);

        $movement = $product->stockMovements()->create([
            'movement_type' => 'sale',
            'quantity' => -1,
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
            'user_id' => $cashier->id,
        ]);

        AuditLog::record('SALE', $cashier->id, 'sale', $sale->id, 'SALE-0001 · GHS 2800.00');

        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'movement_type' => 'sale']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SALE', 'entity_id' => (string) $sale->id]);
        // Cached stock is maintained, but the ledger row is the authority (§13).
        $this->assertSame('5.000', $product->fresh()->stock_cached);
    }
}
