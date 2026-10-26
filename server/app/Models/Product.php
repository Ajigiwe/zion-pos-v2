<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Product extends Model
{
    use BelongsToShop;

    protected $fillable = [
        'sku', 'barcode', 'name', 'category_id', 'brand_id', 'description',
        'cost_price', 'selling_price', 'tax_rate', 'reorder_level',
        'tracking_type', 'is_active', 'stock_cached',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'reorder_level' => 'decimal:3',
        'stock_cached' => 'decimal:3',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Low-stock filter: per-product reorder level, else global alert threshold. */
    public function scopeLowStock(Builder $query): Builder
    {
        $threshold = low_stock_threshold();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($threshold) {
                $q->where(function (Builder $q2) {
                    $q2->whereColumn('stock_cached', '<=', 'reorder_level')
                        // reorder_level 0 = "do not track" per the Drift defaults
                        ->where('reorder_level', '>', 0);
                })->orWhere(function (Builder $q2) use ($threshold) {
                    $q2->whereNull('reorder_level')->orWhere('reorder_level', '<=', 0);
                    $q2->where('stock_cached', '<=', $threshold);
                });
            });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q, string $t) => $q->where(
            fn (Builder $w) => $w
                ->where('sku', 'like', "%{$t}%")
                ->orWhere('name', 'like', "%{$t}%")
                ->orWhere('barcode', 'like', "%{$t}%")
        ));
    }
}
