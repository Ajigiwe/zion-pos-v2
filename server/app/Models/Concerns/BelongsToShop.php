<?php

namespace App\Models\Concerns;

use App\Models\Shop;
use App\Support\ShopContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rows of this table live in exactly one shop. Adds a global scope filtered by
 * the request's active shop (see ShopContext) so nothing rows in another
 * shop can ever leak into a query, and stamps shop_id on create.
 *
 * Console jobs (migrations, schedule, backup) run unscoped — they deal with
 * ALL shops deliberately.
 */
trait BelongsToShop
{
    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope('shop', function (Builder $query): void {
            $id = ShopContext::id();
            if ($id !== null) {
                $query->where($query->getModel()->getTable().'.shop_id', $id);
            }
        });

        static::creating(function (self $model): void {
            $model->shop_id ??= ShopContext::id() ?? 1;
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
