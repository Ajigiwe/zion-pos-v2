<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links the return of goods from an original sale to a replacement sale
 * (§15). Stock moves as EXCHANGE_RETURN / EXCHANGE_SALE; the replacement
 * sale's payments include an exchange_credit row.
 */
class Exchange extends Model
{
    protected $fillable = [
        'exchange_number', 'original_sale_id', 'refund_id',
        'replacement_sale_id', 'return_total', 'replacement_total', 'difference',
    ];

    protected $casts = [
        'return_total' => 'decimal:2',
        'replacement_total' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'original_sale_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function replacementSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'replacement_sale_id');
    }
}
