<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The till (PHP-PLAN Phase 4). The browser owns the cart; the server owns
 * the transaction. Online checkout goes through /pos for instant numbering;
 * offline checkout is queued in IndexedDB and syncs via /api/sales.
 */
class POSController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request)
    {
        $products = \App\Models\Product::query()
            ->where('is_active', true)
            ->with(['category:id,name', 'brand:id,name'])
            ->orderBy('name')
            ->get(['id', 'sku', 'barcode', 'name', 'selling_price', 'stock_cached', 'reorder_level', 'category_id', 'brand_id', 'tax_rate', 'is_active', 'tracking_type']);

        $categories = \App\Models\Category::query()->orderBy('name')->get(['id', 'name']);

        return view('pos.index', [
            'products' => $products,
            'categories' => $categories,
            'store' => store_info(),
            'till' => Setting::get('pos.till_id_display', config('pos.till_id')),
            'canApproveRefunds' => $request->user()->can('refunds.approve'),
        ]);
    }

    /** History mode — recent sales (Flutter Sales ▸ History). */
    public function history()
    {
        $sales = \App\Models\Sale::query()->where('sale_status', 'COMPLETED')
            ->orderByDesc('created_at')->limit(60)
            ->get(['id', 'receipt_number', 'total', 'created_at']);

        return response()->json(['sales' => $sales->map(fn ($s) => [
            'id' => $s->id, 'receipt_number' => $s->receipt_number,
            'total' => (float) $s->total, 'created_at' => $s->created_at->format('Y-m-d H:i'),
        ])->all()]);
    }

    /** Single sale detail (dialog). */
    public function detail(\App\Models\Sale $sale)
    {
        $sale->load(['items', 'payments']);

        return response()->json([
            'id' => $sale->id,
            'receipt_number' => $sale->receipt_number,
            'created_at' => $sale->created_at->format('Y-m-d H:i'),
            'items' => $sale->items->map(fn ($i) => [
                'name' => $i->name ?? optional($i->product)->name,
                'quantity' => (float) $i->quantity,
                'amount' => (float) $i->subtotal,
            ])->all(),
            'total' => (float) $sale->total,
            'payments' => $sale->payments->map(fn ($p) => [
                'method' => $p->payment_method, 'amount' => (float) $p->amount,
            ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string'],
            'payments.*.amount' => ['required', 'numeric'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = $this->sales->createSale($request->user(), $data, [
            'source' => 'web',
            'till_id' => config('pos.till_id'),
        ]);

        $sale->load('items.product:id,name,sku,barcode', 'payments');
        $paid = (float) $sale->payments->sum('amount');
        $sale->change = max(0.0, round($paid - (float) $sale->total, 2));

        return response()->json(['sale' => $sale], 201);
    }
}
