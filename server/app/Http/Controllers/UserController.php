<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    /** Role labels: superadmin = the developer account; admin = the store Owner. */
    public const ROLES = ['superadmin' => 'Superadmin', 'admin' => 'Owner', 'manager' => 'Manager', 'inventory_manager' => 'Inventory Manager', 'cashier' => 'Cashier'];

    /** Superadmin per-user permission grants: label + description per permission. */
    public const PERMISSION_INFO = [
        'dashboard.view' => ['Dashboard view', 'See the daily dashboard overview.'],
        'products.view' => ['View products', 'Browse the catalog and product details.'],
        'products.manage' => ['Manage products', 'Create, edit, hide products; categories & suppliers.'],
        'inventory.view' => ['View inventory', 'Browse the stock ledger.'],
        'inventory.adjust' => ['Adjust inventory', 'Opening stock, add stock, adjustments and damage.'],
        'pos.view' => ['Open the till', 'Access the sales screen (read).'],
        'pos.use' => ['Use the till', 'Ring up and complete sales.'],
        'refunds.view' => ['View refunds', 'See refund requests, exchanges and history.'],
        'refunds.request' => ['Request refunds', 'Create refund requests for completed sales.'],
        'refunds.approve' => ['Approve refunds', 'Approve or reject refund requests; stock returns on approval.'],
        'exchanges.process' => ['Process exchanges', 'Complete item exchanges directly at the counter.'],
        'imports.view' => ['View imports', 'See import types, batches and their history.'],
        'imports.run' => ['Run imports', 'Upload CSVs and start product/stock imports.'],
        'reports.view' => ['View reports', 'Sales, refunds and exchange reports with CSV export.'],
        'audit.view' => ['View audit log', 'See the full activity trail.'],
        'users.manage' => ['Manage staff', 'Add, edit and deactivate user accounts.'],
        'settings.manage' => ['Manage settings', 'Change store, receipt, appearance and system settings.'],
    ];

    public function index()
    {
        $users = User::query()->with(['roles:id,name', 'shops:id,code,name'])->orderBy('name')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => self::ROLES,
        ]);
    }

    public function create()
    {
        return view('users.form', ['user' => new User(), 'roles' => self::ROLES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => strtolower(trim($data['username'])),
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
        $user->assignRole($data['role']);
        $this->syncShops($request, $user);

        AuditLog::record('USER_CREATE', auth()->id(), 'user', $user->id, "{$user->name} joined as {$data['role']}");

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', [
            'user' => $user,
            'roles' => self::ROLES,
            'roleValue' => $user->role_label(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => strtolower(trim($data['username'])),
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($user->role_label() !== self::ROLES[$data['role']]) {
            $user->syncRoles([$data['role']]);
            AuditLog::record('USER_UPDATE', auth()->id(), 'user', $user->id, "role → {$data['role']}");
        }

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->input('password'))]);
            AuditLog::record('PASSWORD_RESET', auth()->id(), 'user', $user->id, $user->name);
        }

        $before = $user->shops->pluck('id')->sort()->values()->all();
        $this->syncShops($request, $user);
        $after = collect($request->input('shops', []))->map(fn ($v) => (int) $v)->sort()->values()->all();
        if ($before !== $after) {
            AuditLog::record('USER_SHOPS', auth()->id(), 'user', $user->id, $user->name.' → shops '.implode(',', $after ?: ['main']));
        }

        return redirect()->route('users.index')->with('success', "{$user->name} updated.");
    }

    /** Active switch from the settings card (guards: self / last owner). */
    public function toggle(Request $request, User $user)
    {
        $active = ! $user->is_active;

        if (! $active && $user->id === auth()->id()) {
            return back()->with('error', 'You cannot disable your own account.');
        }

        if (! $active) {
            $role = $user->roles->first()?->name ?? 'cashier';
            $lastOwner = $user->hasRole('superadmin')
                && User::role('superadmin')->where('is_active', true)->where('id', '!=', $user->id)->doesntExist();
            if ($lastOwner) {
                return back()->with('error', 'You cannot disable the last active owner account.');
            }
        }

        $user->update(['is_active' => $active]);

        AuditLog::record($active ? 'USER_ACTIVATE' : 'USER_DEACTIVATE', auth()->id(), 'user', $user->id,
            ($active ? 'User activated: ' : 'User deactivated: ').$user->name);

        return back()->with('success', ($active ? 'User activated: ' : 'User disabled: ').$user->name);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $hasSales = \App\Models\Sale::where('cashier_id', $user->id)->exists();
        if ($hasSales) {
            $user->update(['is_active' => false]);

            return back()->with('success', "{$user->name} has sales history — disabled instead of deleted.");
        }

        AuditLog::record('USER_DELETE', auth()->id(), 'user', $user->id, $user->name);
        $user->delete();

        return back()->with('success', "{$user->name} deleted.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._\-]+$/i', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
            'password' => [Rule::requiredIf($user?->exists === false), 'nullable', 'string', 'min:6'],
            'shops' => ['nullable', 'array'],
            'shops.*' => ['integer', Rule::exists('shops', 'id')],
        ]);
    }

    /** Shop memberships decide where this account can see products/stock/sales. */
    private function syncShops(Request $request, User $user): void
    {
        $ids = collect($request->input('shops', []))->map(fn ($v) => (int) $v)->unique()->all();

        if ($ids === [] && ! $user->hasRole('superadmin')) {
            // never leave a staff member stranded with no shop
            $ids = [\App\Models\Shop::main()->id];
        }

        $user->shops()->sync($ids);
    }

    /** Superadmin-only per-user permission grants (added on top of the role). */
    public function permissions(User $user)
    {
        $user->load(['roles.permissions:id,name', 'permissions:id,name']);
        $direct = $user->permissions->pluck('name')->all();
        $rolePerms = $user->roles->flatMap->permissions->unique('name')->pluck('name')->all();

        return view('users.permissions', [
            'user' => $user,
            'info' => self::PERMISSION_INFO,
            'names' => array_keys(self::PERMISSION_INFO),
            'direct' => $direct,
            'rolePerms' => $rolePerms,
        ]);
    }

    public function updatePermissions(Request $request, User $user)
    {
        $allowed = array_keys(self::PERMISSION_INFO);
        $requested = array_values((array) $request->input('grant', []));
        $changed = array_values(array_intersect($requested, $allowed));

        $user->syncPermissions($changed);

        AuditLog::record('PERMISSION_UPDATE', auth()->id(), 'user', $user->id,
            "{$user->name}: direct grants → " . implode(', ', $changed ?: ['(none)']));

        return redirect()->route('users.permissions', $user)->with('success', "{$user->name}'s permissions updated.");
    }
}
