<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Support\ShopContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Boots the multi-shop context for every web request: resolves which shop
 * this user is operating in (their assignments, or the superadmin's session
 * switcher) and installs it for all shop-scoped queries + helpers. Console
 * work is deliberately unscoped.
 */
class BootShopContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = null;

        if (auth()->check()) {
            $user = auth()->user();
            $requested = (int) $request->session()->get('shop_id', 0);

            if ($user->hasRole('superadmin') || $user->hasRole('admin')) {
                // Superadmin + Owner operate anywhere; session switcher picks the shop.
                $shop = $requested
                    ? Shop::query()->find($requested)
                    : Shop::main();
            } else {
                $allowed = $user->shops()->where('is_active', true)->orderBy('id')->get();
                $shop = $allowed->firstWhere('id', $requested)
                    ?? $allowed->first()
                    ?? Shop::main(); // bootstrap: legacy/unassigned staff ride the main shop (P3 tightens)
            }

            // persist the effective shop so the switcher and any redirects agree
            if ($shop && $request->session()->get('shop_id') !== $shop->id) {
                $request->session()->put('shop_id', $shop->id);
            }
        }

        ShopContext::set($shop);

        return $next($request);
    }
}
