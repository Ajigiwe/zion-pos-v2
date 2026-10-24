<?php

namespace App\Providers;

use App\Models\Product;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Enforce Bootstrap 5 styling on all paginated query results (prevents giant Tailwind SVGs)
        Paginator::useBootstrapFive();

        // Superadmin bypasses every permission check in one line (RBAC §4).
        Gate::before(fn ($user) => $user->hasRole('superadmin') ? true : null);

        // Sidebar badge: threshold-aware low-stock rule — products with their own
        // reorder level use it, everything else falls back to the global alert level.
        View::composer('layouts.app', function ($view) {
            $view->with('lowStockCount', Product::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereColumn('stock_cached', '<=', 'reorder_level')
                           ->where('reorder_level', '>', 0);
                    })->orWhere(function ($q2) {
                        $q2->whereNull('reorder_level')
                           ->orWhere('reorder_level', '<=', 0);
                        $q2->where('stock_cached', '<=', low_stock_threshold());
                    });
                })
                ->count());
        });
    }
}
