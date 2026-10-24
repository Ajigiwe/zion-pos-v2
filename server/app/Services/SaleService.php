<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates point-of-sale transactions in one atomic unit — the online-port of
 * features/sales/data/sales_repository.dart. Used by the POS web form, the
 * offline PWA sync API and the exchange flow, so every write path shares the
 * same invariants:
 *
 *  - totals are always recomputed server-side from the price snapshot
 *  - stock is the ledger: one negative movement per line, cache re-derived
 *  - payments must total exactly what the server computed
 *  - offline requests may oversell but are flagged for admin review
 */
class SaleService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function createSale(User $user, array $data, array $options = []): Sale
    {
        $offline = $options['offline'] ?? false;
        $source = $offline ? 'offline_pwa' : ($options['source'] ?? 'web');
        $tillId = $options['till_id'] ?? null;

        $items = $this->validatedItems($data['items'] ?? []);

        return DB::transaction(function () use ($user, $data, $options, $items, $source, $offline, $tillId) {
            $products = Product::query()
                ->whereIn('id', array_column($items, 'product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = $this->buildLines($items, $products);
            $discount = max(0.0, (float) ($data['discount'] ?? 0));
            $subtotal = array_sum(array_column($lines, 'subtotal'));
            $total = max(0.0, round($subtotal - $discount, 2));
            $payments = $this->validatedPayments($data['payments'] ?? [], $total);

            // Online creates must not oversell unless the user can adjust stock;
            // offline creates may (PHP-PLAN §3) and are flagged instead.
            $oversold = false;
            if (! $offline && ! $user->can('inventory.adjust')) {
                foreach ($lines as $line) {
                    if ($line['product']->stock_cached < $line['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$line['product']->name} ({$line['product']->sku}): have {$line['product']->stock_cached}, want {$line['quantity']}.",
                        ]);
                    }
                }
            } else {
                foreach ($lines as $line) {
                    if ($line['product']->stock_cached < $line['quantity']) {
                        $oversold = true;
                    }
                }
            }

            $movementType = ($options['movement_type'] ?? MovementType::Sale)->value;

            $sale = Sale::create([
                'receipt_number' => $this->numbers->next('SALE'),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'uuid' => $data['uuid'] ?? null,
                'cashier_id' => $user->id,
                'till_id' => $options['till_id'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => 0.0, // tax-inclusive prices; informational only (§11)
                'total' => $total,
                'payment_status' => 'PAID',
                'sale_status' => 'COMPLETED',
                'needs_review' => $oversold,
                'source' => $source,
            ]);

            foreach ($lines as $line) {
                $item = $sale->items()->create($line['row'] + ['sale_id' => $sale->id]);
                $this->recordMovement(
                    product: $line['product'],
                    quantity: -$line['quantity'],
                    type: $movementType,
                    referenceType: 'sale_item',
                    referenceId: $item->id,
                    userId: $user->id,
                );
            }

            foreach ($payments as $payment) {
                $sale->payments()->create($payment);
            }

            $sale->refresh();

            $details = sprintf(
                '%s · %s · %d item(s)',
                $sale->receipt_number,
                money($sale->total),
                count($lines),
            );
            $auditAction = $options['audit_action'] ?? ($oversold ? 'SALE_OVERSOLD' : 'SALE');
            AuditLog::record($auditAction, $user->id, 'sale', $sale->id, $details, $tillId);

            return $sale;
        });
    }

    /** Deduplicate an offline sync retry by idempotency key. */
    public function findRepeated(string $idempotencyKey): ?Sale
    {
        return Sale::query()
            ->with(['items', 'payments'])
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function validatedItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one item before completing a sale.']);
        }

        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages(['items' => 'Item quantities must be positive.']);
            }
            if (empty($item['product_id'])) {
                throw ValidationException::withMessages(['items' => 'Every item needs a product.']);
            }
        }

        return $items;
    }

    private function buildLines(array $items, $products): array
    {
        $lines = [];

        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? null;

            // Hybrid: the offline client may reference a not-yet-catalogued SKU.
            if (! $product) {
                $product = Product::query()->where('sku', $item['sku'] ?? '')->first()
                    ?? Product::query()->where('barcode', $item['barcode'] ?? '')->first();
            }

            if (! $product || 0.0 === (float) $product->selling_price || ! $product->is_active) {
                throw ValidationException::withMessages(['items' => "Unknown product '{$item['sku']}'."]);
            }

            $lines[] = [
                'product' => $product,
                'quantity' => (float) $item['quantity'],
                'subtotal' => round($product->selling_price * (float) $item['quantity'], 2),
                'row' => [
                    'product_id' => $product->id,
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => $product->selling_price, // snapshot (§11)
                    'discount' => 0.0,
                    'tax' => 0.0,
                    'subtotal' => round($product->selling_price * (float) $item['quantity'], 2),
                ],
            ];
        }

        return $lines;
    }

    private function validatedPayments(array $payments, float $total): array
    {
        if ($total > 0 && $payments === []) {
            throw ValidationException::withMessages(['payments' => 'Record at least one payment.']);
        }

        $sum = array_sum(array_map(fn ($p) => (float) $p['amount'], $payments));

        if ($sum < $total - 0.01) {
            throw ValidationException::withMessages(['payments' => 'The recorded payments do not cover the sale total.']);
        }

        $excess = round($sum - $total, 2);
        if ($excess > 0.01) {
            $cashSum = 0.0;
            foreach ($payments as $p) {
                if (($p['method'] ?? '') === 'cash') {
                    $cashSum += (float) $p['amount'];
                }
            }
            if ($cashSum < $excess) {
                throw ValidationException::withMessages(['payments' => 'Only cash payments allow overpayment for change.']);
            }
        }

        foreach ($payments as $payment) {
            if (empty($payment['method'])) {
                throw ValidationException::withMessages(['payments' => 'Every payment needs a method.']);
            }
        }

        return array_map(fn ($p) => [
            'payment_method' => $p['method'],
            'amount' => $p['amount'],
            'reference' => $p['reference'] ?? null,
        ], $payments);
    }

    private function recordMovement(
        Product $product,
        float $quantity,
        string $type,
        string $referenceType,
        int $referenceId,
        int $userId,
        ?string $reason = null,
    ): void {
        $product->stockMovements()->create([
            'movement_type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => $userId,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $product->fill(['stock_cached' => $product->stock_cached + $quantity])->save();
    }
}
