<?php

namespace App\Http\Controllers;

use App\Models\Refund;
use App\Models\Sale;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    public function index()
    {
        $user = auth()->user();
        $canRefund = $user->can('refunds.approve');
        $canExchange = $user->can('refunds.approve') || $user->can('pos.use');

        $salesForPicker = Sale::query()->where('sale_status', 'COMPLETED')
            ->orderByDesc('created_at')->limit(50)->get(['id', 'receipt_number', 'total', 'created_at']);

        $refunds = Refund::query()
            ->with(['originalSale:id,receipt_number,total', 'cashier:id,name', 'items'])
            ->orderByRaw("FIELD(status, 'PENDING', 'APPROVED', 'REJECTED')")
            ->latest('created_at')
            ->limit(60)
            ->get();

        $exchanges = \App\Models\Exchange::query()->latest('created_at')->limit(30)
            ->with(['originalSale:id,receipt_number', 'replacementSale:id,receipt_number'])->get();

        return view('refunds.index', [
            'sales' => $salesForPicker,
            'refunds' => $refunds,
            'exchanges' => $exchanges,
            'canRefund' => $canRefund,
            'canExchange' => $canExchange,
            'canApprove' => $canRefund,
        ]);
    }

    /** Look up a completed sale for the refund/exchange forms. */
    public function find(Request $request)
    {
        $term = trim((string) $request->input('receipt', ''));
        $sale = $term === ''
            ? null
            : Sale::query()->where('receipt_number', $term)->first();

        if (! $sale) {
            return back()->with('error', "Sale '{$term}' not found — check the receipt number.");
        }

        $sale->load(['items.product:id,name,sku,barcode', 'payments', 'refunds' => fn ($q) => $q->where('status', 'APPROVED')->with('items')]);

        return view('refunds.create', ['sale' => $sale]);
    }

    /** Flutter-refund_flow_page parity wizard. */
    public function flow(Request $request, Sale $sale)
    {
        $mode = $request->input('mode') === 'refund' ? 'refund' : 'exchange';

        $sale->load(['items.product:id,name,sku,barcode,selling_price', 'refunds' => fn ($q) => $q->where('status', '!=', 'REJECTED')->with('items')]);
        $alreadyRefunded = [];
        foreach ($sale->refunds as $refund) {
            foreach ($refund->items as $item) {
                $alreadyRefunded[$item->sale_item_id] = ($alreadyRefunded[$item->sale_item_id] ?? 0) + (float) $item->quantity;
            }
        }

        $catalog = $mode === 'exchange'
            ? \App\Models\Product::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'sku', 'selling_price', 'stock_cached'])
            : collect();

        // Pre-mapped for JS: Blade cannot compile inline `fn () => [...]` inside @json().
        $catalogForJs = $catalog->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
            'price' => (float) $p->selling_price, 'stock' => (float) $p->stock_cached,
        ])->values()->all();

        $currency = $catalog->isEmpty() ? store_info()['currency'] : store_info()['currency'];

        return view('refunds.flow', [
            'mode' => $mode,
            'sale' => $sale,
            'lines' => $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => optional($item->product)->name ?? ($item->name ?? 'Item'),
                'sku' => optional($item->product)->sku,
                'unit' => (float) $item->unit_price,
                'qty' => (float) $item->quantity,
                'remaining' => max(0, (float) $item->quantity - (float) ($alreadyRefunded[$item->id] ?? 0)),
            ])->all(),
            'catalog' => $catalog,
            'catalogForJs' => $catalogForJs,
            'currency' => store_info()['currency'],
            'canApprove' => auth()->user()->can('refunds.approve'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'original_sale_id' => ['required', 'integer', 'exists:sales,id'],
            'lines' => ['required'],
            'method' => ['required', 'in:cash,mobile_money,card,bank_transfer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $lines = $this->decodeLines($data['lines'], 'sale_item_id');

        $sale = Sale::findOrFail($data['original_sale_id']);
        $refund = $this->refunds->createRefund($sale, $request->user(), collect($lines)->map(fn ($l) => [
            'sale_item_id' => (int) $l['sale_item_id'],
            'quantity' => (float) $l['quantity'],
        ])->all(), $data['method'], $data['reason'] ?? null);

        return $refund->status === 'APPROVED'
            ? redirect()->route('refunds.index')->with('success', "Refund {$refund->refund_number} processed — stock returned.")
            : redirect()->route('refunds.index')->with('success', "Refund request {$refund->refund_number} sent for admin approval.");
    }

    /** The flow wizard posts JSON-encoded line arrays; normalize both shapes. */
    private function decodeLines(mixed $lines, string $idKey): array
    {
        if (is_string($lines)) {
            $lines = json_decode($lines, true) ?? [];
        }

        return collect($lines)->map(fn ($l) => [$idKey => (int) $l[$idKey], ...($l + [$idKey => null])])->all();
    }

    public function approve(Request $request, Refund $refund)
    {
        $this->refunds->approve($refund, $request->user());

        return back()->with('success', "Refund {$refund->refund_number} approved — stock has been returned to the ledger.");
    }

    public function reject(Request $request, Refund $refund)
    {
        $this->refunds->reject($refund, $request->user());

        return back()->with('success', "Refund request denied.");
    }
}
