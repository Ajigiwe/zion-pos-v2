<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    protected $fillable = [
        'refund_number', 'idempotency_key', 'uuid', 'original_sale_id',
        'cashier_id', 'refund_method', 'amount', 'reason', 'status',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'original_sale_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}
