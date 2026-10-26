<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the stock ledger (§13). Current stock = SUM(quantity) over the
 * ledger; products.stock_cached is only a maintained cache of that sum.
 */
class StockMovement extends Model
{
    use BelongsToShop;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'movement_type', 'quantity',
        'reference_type', 'reference_id', 'user_id', 'reason',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'created_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
