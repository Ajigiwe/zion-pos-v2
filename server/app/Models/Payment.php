<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment record on a sale (§12). A cash + mobile money split is two
 * rows with the same sale_id; each row uses exactly one method. Mobile money
 * stores its transaction id in `reference`.
 */
class Payment extends Model
{
    protected $fillable = ['sale_id', 'payment_method', 'amount', 'reference'];

    protected $casts = ['amount' => 'decimal:2'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
