<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Flutter parity: Active / Hidden / All segmented filter.
        $status = $request->input('status', 'active');
        $status = in_array($status, ['active', 'hidden', 'all'], true) ? $status : 'active';

        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name'])
            ->search($request->input('q'))
            ->when($request->input('low') === '1', fn ($q) => $q->lowStock())
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'hidden', fn ($q) => $q->where('is_active', false))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'status' => $status,
            'hiddenCount' => Product::query()->where('is_active', false)->count(),
            'filters' => $request->only(['q', 'low', 'category_id', 'status']),
        ]);
    }

    public function create()
    {
        return view('products.form', [
            'product' => new Product(['tracking_type' => 'quantity', 'tax_rate' => 0, 'reorder_level' => 0]),
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $product = Product::create($data + ['is_active' => true]);

        // Initial stock is a ledger entry, never a direct edit (§13).
        if ((float) ($data['initial_stock'] ?? 0) !== 0.0) {
            $this->recordMovement($product, (float) $data['initial_stock'], MovementType::OpeningStock, 'opening stock for new product');
        }

        AuditLog::record('PRODUCT_CREATE', auth()->id(), 'product', $product->id, "{$product->sku} — {$product->name}");

        return redirect()->route('products.index')->with('success', "Created {$product->name}.");
    }

    public function edit(Product $product)
    {
        return view('products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $product->update(collect($data)->except('initial_stock')->all());

        if ($request->has('is_active')) {
            $product->update(['is_active' => $request->boolean('is_active')]);
        }

        AuditLog::record('PRODUCT_UPDATE', auth()->id(), 'product', $product->id, "{$product->sku} updated");

        return redirect()->route('products.index')->with('success', 'Saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Immutable history: deactivate instead of hard-delete once sold.
        $sold = $product->stockMovements()->where('movement_type', 'sale')->exists();

        if ($sold) {
            $product->update(['is_active' => false]);
            AuditLog::record('PRODUCT_ARCHIVE', auth()->id(), 'product', $product->id, "{$product->sku} archived");
            $message = "{$product->name} has sales history — deactivated instead of deleted.";
        } else {
            $product->delete();
            AuditLog::record('PRODUCT_DELETE', auth()->id(), 'product', $product->id ?? null, "{$product->sku} deleted");
            $message = "{$product->name} deleted.";
        }

        return redirect()->route('products.index')->with('success', $message);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        // SKU/barcode uniqueness is per shop (multi-shop): a code may exist in
        // another shop, never twice in this one.
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', Rule::unique('products')->where('shop_id', shop_id())->ignore($product?->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products')->where('shop_id', shop_id())->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('shop_id', shop_id())],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('shop_id', shop_id())],
            'description' => ['nullable', 'string'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'tracking_type' => ['required', 'in:quantity,serialized'],
            'initial_stock' => ['nullable', 'numeric'],
        ]);

        $data['initial_stock'] = (float) ($data['initial_stock'] ?? 0);

        return $data;
    }

    private function recordMovement(Product $product, float $qty, MovementType $type, string $reason): void
    {
        $product->stockMovements()->create([
            'movement_type' => $type->value,
            'quantity' => $qty,
            'user_id' => auth()->id(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
        $product->fill(['stock_cached' => $product->stock_cached + $qty])->save();
    }
}
