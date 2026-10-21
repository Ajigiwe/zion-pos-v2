<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = Sale::query()
            ->with(['cashier:id,name', 'payments', 'items:id,sale_id,subtotal'])
            ->when($request->input('q'), function ($q, $term) {
                $q->where(fn ($w) => $w
                    ->where('receipt_number', 'like', "%{$term}%")
                    ->orWhere('total', 'like', $term));
            })
            ->when($request->input('month'), fn ($q, $v) => $q->whereYear('created_at', substr($v, 0, 4))->whereMonth('created_at', substr($v, 5)))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('sales.index', ['sales' => $sales, 'filters' => $request->only(['q', 'month'])]);
    }
}
