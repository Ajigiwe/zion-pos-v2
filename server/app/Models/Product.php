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

    /**
     * Auto-generate a unique sequential SKU (e.g. SKU-000001).
     */
    public static function generateSku(?int $shopId = null): string
    {
        $shopId ??= shop_id();
        $query = static::withoutGlobalScopes();
        if ($shopId !== null) {
            $query->where('shop_id', $shopId);
        }

        $max = 0;
        $query->where('sku', 'like', 'SKU-%')->pluck('sku')->each(function ($s) use (&$max) {
            if (preg_match('/^SKU-(\d+)$/', (string) $s, $m)) {
                $max = max($max, (int) $m[1]);
            }
        });

        $next = $max + 1;
        do {
            $sku = 'SKU-'.str_pad((string) $next++, 6, '0', STR_PAD_LEFT);
            $check = static::withoutGlobalScopes()->where('sku', $sku);
            if ($shopId !== null) {
                $check->where('shop_id', $shopId);
            }
        } while ($check->exists());

        return $sku;
    }
}
