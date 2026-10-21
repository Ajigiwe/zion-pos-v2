<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Critical business flows from the Flutter app's domain (Phase 4 acceptance). */
class PosTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::whereEmail('admin@zionpos.test')->first();
        $this->cashier = User::whereEmail('cashier@zionpos.test')->first();
    }

    private function product(float $price = 100.0, float $stock = 10.0): Product
    {
        return Product::create([
            'sku' => 'TEST-'.uniqid(),
            'name' => 'Test product',
            'cost_price' => 50,
            'selling_price' => $price,
            'reorder_level' => 2,
            'stock_cached' => $stock,
        ]);
    }

    public function test_cashier_can_complete_a_sale(): void
    {
        $product = $this->product(2800, 5);

        $sale = app(\App\Services\SaleService::class)->createSale($this->cashier, [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 5600]],
        ]);

        $this->assertSame('SALE-000001', $sale->receipt_number);
        $this->assertSame(5600.0, (float) $sale->total);
        $this->assertSame(1, DB::table('stock_movements')->where('product_id', $product->id)->where('quantity', -2)->count());
        $this->assertSame(3.000, (float) $product->fresh()->stock_cached);
        $this->assertDatabaseHas('audit_logs', ['action' => 'SALE', 'entity_id' => $sale->id]);
    }

    public function test_online_sale_cannot_oversell_for_cashiers(): void
    {
        $product = $this->product(100, 1);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\SaleService::class)->createSale($this->cashier, [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 300]],
        ]);
    }

    public function test_offline_sale_may_oversell_and_is_flagged(): void
    {
        $product = $this->product(100, 1);

        $sale = app(\App\Services\SaleService::class)->createSale(
            $this->cashier,
            ['items' => [['product_id' => $product->id, 'quantity' => 3]], 'payments' => [['method' => 'mobile_money', 'amount' => 300, 'reference' => 'MM123']]],
            ['offline' => true],
        );

        $this->assertTrue($sale->needs_review);
        $this->assertSame('offline_pwa', $sale->source);
        $this->assertSame(-2.000, (float) $product->fresh()->stock_cached); // ledger goes negative
        $this->assertDatabaseHas('audit_logs', ['action' => 'SALE_OVERSOLD']);
    }

    public function test_sync_api_is_idempotent_on_retries(): void
    {
        $product = $this->product(100, 50);
        $key = '11111111-1111-4111-8111-111111111111';

        // First sync creates…
        $headers = ['X-Idempotency-Key' => $key, 'Accept' => 'application/json'];
        $first = $this->actingAs($this->cashier)
            ->postJson('/api/sales', ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'payments' => [['method' => 'cash', 'amount' => 200]]], $headers);

        $first->assertCreated(201);
        $saleId = $first->json('sale.id');

        // …the retry never creates a second row (server 409/dedupe).
        $retry = $this->actingAs($this->cashier)
            ->postJson('/api/sales', ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'payments' => [['method' => 'cash', 'amount' => 200]]], $headers);

        $this->assertSame(1, DB::table('sales')->whereNotNull('idempotency_key')->count());
        $this->assertContains($retry->getStatusCode(), [201, 409]);
        $this->assertSame($saleId, Sale::where('idempotency_key', $key)->first()?->id);
    }

    public function test_cashier_refund_goes_pending_and_admin_approval_moves_stock(): void
    {
        $service = app(\App\Services\RefundService::class);
        $product = $this->product(500, 4);
        $sale = app(\App\Services\SaleService::class)->createSale($this->cashier, [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ]);
        $saleItemId = $sale->items()->first()->id;
        $stockAfterSale = (float) $product->fresh()->stock_cached;

        $refund = $service->createRefund($sale, $this->cashier, [['sale_item_id' => $saleItemId, 'quantity' => 1]], 'cash', 'customer changed mind');

        $this->assertSame('PENDING', $refund->status);
        $this->assertSame($stockAfterSale, (float) $product->fresh()->stock_cached); // nothing moves yet

        $service->approve($refund, $this->admin);

        $this->assertSame('APPROVED', $refund->fresh()->status);
        $this->assertSame($stockAfterSale + 1.0, (float) $product->fresh()->stock_cached);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'movement_type' => 'refund', 'quantity' => 1]);
        // Original sale untouched (§14)
        $this->assertSame(1000.0, (float) $sale->fresh()->total);
    }

    public function test_refunds_cannot_exceed_sold_quantity(): void
    {
        $service = app(\App\Services\RefundService::class);
        $product = $this->product(500, 4);
        $sale = app(\App\Services\SaleService::class)->createSale($this->admin, [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ]);
        $saleItemId = $sale->items()->first()->id;

        $service->createRefund($sale, $this->admin, [['sale_item_id' => $saleItemId, 'quantity' => 1]], 'cash');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createRefund($sale, $this->admin, [['sale_item_id' => $saleItemId, 'quantity' => 1]], 'cash');
    }

    public function test_exchange_moves_stock_both_directions(): void
    {
        $returned = $this->product(500, 4);
        $replacement = $this->product(700, 6);   // replaced item costs more → cash difference

        $original = app(\App\Services\SaleService::class)->createSale($this->cashier, [
            'items' => [['product_id' => $returned->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ]);

        [$exchange, $refund, $replacementSale] = app(\App\Services\ExchangeService::class)
            ->createExchange($original, $this->admin, [
                ['sale_item_id' => $original->items()->first()->id, 'quantity' => 1],
            ], [
                ['product_id' => $replacement->id, 'quantity' => 1],
            ]);

        $this->assertSame(200.0, (float) $exchange->difference); // customer pays the extra
        $this->assertSame('exchange_credit', $replacementSale->payments->first()->payment_method);
        $lastPayment = $replacementSale->payments->last();
        $this->assertSame('cash', $lastPayment->payment_method);
        $this->assertSame(200.0, (float) $lastPayment->amount);
        $this->assertSame(4.000, (float) $returned->fresh()->stock_cached);   // -1 then +1
        $this->assertSame(5.000, (float) $replacement->fresh()->stock_cached); // sold one
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'exchange_return']);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'exchange_sale']);
        $this->assertDatabaseHas('exchanges', ['id' => $exchange->id]);
    }
}
