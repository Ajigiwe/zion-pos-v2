<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    /** Map a Sale (or standalone refund doc) into the receipt partial's $r data. */
    public static function forSale(Sale $sale): array
    {
        $sale->load(['items.product', 'payments', 'cashier']);

        $tax = (float) $sale->tax;
        $payments = $sale->payments->map(fn ($p) => [
            'method' => $p->payment_method,
            'amount' => (float) $p->amount,
            'ref' => $p->reference,
        ])->all();

        $paid = array_sum(array_column($payments, 'amount'));
        $due = (float) $sale->total;
        $change = max(0, $paid - $due);

        return [
            'kind' => 'sale',
            'title' => 'RECEIPT',
            'number' => $sale->receipt_number,
            'date' => $sale->created_at?->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
            'cashier' => $sale->cashier?->name,
            'ref_number' => null,
            'items' => $sale->items->map(fn ($i) => [
                'name' => $i->name ?? optional($i->product)->name ?? 'Item',
                'qty' => (float) $i->quantity,
                'unit' => (float) $i->unit_price,
                'total' => (float) $i->subtotal,
            ])->all(),
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'tax' => $tax,
            'total' => $due,
            'payments' => $payments,
            'change' => $change,
            'needs_review' => (bool) $sale->needs_review,
            'refunds' => collect($sale->refunds ?? [])->map(fn ($rf) => [
                'number' => $rf->refund_number, 'amount' => (float) $rf->amount, 'reason' => $rf->reason,
            ])->all(),
        ];
    }

    /** Reprint a completed sale's receipt (thermal layout, browser print). */
    public function show(Sale $sale)
    {
        return view('receipts.show', [
            'sale' => $sale->load(['items.product', 'payments', 'cashier', 'refunds' => fn ($q) => $q->where('status', 'APPROVED')]),
            'r' => self::forSale($sale),
            'store' => store_info(),
        ]);
    }

    /** Archival PDF copy via dompdf. Standalone wrapper keeps it session-free. */
    public function pdf(Sale $sale): Response
    {
        $store = store_info();
        // 80 mm -> 226.8 pt wide; 58 mm -> 164.4 pt (thermal rolls)
        $widthPt = ($store['paper_width'] ?? '80') === '58' ? 164.4 : 226.8;
        $r = self::forSale($sale);

        return Pdf::loadView('receipts.pdf', [
            'r' => $r,
            'store' => $store,
        ])->setPaper([0, 0, $widthPt, 792], 'portrait')->download($sale->receipt_number.'.pdf');
    }

    /** Printer test receipt (Flutter parity: test receipt TEST-001). */
    public function testReceipt()
    {
        $r = [
            'kind' => 'test',
            'title' => 'RECEIPT',
            'number' => 'TEST-001',
            'date' => now()->format('Y-m-d H:i'),
            'cashier' => auth()->user()->name,
            'items' => [['name' => 'Thermal Printer Hardware Test', 'qty' => 0, 'unit' => 0, 'total' => 0]],
            'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0,
            'payments' => [], 'change' => 0, 'needs_review' => false,
        ];

        return response()->view('receipts.test', ['r' => $r, 'store' => store_info()])
            ->header('X-Auto-Print', '1');
    }
}
