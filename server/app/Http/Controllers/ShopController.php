<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Multi-shop management (superadmin): create trading locations, keep their
 * profiles current, assign staff, and switch which shop you are operating in.
 * Shop deactivation is the soft exit — data stays, tills stop serving.
 */
class ShopController extends Controller
{
    public function index()
    {
        return view('shops.index', [
            'shops' => Shop::query()->withCount('users')->ordered()->get(),
            'staff' => User::query()->with('roles:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $shop = Shop::create($data);

        AuditLog::record('SHOP_CREATE', auth()->id(), 'shop', $shop->id, $shop->name);

        return redirect()->route('shops.index')->with('success', "Shop '{$shop->name}' created — assign staff now.");
    }

    public function update(Request $request, Shop $shop)
    {
        $shop->update($this->validated($request, $shop));

        AuditLog::record('SHOP_UPDATE', auth()->id(), 'shop', $shop->id, $shop->name);

        return redirect()->route('shops.index')->with('success', "Shop '{$shop->name}' updated.");
    }

    /** Activate/deactivate: deactivated shops vanish from pickers, data intact. */
    public function toggle(Request $request, Shop $shop)
    {
        $shop->update(['is_active' => ! $shop->is_active]);

        AuditLog::record($shop->is_active ? 'SHOP_ACTIVATE' : 'SHOP_DEACTIVATE', auth()->id(), 'shop', $shop->id, $shop->name);

        return back()->with('success', "Shop '{$shop->name}' ".($shop->is_active ? 'activated.' : 'deactivated.'));
    }

    /** Attach/detach one staff member to/from this shop. */
    public function staff(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'action' => ['required', 'in:attach,detach'],
        ]);

        $user = User::find($data['user_id']);

        if ($shop->users()->whereKey($user->id)->exists()) {
            $shop->users()->detach($user->id);
        }

        if ($data['action'] === 'attach') {
            $shop->users()->attach($user->id);
        }

        // keep every user with at least one shop wherever possible
        if ($user->shops()->doesntExist()) {
            $user->shops()->attach(Shop::main()->id);
        }

        AuditLog::record($data['action'] === 'attach' ? 'SHOP_STAFF_ADD' : 'SHOP_STAFF_REMOVE', auth()->id(), 'shop', $shop->id, $user->name.' @ '.$shop->name);

        return back()->with('success', "{$user->name} ".($data['action'] === 'attach' ? 'assigned to' : 'removed from')." '{$shop->name}'.");
    }

    /** Session switcher — superadmin or staff that belongs to the target shop. */
    public function switchShop(Request $request)
    {
        $data = $request->validate(['shop_id' => ['required', 'integer']]);
        $user = $request->user();
        $shop = Shop::query()->where('is_active', true)->find($data['shop_id']);

        $allowed = $user->hasRole('superadmin')
            || ($shop && $user->shops()->whereKey($shop->id)->exists());

        if (! $shop || ! $allowed) {
            return back()->with('error', 'You are not assigned to that shop.');
        }

        $request->session()->put('shop_id', $shop->id);

        return redirect()->route('dashboard')->with('success', "Now operating in '{$shop->name}'.");
    }

    private function validated(Request $request, ?Shop $shop = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:16', 'regex:/^[A-Z0-9]{2,16}$/', Rule::unique('shops', 'code')->ignore($shop?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', (bool) $shop?->is_active);

        return $data;
    }
}
