<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Supplier;

/**
 * One page, three tabs — categories, brands, suppliers.
 */
class TaxonomyController extends Controller
{
    public const TYPES = ['category' => Category::class, 'brand' => Brand::class, 'supplier' => Supplier::class];

    public function index(Request $request)
    {
        $tab = $request->input('tab');
        if (! array_key_exists($tab, self::TYPES)) {
            $tab = 'category';
        }
        $class = self::TYPES[$tab];

        $rows = $tab === 'supplier'
            ? Supplier::orderBy('name')->get()
            : $class::orderBy('name')->withCount('products')->get();

        return view('taxonomy.index', compact('tab', 'rows'));
    }

    public function store(Request $request, string $type)
    {
        $class = self::validateType($type);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
        if ($type === 'supplier') {
            $rules += ['phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:500']];
        }

        $data = $request->validate($rules);
        $class::create($data);

        AuditLog::record(ucwords($type).'_CREATE', auth()->id(), $type, null, $data['name']);

        return back()->with('success', ucwords($type)." '{$data['name']}' created.");
    }

    public function update(Request $request, string $type, int $id)
    {
        $class = self::validateType($type);
        $row = $class::findOrFail($id);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
        if ($type === 'supplier') {
            $rules += ['phone' => ['nullable', 'string', 'max:50'], 'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:500']];
        }

        $data = $request->validate($rules);
        $row->update($data);

        AuditLog::record(ucwords($type).'_UPDATE', auth()->id(), $type, $id, $data['name']);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $class = self::validateType($type);
        $row = $class::findOrFail($id);

        $inUse = match ($type) {
            'category' => (int) (Category::find($id)?->products()->count() ?? 0),
            'brand' => (int) (Brand::find($id)?->products()->count() ?? 0),
            default => null,
        };

        if ($inUse !== null && $inUse > 0) {
            return back()->with('error', "Cannot delete: {$inUse} product(s) still use this {$type}.");
        }

        $name = $row->name;
        $row->delete();

        AuditLog::record(ucwords($type).'_DELETE', auth()->id(), $type, $id, $name);

        return back()->with('success', "Deleted '{$name}'.");
    }

    private static function validateType(string $type): string
    {
        if (! isset(self::TYPES[$type])) {
            abort(404);
        }

        return self::TYPES[$type];
    }
}
