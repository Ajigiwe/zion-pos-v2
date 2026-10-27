<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Ranges the dashboard filter offers (mirrors Reports). */
    private const RANGES = [
        'today' => [0, 'Today'],
        '7' => [6, 'Last 7 days'],
        '30' => [29, 'Last 30 days'],
    ];

    public function __invoke(\Illuminate\Http\Request $request)
    {
        $user = $request->user();

        // Cashiers land on the till (parity: app opens on Sales).
        if (! $user->can('reports.view') && $user->can('pos.use')) {
            return redirect()->route('pos');
        }

        $key = $request->input('range');
        if (! is_string($key) || ! array_key_exists($key, self::RANGES)) {
            $key = 'today';
        }
        [$daysBack, $label] = self::RANGES[$key];

        $today = Carbon::today();
        $start = $today->copy()->subDays($daysBack)->startOfDay();
        $end = $today->copy()->endOfDay();
        $sales = Sale::query()->where('sale_status', 'COMPLETED')
            ->whereBetween('created_at', [$start, $end])
            ->with('payments')->get();

        $gross = (float) $sales->sum('subtotal');
        $discounts = (float) $sales->sum('discount');

        $paidByMethod = [];
        foreach ($sales as $sale) {
            foreach ($sale->payments as $payment) {
                $paidByMethod[$payment->payment_method] ??= ['amount' => 0.0, 'count' => 0];
                $paidByMethod[$payment->payment_method]['amount'] += (float) $payment->amount;
                $paidByMethod[$payment->payment_method]['count'] += 1;
            }
        }

        // Refunds in the same period (approved), split by method.
        $refunds = Refund::query()->where('status', 'APPROVED')
            ->whereBetween('created_at', [$start, $end])
            ->get(['refund_method', 'amount']);

        $refundedByMethod = [];
        foreach ($refunds as $refund) {
            $refundedByMethod[$refund->refund_method] ??= ['amount' => 0.0, 'count' => 0];
            $refundedByMethod[$refund->refund_method]['amount'] += (float) $refund->amount;
            $refundedByMethod[$refund->refund_method]['count'] += 1;
        }

        // Flutter: takings = cash + mobile money payments minus same-method refunds.
        $cashPaid = $paidByMethod['cash']['amount'] ?? 0.0;
        $momoPaid = $paidByMethod['mobile_money']['amount'] ?? 0.0;
        $cashRefunded = $refundedByMethod['cash']['amount'] ?? 0.0;
        $momoRefunded = $refundedByMethod['mobile_money']['amount'] ?? 0.0;
        $takings = max(0, ($cashPaid - $cashRefunded) + ($momoPaid - $momoRefunded));

        $threshold = low_stock_threshold();
        $lowStock = Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($threshold) {
                $q->where(function ($q2) {
                    $q2->whereColumn('stock_cached', '<=', 'reorder_level')
                       ->where('reorder_level', '>', 0);
                })->orWhere(function ($q2) use ($threshold) {
                    $q2->whereNull('reorder_level')->orWhere('reorder_level', '<=', 0);
                    $q2->where('stock_cached', '<=', $threshold);
                });
            })
            ->orderBy('stock_cached')
            ->limit(50)
            ->get(['id', 'name', 'sku', 'stock_cached', 'reorder_level']);

        return view('dashboard.index', [
            'day' => $today,
            'rangeKey' => $key,
            'rangeLabel' => $label,
            'gross' => $gross,
            'discounts' => $discounts,
            'salesCount' => $sales->count(),
            'takings' => $takings,
            'cash' => $cashPaid, 'cashCount' => $paidByMethod['cash']['count'] ?? 0,
            'momo' => $momoPaid, 'momoCount' => $paidByMethod['mobile_money']['count'] ?? 0,
            'refunds' => $refunds, 'cashRefunded' => $cashRefunded, 'momoRefunded' => $momoRefunded,
            'refundsCount' => $refunds->count(),
            'lowStock' => $lowStock,
            'threshold' => $threshold,
        ]);
    }
}
