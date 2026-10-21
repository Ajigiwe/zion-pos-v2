<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Exchange;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product exchanges (§15): return goods from an original sale against a
 * replacement sale. Stock moves as EXCHANGE_RETURN (in) / EXCHANGE_SALE (out)
 * and the replacement's payments include the returned goods' value as
 * exchange_credit. Admins only — cashiers raise refund requests instead.
 */
class ExchangeService
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly RefundService $refunds,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<int, array{sale_item_id: int, quantity: float}>  $returnLines
     * @param  array<int, array{product_id: int, quantity: float}>  $replacementLines
     * @return array{0: Exchange, 1: \App\Models\Refund, 2: Sale}
     */
    public function createExchange(Sale $original, User $user, array $returnLines, array $replacementLines, string $differenceMethod = 'cash'): array
    {
        if (! $user->can('exchanges.process')) {
            throw ValidationException::withMessages(['exchange' => 'You do not have permission to process exchanges.']);
        }

        if ($replacementLines === []) {
            throw ValidationException::withMessages(['replacement' => 'Add the replacement item(s).']);
        }

        // Replacement prices resolve from the same snapshot rules as the POS.
        $products = Product::query()->whereIn('id', array_column($replacementLines, 'product_id'))->get()->keyBy('id');
        $replacementTotal = 0.0;
        foreach ($replacementLines as $line) {
            $product = $products[$line['product_id']] ?? null;
            if (! $product || (float) $line['quantity'] <= 0) {
                throw ValidationException::withMessages(['replacement' => 'Invalid replacement line.']);
            }
            $replacementTotal += (float) $product->selling_price * (float) $line['quantity'];
        }
        $replacementTotal = round($replacementTotal, 2);

        return DB::transaction(function () use ($original, $user, $returnLines, $replacementLines, $differenceMethod, $replacementTotal) {
            // 1) Goods come back in (EXCHANGE_RETURN) via an approved refund.
            $refund = $this->refunds->createRefund(
                original: $original,
                user: $user,
                lines: $returnLines,
                method: 'cash',
                reason: 'Exchange',
                movementType: MovementType::ExchangeReturn->value,
            );

            // 2) Replacement goods go out (EXCHANGE_SALE) as a sale whose
            //    first payment row is the returned-goods value (exchange_credit).
            $credit = min((float) $refund->amount, $replacementTotal);
            $difference = round($replacementTotal - (float) $refund->amount, 2);

            $payments = [['method' => 'exchange_credit', 'amount' => $credit, 'reference' => $refund->refund_number]];
            if ($difference > 0.0) {
                $payments[] = ['method' => $differenceMethod, 'amount' => $difference, 'reference' => 'exchange difference'];
            }

            $replacement = $this->sales->createSale($user, [
                'items' => $replacementLines,
                'payments' => $payments,
            ], [
                'movement_type' => MovementType::ExchangeSale,
                'source' => 'exchange',
                'audit_action' => 'EXCHANGE_SALE',
            ]);

            // 3) The exchange record links the three documents.
            $exchange = Exchange::create([
                'exchange_number' => $this->numbers->next('EX'),
                'original_sale_id' => $original->id,
                'refund_id' => $refund->id,
                'replacement_sale_id' => $replacement->id,
                'return_total' => $refund->amount,
                'replacement_total' => $replacement->total,
                'difference' => $difference,
            ]);

            AuditLog::record('EXCHANGE', $user->id, 'exchange', $exchange->id, sprintf(
                '%s · %s ⇄ %s · difference %s',
                $exchange->exchange_number,
                $original->receipt_number,
                $replacement->receipt_number,
                money($difference),
            ));

            return [$exchange, $refund, $replacement];
        });
    }
}
