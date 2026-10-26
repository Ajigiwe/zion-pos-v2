<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A completed point-of-sale transaction. Immutable: refunds/exchanges
 * reference the original sale but never modify or delete it.
 */
class Sale extends Model
{
    use BelongsToShop;

    protected $fillable = [
        'receipt_number', 'idempotency_key', 'uuid', 'cashier_id', 'till_id',
        'subtotal', 'discount', 'tax', 'total', 'payment_status', 'sale_status',
        'needs_review', 'source',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'needs_review' => 'boolean',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'original_sale_id');
    }
}
