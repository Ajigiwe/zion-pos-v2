<?php

namespace App\Support;

use App\Models\Shop;

/**
 * Resolve the active shop for a request and hold it for the lifetime of that
 * request. Everyone lands on the main shop unless their assignments (or the
 * superadmin's session switcher) say otherwise; staff can ONLY activate shops
 * they are assigned to.
 *
 * Console commands (migrations, schedule, backups) keep this unset on
 * purpose: they work across ALL shops.
 */
class ShopContext
{
    private static ?int $id = null;

    /** True once middleware has run for this request (set() called). */
    private static bool $begun = false;

    private static ?Shop $shop = null;

    /** Called by middleware after resolving; id()/shop() return null before that. */
    public static function set(?Shop $shop): void
    {
        self::$begun = true;
        self::$shop = $shop;
        self::$id = $shop?->id;
    }

    public static function id(): ?int
    {
        return self::$begun ? self::$id : null;
    }

    public static function shop(): ?Shop
    {
        return self::$begun ? self::$shop : null;
    }
}
