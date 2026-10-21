<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ExchangeService;
use Illuminate\Http\Request;

class ExchangeController extends Controller
{
    public function __construct(private readonly ExchangeService $exchanges) {}

    public function show(Request $request)
    {
        $term = trim((string) $request->input('receipt', ''));
        $sale = $term === ''
            ? null
            : Sale::query()->where('receipt_number', $term)->first();

        if ($term !== '' && ! $sale) {
            $request->session()->flash('error', "Sale '{$term}' not found.");
        }

        $sale?->load(['items.product:id,name,sku,barcode,selling_price', 'refunds' => fn ($q) => $q->where('status', 'APPROVED')]);
        $catalog = $sale ? \App\Models\Product::query()->where('is_active', true)->where('stock_cached', '>', 0)->orderBy('name')->get(['id', 'name', 'sku']) : collect();

        return view('exchanges.create', compact('sale', 'catalog', 'term'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'original_sale_id' => ['required', 'integer', 'exists:sales,id'],
            'return_lines' => ['required'],
            'replacement_lines' => ['required'],
            'difference_method' => ['required', 'in:cash,mobile_money,card,bank_transfer'],
        ]);

        $returnLines = is_string($data['return_lines']) ? (json_decode($data['return_lines'], true) ?? []) : $data['return_lines'];
        $replacementLines = is_string($data['replacement_lines']) ? (json_decode($data['replacement_lines'], true) ?? []) : $data['replacement_lines'];

        $sale = Sale::findOrFail($data['original_sale_id']);

        [$exchange, $refund, $replacement] = $this->exchanges->createExchange(
            $sale,
            $request->user(),
            collect($returnLines)->map(fn ($l) => ['sale_item_id' => (int) $l['sale_item_id'], 'quantity' => (float) $l['quantity']])->all(),
            collect($replacementLines)->map(fn ($l) => ['product_id' => (int) $l['product_id'], 'quantity' => (float) $l['quantity']])->all(),
            $data['difference_method'],
        );

        return redirect()->route('receipts.show', $replacement)
            ->with('success', "{$exchange->exchange_number}: returned {$refund->refund_number}, replacement {$replacement->receipt_number}.");
    }
}
