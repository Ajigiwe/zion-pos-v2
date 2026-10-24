<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The stock ledger is read-only truth; the adjust form appends correction
 * movements (DAMAGE / ADJUSTMENT / PURCHASE / OPENING_STOCK / TRANSFER).
 */
class InventoryController extends Controller
{
    private const CORRECTIONS = [
        'opening_stock' => MovementType::OpeningStock,
        'purchase' => MovementType::Purchase,
        'damage' => MovementType::Damage,
        'adjustment' => MovementType::Adjustment,
        'transfer_in' => MovementType::TransferIn,
        'transfer_out' => MovementType::TransferOut,
    ];

    public function index(Request $request)
    {
        $movements = StockMovement::query()
            ->with(['product:id,sku,name,barcode', 'user:id,name'])
            ->when($request->input('product_id'), fn ($q, $v) => $q->where('product_id', (int) $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('movement_type', $v))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($request->input('search'), function ($q, $v) {
                $q->whereHas('product', fn ($p) => $p->where(function ($w) use ($v) {
                    $w->where('name', 'like', "%{$v}%")
                        ->orWhere('sku', 'like', "%{$v}%")
                        ->orWhere('barcode', 'like', "%{$v}%");
                }));
            })
            ->latest('created_at')
            ->limit(300)
            ->get();

        return view('inventory.index', [
            'movements' => $movements,
            'types' => MovementType::cases(),
            'filter' => $request->only(['product_id', 'type', 'from', 'to', 'search']),
            'productId' => $request->input('product_id'),
            // searchable product picker in the add/adjust modal
            'pickerProducts' => \App\Models\Product::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'stock_cached']),
        ]);
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'direction' => ['required', 'in:in,out'],
            'movement_type' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:movement_type,damage,adjustment'],
        ]);

        $type = self::CORRECTIONS[$data['movement_type']] ?? null;
        if (! $type) {
            // SALE / REFUND etc. come only from business transactions.
            return back()->with('error', 'Choose a correction movement type.');
        }

        $inbound = match ($data['movement_type']) {
            'opening_stock', 'purchase', 'transfer_in' => true,
            'damage', 'transfer_out' => false,
            default => $data['direction'] === 'in', // plain adjustment: either way
        };

        $product = Product::query()->lockForUpdate()->find($data['product_id']);

        if ($data['movement_type'] === 'adjustment') {
            // Physical count semantics: record the difference to reach the counted qty.
            $signed = (float) $data['quantity'] - (float) $product->stock_cached;

            if ($signed == 0) {
                return back()->with('error', "System stock already matches the physical count ({$product->stock_cached}).");
            }
        } else {
            $signed = ($inbound ? 1 : -1) * abs((float) $data['quantity']);
        }

        if ($product->stock_cached + $signed < 0) {
            return back()->with('error', "That would put {$product->sku} on hand at a negative total ({$product->stock_cached}).");
        }

        DB::transaction(function () use ($product, $signed, $type, $data, &$movementId) {
            $movement = $product->stockMovements()->create([
                'movement_type' => $type->value,
                'quantity' => $signed,
                'user_id' => auth()->id(),
                'reason' => $data['reason'] ?? null,
                'created_at' => now(),
            ]);

            $product->fill(['stock_cached' => $product->stock_cached + $signed])->save();
            $movementId = $movement->id;
        });

        AuditLog::record(
            'STOCK_'.Str::upper($type->value),
            auth()->id(),
            'stock_movement',
            $movementId,
            sprintf('%s × %s%s (%s)', $product->sku, $signed > 0 ? '+' : '', rtrim(rtrim((string) $signed, '0'), '.'), $data['reason'] ?? '—')
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => sprintf('%s: %s%s of %s', $type->label(), $signed > 0 ? '+' : '', rtrim(rtrim((string) $signed, '0'), '.'), $product->name),
                'new_stock' => (float) $product->stock_cached,
            ]);
        }

        return back()->with('success', sprintf('%s: %s%s of %s', $type->label(), $signed > 0 ? '+' : '', rtrim(rtrim((string) $signed, '0'), '.'), $product->name));
    }

    /** Opening stock wizard — lists only products with zero stock (Flutter parity). */
    public function openingWizard()
    {
        abort_unless(auth()->user()->can('inventory.adjust'), 403);

        $products = Product::query()->where('is_active', true)
            ->where('stock_cached', '<=', 0)
            ->orderBy('name')->get(['id', 'name', 'sku', 'stock_cached']);

        return view('inventory.opening', ['products' => $products]);
    }

    /** Bulk record opening stock from the wizard. */
    public function openingRecord(Request $request)
    {
        abort_unless(auth()->user()->can('inventory.adjust'), 403);

        $data = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entered = collect($data['quantities'] ?? [])->filter(fn ($q) => is_numeric($q) && (float) $q != 0);
        if ($entered->isEmpty()) {
            return back()->with('error', 'Enter at least one quantity.');
        }

        $count = 0;
        $units = 0.0;
        DB::transaction(function () use ($entered, &$count, &$units) {
            Product::query()->whereIn('id', $entered->keys())->lockForUpdate()->get()
                ->each(function (Product $product) use ($entered, &$count, &$units) {
                    $qty = (float) $entered[$product->id];
                    if ($qty <= 0) return;

                    $movement = $product->stockMovements()->create([
                        'movement_type' => 'opening_stock',
                        'quantity' => $qty,
                        'user_id' => auth()->id(),
                        'reason' => 'Opening stock',
                        'created_at' => now(),
                    ]);
                    $product->fill(['stock_cached' => $product->stock_cached + $qty])->save();
                    $count++;
                    $units += $qty;
                });
        });

        AuditLog::record('OPENING_STOCK', auth()->id(), 'stock_movement', null,
            "Opening stock recorded for {$count} product(s)");

        return redirect()->route('inventory.index')
            ->with('success', "Opening stock recorded for {$count} product(s) ({$units} units).");
    }

    /** Stock count sheet PDF (Flutter stock_sheet_document parity). */
    public function sheet()
    {
        $products = Product::query()->with(['category:id,name', 'brand:id,name'])->orderBy('name')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('inventory.stock_sheet', ['products' => $products, 'store' => store_info()])
            ->setPaper('a4', 'portrait');

        $filename = 'stock-count-'.now()->toDateString().'.pdf';

        return $pdf->stream($filename, ['Attachment' => false]);
    }
}
