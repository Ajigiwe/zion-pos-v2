<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Flutter: Today / Last 7 days / Last 30 days (default Today). */
    private function range(string $key): array
    {
        $today = CarbonImmutable::today();

        return match ($key) {
            '7' => [$today->subDays(6)->startOfDay(), $today->endOfDay(), 'Last 7 days'],
            '30' => [$today->subDays(29)->startOfDay(), $today->endOfDay(), 'Last 30 days'],
            default => [$today->startOfDay(), $today->endOfDay(), 'Today'],
        };
    }

    public function index(\Illuminate\Http\Request $request)
    {
        [$from, $to, $label] = $this->range((string) $request->input('range', 'today'));
        $key = (string) $request->input('range', 'today');

        $sales = Sale::query()->where('sales.sale_status', 'COMPLETED')
            ->whereBetween('sales.created_at', [$from, $to])
            ->with(['payments', 'cashier:id,name', 'items.product:id,name,sku', 'items:quantity'])
            ->get();
        $refunds = Refund::query()->where('status', 'APPROVED')
            ->whereBetween('refunds.created_at', [$from, $to])
            ->with(['cashier:id,name'])
            ->get();
        $refundItems = RefundItem::query()
            ->whereIn('refund_id', $refunds->pluck('id'))
            ->with('saleItem.product:id,name,sku')
            ->get();

        $data = $this->aggregate($sales, $refunds, $refundItems);

        if ($request->input('export') === 'csv') {
            return $this->export($label, $data);
        }

        return view('reports.index', [
            'label' => $label,
            'key' => $key,
        ] + $data);
    }

    private function aggregate($sales, $refunds, $refundItems): array
    {
        $gross = (float) $sales->sum('total');
        $discounts = (float) $sales->sum('discount');
        $refundTotal = (float) $refunds->sum('amount');

        $paidByMethod = [];
        foreach ($sales as $sale) {
            foreach ($sale->payments as $payment) {
                $m = $paidByMethod[$payment->payment_method] ??= ['amount' => 0.0, 'count' => 0];
                $m['amount'] += (float) $payment->amount;
                $m['count']++;
                $paidByMethod[$payment->payment_method] = $m;
            }
        }

        $refundedByMethod = [];
        foreach ($refunds as $refund) {
            $m = $refundedByMethod[$refund->refund_method] ??= ['amount' => 0.0, 'count' => 0];
            $m['amount'] += (float) $refund->amount;
            $m['count']++;
            $refundedByMethod[$refund->refund_method] = $m;
        }

        $byCashier = $sales->groupBy(fn ($s) => $s->cashier?->name ?? 'Unknown')
            ->map(fn ($g) => [
                'name' => $g->first()->cashier?->name ?? 'Unknown',
                'count' => $g->count(),
                'total' => (float) $g->sum('total'),
            ])->sortByDesc('total')->values();

        $topProducts = [];
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $name = optional($item->product)->name ?? ($item->name ?? 'Item');
                $sku = optional($item->product)->sku ?? '';
                $key = $sku.'|'.$name;
                $topProducts[$key] ??= ['sku' => $sku, 'name' => $name, 'qty' => 0.0, 'revenue' => 0.0];
                $topProducts[$key]['qty'] += (float) $item->quantity;
                $topProducts[$key]['revenue'] += (float) ($item->subtotal ?? ($item->unit_price * $item->quantity));
            }
        }
        $topProducts = collect($topProducts)->sortByDesc('revenue')->values();

        $refundsByCashier = $refunds->groupBy(fn ($r) => $r->cashier?->name ?? 'Unknown')
            ->map(fn ($g) => [
                'name' => $g->first()->cashier?->name ?? 'Unknown',
                'count' => $g->count(),
                'total' => (float) $g->sum('amount'),
            ])->sortByDesc('total')->values();

        $refundedProducts = [];
        foreach ($refundItems as $item) {
            $product = optional($item->saleItem)->product;
            $key = $product?->name ?? 'Unknown';
            $refundedProducts[$key] ??= ['name' => $key, 'qty' => 0.0, 'amount' => 0.0];
            $refundedProducts[$key]['qty'] += (float) $item->quantity;
            $refundedProducts[$key]['amount'] += (float) (($product?->selling_price ?? 0) * (float) $item->quantity);
        }
        $refundedProducts = collect($refundedProducts)->sortByDesc('amount')->values();

        $reasons = $refunds->filter(fn ($r) => filled($r->reason))->groupBy('reason')
            ->map(fn ($g) => ['reason' => $g->first()->reason, 'count' => $g->count(), 'amount' => (float) $g->sum('amount')])
            ->sortByDesc('amount')->values();

        return [
            'gross' => $gross,
            'discounts' => $discounts,
            'refundTotal' => $refundTotal,
            'net' => $gross - $refundTotal,
            'salesCount' => $sales->count(),
            'refundsCount' => $refunds->count(),
            'paidByMethod' => $paidByMethod,
            'refundedByMethod' => $refundedByMethod,
            'byCashier' => $byCashier,
            'topProducts' => $topProducts,
            'refundsByCashier' => $refundsByCashier,
            'refundedProducts' => $refundedProducts,
            'reasons' => $reasons,
        ];
    }

    private function export(string $label, array $d): StreamedResponse
    {
        $name = 'report-'.str_replace(' ', '-', strtolower($label)).'.csv';

        return response()->streamDownload(function () use ($label, $d) {
            $out = fopen('php://output', 'w');
            $row = fn (...$cells) => fputcsv($out, $cells);
            $blank = fn () => fputcsv($out, []);
            $cur = fn ($v) => money($v);

            $row('Report,Financial reconciliation');
            $row('Period,"'.$label.'"');
            $row('Generated,"'.now()->toIso8601String().'"');
            $row('Total Sales,'.$cur($d['gross']));
            $row('Discounts Given,'.$cur($d['discounts']));
            $row('Sales Completed,'.$d['salesCount']);
            $row('Refunds,'.$cur($d['refundTotal']));
            $row('Net Sales,'.$cur($d['net']));
            $blank();

            $row('Payments by Method,Amount,Count');
            foreach ($d['paidByMethod'] as $method => $m) {
                if ($m['amount'] == 0 && $m['count'] == 0) continue;
                $row(\Illuminate\Support\Str::title(str_replace('_', ' ', $method)).','.$cur($m['amount']).','.$m['count']);
            }
            $blank();

            $row('Refunds by Method,Amount,Count');
            foreach ($d['refundedByMethod'] as $method => $m) {
                if ($m['amount'] == 0 && $m['count'] == 0) continue;
                $row(\Illuminate\Support\Str::title(str_replace('_', ' ', $method)).','.$cur($m['amount']).','.$m['count']);
            }
            $blank();

            $row('Sales by Cashier');
            $row('Cashier,Sales,Total (GHS)');
            foreach ($d['byCashier'] as $c) $row($c['name'].','.$c['count'].','.$cur($c['total']));
            $blank();

            $row('Sales by Product');
            $row('SKU,Product,Qty,Revenue (GHS)');
            foreach ($d['topProducts'] as $p) $row($p['sku'].','.$p['name'].','.rtrim(rtrim(number_format($p['qty'], 3), '0'), '.').','.$cur($p['revenue']));
            $blank();

            $row('Refunds by Cashier');
            $row('Cashier,Refunds,Total (GHS)');
            foreach ($d['refundsByCashier'] as $c) $row($c['name'].','.$c['count'].','.$cur($c['total']));
            $blank();

            $row('Refunds by Product');
            $row('Product,Qty,Amount (GHS)');
            foreach ($d['refundedProducts'] as $p) $row($p['name'].','.rtrim(rtrim(number_format($p['qty'], 3), '0'), '.').','.$cur($p['amount']));
            $blank();

            $row('Refund Reasons');
            $row('Reason,Refunds,Amount (GHS)');
            foreach ($d['reasons'] as $r) $row($r['reason'].','.$r['count'].','.$cur($r['amount']));

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Inventory valuation CSV (kept from the original build). */
    public function exportInventory(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['sku', 'barcode', 'name', 'category', 'brand', 'cost_price', 'selling_price', 'stock_cached', 'stock_value_cost', 'reorder_level', 'is_active']);
            Product::query()->with(['category:id,name', 'brand:id,name'])->orderBy('name')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->sku, $p->barcode, $p->name, $p->category?->name, $p->brand?->name,
                        (float) $p->cost_price, (float) $p->selling_price, (float) $p->stock_cached,
                        round((float) $p->stock_cached * (float) $p->cost_price, 2),
                        $p->reorder_level, $p->is_active ? 'active' : 'inactive',
                    ]);
                }
            });
            fclose($out);
        }, 'inventory-'.now()->toDateString().'.csv', ['Content-Type' => 'text/csv']);
    }
}
