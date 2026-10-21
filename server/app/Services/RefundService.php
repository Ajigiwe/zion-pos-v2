<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Refunds and exchanges — ports features/refunds/data/refunds_repository.dart.
 *
 * Invariants carried over from the Flutter app:
 *  - the original sale is NEVER modified or deleted (§14);
 *  - a cashiers' refund request starts PENDING and needs an admin approval;
 *    admin-created refunds complete immediately;
 *  - refunds are idempotent under the offline sync: unique idempotency_key;
 *  - each refunded sale line can never be refunded more than it sold.
 */
class RefundService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public const MAX_PER_REQUEST = 50;

    public function createRefund(
        Sale $original,
        User $user,
        array $lines,          // [['sale_item_id'=>.., 'quantity'=>..]]
        string $method,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        string $movementType = 'refund',   // exchange flow overrides
    ): Refund {
        return DB::transaction(function () use ($original, $user, $lines, $method, $reason, $idempotencyKey, $movementType) {
            if (count($lines) === 0 || count($lines) > self::MAX_PER_REQUEST) {
                throw ValidationException::withMessages(['lines' => 'Select 1–50 items to refund.']);
            }

            $saleItems = SaleItem::query()
                ->where('sale_id', $original->id)
                ->whereIn('id', array_column($lines, 'sale_item_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // How much of each line is already refunded, pending or approved.
            $alreadyRefunded = RefundItem::query()
                ->whereIn('sale_item_id', array_column($lines, 'sale_item_id'))
                ->join('refunds', 'refund_items.refund_id', '=', 'refunds.id')
                ->groupBy('refund_items.sale_item_id')
                ->selectRaw('refund_items.sale_item_id, SUM(refund_items.quantity) as qty')
                ->pluck('qty', 'sale_item_id');

            $total = 0.0;
            foreach ($lines as $line) {
                $saleItem = $saleItems[$line['sale_item_id']] ?? null;
                if (! $saleItem) {
                    throw ValidationException::withMessages(['lines' => 'That line does not belong to the original sale.']);
                }

                $qty = (float) $line['quantity'];
                $remaining = (float) $saleItem->quantity - (float) ($alreadyRefunded[$saleItem->id] ?? 0);
                if ($qty <= 0 || $qty > $remaining + 0.0001) {
                    throw ValidationException::withMessages([
                        'lines' => "Can only refund {$remaining} of this line ({$remaining} remaining of {$saleItem->quantity}).",
                    ]);
                }

                $total += (float) $saleItem->unit_price * $qty;
            }

            $approved = $user->can('refunds.approve');

            $refund = Refund::create([
                'refund_number' => $this->numbers->next('REF'),
                'idempotency_key' => $idempotencyKey,
                'original_sale_id' => $original->id,
                'cashier_id' => $user->id,
                'refund_method' => $method,
                'amount' => round($total, 2),
                'reason' => $reason,
                'status' => $approved ? 'APPROVED' : 'PENDING', // cashier requests wait
            ]);
            foreach ($lines as $line) {
                $saleItem = $saleItems[$line['sale_item_id']];
                $qty = (float) $line['quantity'];

                RefundItem::create([
                    'refund_id' => $refund->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $qty,
                    'unit_price' => $saleItem->unit_price,
                    'subtotal' => round($saleItem->unit_price * $qty, 2),
                ]);
            }

            if ($approved) {
                $this->postStockReturns($refund, $user, $movementType, $original);
            }

            $details = sprintf(
                '%s · %s from %s · %s',
                $refund->refund_number,
                money($refund->amount),
                $original->receipt_number,
                $approved ? 'APPROVED' : 'PENDING approval',
            );
            AuditLog::record($approved ? ($movementType === 'refund' ? 'REFUND' : 'EXCHANGE') : 'REFUND_REQUEST', $user->id, 'refund', $refund->id, $details);

            return $refund;
        });
    }

    /** Admin approves a pending request — this is when stock and money move. */
    public function approve(Refund $refund, User $approver): Refund
    {
        if (! $approver->can('refunds.approve')) {
            throw ValidationException::withMessages(['refund' => 'Only admins can approve refunds.']);
        }

        return DB::transaction(function () use ($refund, $approver) {
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->first();

            if ($refund->status === 'APPROVED') {
                throw ValidationException::withMessages(['refund' => 'This refund is already approved.']);
            }

            $original = Sale::query()->lockForUpdate()->find($refund->original_sale_id);
            $refund->status = 'APPROVED';
            $refund->save();

            $this->postStockReturns($refund, $approver, 'refund', $original);

            AuditLog::record('REFUND_APPROVED', $approver->id, 'refund', $refund->id, sprintf('%s approved · %s', $refund->refund_number, money($refund->amount)));

            return $refund;
        });
    }

    public function reject(Refund $refund, User $approver): Refund
    {
        $refund->status = 'REJECTED';
        $refund->save();

        AuditLog::record('REFUND_REJECTED', $approver->id, 'refund', $refund->id, sprintf('%s rejected', $refund->refund_number));

        return $refund;
    }

    /** Amount already refunded against one sale (approved refunds only). */
    public function refundedForSale(Sale $sale): float
    {
        return (float) Refund::query()
            ->where('original_sale_id', $sale->id)
            ->where('status', 'APPROVED')
            ->sum('amount');
    }

    /** Movement type differs between plain refund and exchange (§15). */
    private function postStockReturns(Refund $refund, User $user, string $movementType, Sale $original): void
    {
        foreach ($refund->items as $item) {
            $product = Product::query()->lockForUpdate()->find($item->product_id);

            $item->product->stockMovements()->create([
                'movement_type' => $movementType,
                'quantity' => $item->quantity,
                'reference_type' => 'refund_item',
                'reference_id' => $item->id,
                'user_id' => $user->id,
                'reason' => $refund->reason,
                'created_at' => now(),
            ]);

            $product->fill(['stock_cached' => $product->stock_cached + (float) $item->quantity])->save();
        }
    }
}
