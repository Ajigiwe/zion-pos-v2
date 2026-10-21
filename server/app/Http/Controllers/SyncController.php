<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Refund;
use App\Models\Sale;
use App\Services\RefundService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Same-origin sync endpoints used by the offline PWA queue (PHP-PLAN §3).
 *
 *  GET  /api/snapshot  — catalog + stock + settings, drives the till cache
 *  POST /api/sales     — ingest one queued sale or refund (idempotent)
 *  GET  /api/ping      — true connectivity probe (offline.js uses it)
 */
class SyncController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly RefundService $refunds,
    ) {}

    public function snapshot(): JsonResponse
    {
        $user = auth()->user();

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'price' => (float) $p->selling_price,
                'stock' => (float) $p->stock_cached,
                'reorder_level' => (float) $p->reorder_level,
                'tax_rate' => (float) $p->tax_rate,
                'category' => $p->category?->name,
                'brand' => $p->brand?->name,
                'active' => $p->is_active,
            ]);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role_label(),
                'can_approve_refunds' => $user->can('refunds.approve'),
            ],
            'store' => store_info(),
            'till' => config('pos.till_id', 'Web-Till-1'),
            'products' => $products,
        ]);
    }

    public function storeSale(Request $request): JsonResponse
    {
        $kind = $request->header('X-POS-Kind', 'sale');

        return $kind === 'refund' ? $this->storeRefund($request) : $this->storeSaleAction($request);
    }

    private function storeSaleAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.sku' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'string'],
            'payments.*.amount' => ['required_with:payments', 'numeric'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ]);

        // Force offline semantics: PWA tills may oversell (flagged for review);
        // the idempotency key comes from the header so retries dedupe.
        $options = ['offline' => true, 'till_id' => config('pos.till_id')];
        $data['idempotency_key'] = $request->header('X-Idempotency-Key');

        try {
            $sale = $this->sales->createSale($request->user(), $data, $options);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Duplicate idempotency key — the original is the canonical sale.
            $existing = Sale::query()->where('idempotency_key', $request->header('X-Idempotency-Key'))->first();

            return response()->json(['sale' => $existing->load('items', 'payments'), 'duplicate' => true], 409);
        }

        return response()->json(['sale' => $sale->load('items', 'payments')], 201);
    }

    private function storeRefund(Request $request): JsonResponse
    {
        $key = $request->header('X-Idempotency-Key');

        $existing = Refund::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return response()->json(['refund' => $existing->load('items'), 'duplicate' => true], 409);
        }

        $data = $request->validate([
            'original_sale_id' => ['required_without:receipt_number', 'integer'],
            'receipt_number' => ['required_without:original_sale_id', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $original = $data['original_sale_id']
            ? Sale::find($data['original_sale_id'])
            : Sale::query()->where('receipt_number', $data['receipt_number'])->first();

        if (! $original) {
            return response()->json(['message' => 'Original sale not found for refund.'], 422);
        }

        $refund = $this->refunds->createRefund(
            original: $original,
            user: $request->user(),
            lines: $data['lines'],
            method: $data['method'],
            reason: $data['reason'] ?? null,
            idempotencyKey: $key,
        );

        return response()->json(['refund' => $refund->load('items')], 201);
    }

    public function ping(): JsonResponse
    {
        // A read against the database proves more than a 200 response.
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            return response()->json(['ok' => false], 503);
        }

        return response()->json(['ok' => true]);
    }
}
