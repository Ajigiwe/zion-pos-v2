<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A trading location: the main musical store plus any later shops. Each shop
 * owns its own products/categories/brands/suppliers (catalog), stock ledger
 * (via movements), sales history and imports — everything else (users list,
 * roles, branding/settings, backups) stays global.
 */
class Shop extends Model
{
    protected $fillable = ['name', 'code', 'address', 'phone', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Staff assigned to this shop. */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_shop')->withTimestamps();
    }

    /** The original, universal shop every table was backfilled into. */
    public static function main(): self
    {
        return static::query()->orderBy('id')->firstOrNew(['code' => 'MAIN']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('id');
    }
}
