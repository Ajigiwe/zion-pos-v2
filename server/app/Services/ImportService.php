<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bulk product/stock import — ports features/imports. Accepts CSV and XLSX
 * (maatwebsite/excel), records an ImportBatch like the Flutter app, and
 * always emits ledger movements (never direct stock edits).
 */
class ImportService
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function readSpreadsheet($file): Collection
    {
        $sheet = Excel::toCollection(null, $file)->first();
        if (! $sheet || $sheet->isEmpty()) { return collect(); }

        // Map by header names first (order-independent), fall back to the
        // legacy positional template order when nothing recognizable.
        $canonical = ['sku', 'barcode', 'name', 'category', 'brand', 'cost_price', 'selling_price', 'tax_rate', 'reorder_level', 'quantity'];
        $aliases = [
            'sku' => ['sku', 'code', 'item_code'],
            'barcode' => ['barcode', 'bar_code', 'barcode_number'],
            'name' => ['name', 'item_name', 'product', 'product_name', 'item'],
            'category' => ['category', 'categories'],
            'brand' => ['brand', 'make', 'manufacturer'],
            'cost_price' => ['cost_price', 'cost', 'buying_price', 'cost_ghs', 'cost_price_ghc', 'cost_price_ghs'],
            'selling_price' => ['selling_price', 'price', 'price_ghs', 'price_ghc', 'price_ghe', 'retail', 'retail_price'],
            'tax_rate' => ['tax_rate', 'tax', 'vat'],
            'reorder_level' => ['reorder_level', 'reorder', 'min_stock', 'reorder_point'],
            'quantity' => ['quantity', 'qty', 'quantities'],
        ];

        $header = $sheet->first()->map(function ($h) {
            return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string) $h))), '_');
        });
        $map = [];
        foreach ($header as $idx => $h) {
            foreach ($aliases as $canon => $list) {
                if (! array_key_exists($canon, $map) && in_array($h, $list, true)) {
                    $map[$canon] = $idx;
                    break;
                }
            }
        }

        $dataRows = $sheet->skip(1)->filter(function ($row) {
            return collect($row)->contains(fn ($cell) => trim((string) $cell) !== '');
        });

        if (array_key_exists('sku', $map) || array_key_exists('name', $map)) {
            return $dataRows->map(function ($row) use ($map, $canonical) {
                return collect($canonical)->map(function ($canon) use ($row, $map) {
                    $idx = $map[$canon] ?? null;

                    return $idx === null ? '' : ($row[$idx] ?? '');
                });
            })->values();
        }

        return $dataRows->values();
    }

    public function importProducts(Collection $rows, string $fileName, bool $includeStock = false): ImportBatch
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows->values() as $i => $row) {
            $sku = $row->get(0);
            if ($sku === null || trim((string) $sku) === '') {
                $failed++;
                $errors[] = 'Row '.($i + 2).': missing SKU';
                continue;
            }

            try {
                $attributes = [
                    'sku' => trim((string) $sku),
                    'barcode' => $this->nullIfBlank($row->get(1)),
                    'name' => trim((string) $row->get(2)) ?: 'Unnamed product',
                    'category_id' => $this->categoryFor($row->get(3)),
                    'brand_id' => $this->brandFor($row->get(4)),
                    'cost_price' => max(0, (float) $row->get(5)),
                    'selling_price' => max(0, (float) $row->get(6)),
                    'tax_rate' => (float) ($row->get(7) ?? 0),
                    'reorder_level' => max(0, (float) ($row->get(8) ?? 0)),
                ];

                $product = Product::query()->where('sku', $attributes['sku'])->first();

                if ($product) {
                    $product->update($attributes);
                    $updated++;
                } else {
                    $product = Product::create($attributes + ['is_active' => true]);
                    $created++;
                }

                $stock = (float) ($row->get(9) ?? 0);
                if ($includeStock && $stock > 0) {
                    $this->recordMovement($product, $stock, MovementType::BulkImport, 'product import');
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row '.($i + 2).': '.$e->getMessage();
            }
        }

        return $this->finishBatch($fileName, 'PRODUCT_IMPORT', $rows->count(), $created + $updated, $failed, $errors);
    }

    public function importStock(Collection $rows, string $fileName, string $mode): ImportBatch
    {
        // mode 'add' → BULK_IMPORT (+) movements, 'set' → recalculated ADJUSTMENT movements.
        if (! in_array($mode, ['add', 'set'], true)) {
            throw new \InvalidArgumentException('Mode must be add or set.');
        }

        $applied = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows->values() as $i => $row) {
            $sku = $row->get(0);
            $quantity = (float) ($row->get(1) ?? 0);

            try {
                $product = Product::query()->where('sku', trim((string) $sku))->first();
                if (! $product) {
                    $failed++;
                    $errors[] = 'Row '.($i + 2).": unknown SKU '{$sku}'";
                    continue;
                }

                $delta = $mode === 'add'
                    ? $quantity
                    : $quantity - (float) $product->stock_cached;

                if ($delta != 0.0) {
                    $this->recordMovement($product, $delta, $mode === 'add' ? MovementType::BulkImport : MovementType::Adjustment, 'stock import');
                    $applied++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Row '.($i + 2).': '.$e->getMessage();
            }
        }

        return $this->finishBatch($fileName, 'BULK_STOCK', $rows->count(), $applied, $failed, $errors);
    }

    /**
     * Waiting-room drafts: normalized per-row payloads for the review grid.
     * include=false for structurally broken rows (missing SKU, unknown SKU on
     * stock import) so the operator has to opt them in.
     */
    public function draftRows(Collection $rows, string $kind, string $mode): array
    {
        $drafts = [];

        // lazy counter seeded past any existing SKU-###### codes
        $nextSku = null;
        $genSku = function () use (&$nextSku) {
            if ($nextSku === null) {
                $max = 0;
                Product::query()->where('sku', 'like', 'SKU-%')->pluck('sku')->each(function ($s) use (&$max) {
                    if (preg_match('/^SKU-(\d+)$/', (string) $s, $m)) { $max = max($max, (int) $m[1]); }
                });
                $nextSku = $max + 1;
            }

            return 'SKU-' . str_pad((string) $nextSku++, 6, '0', STR_PAD_LEFT);
        };

        // count barcode occurrences inside the sheet (for the duplicate trap)
        $bcCount = [];
        if ($kind === 'products') {
            foreach ($rows->values() as $r) {
                $b = trim((string) $r->get(1));
                if ($b !== '' && ! preg_match('/^[\d.]+[eE]\+?\d{1,3}$/', $b)) {
                    $bcCount[$b] = ($bcCount[$b] ?? 0) + 1;
                }
            }
        }

        foreach ($rows->values() as $i => $row) {
            $line = $i + 2;
            $sku = trim((string) $row->get(0));
            $product = $sku !== '' ? Product::query()->where('sku', $sku)->first() : null;

            $draft = [
                'line' => (string) $line,
                'sku' => $sku,
                'exists' => (bool) $product,
                'info' => $product
                    ? ($product->name . ' · stock ' . rtrim(rtrim(number_format((float) $product->stock_cached, 2), '0'), '.') . ' · GHS ' . number_format((float) $product->selling_price, 2))
                    : '',
                'auto_sku' => false,
            ];

            if ($kind === 'products') {
                // spreadsheet traps: a value like 4.01712E+12 is a float-printed
                // barcode (Excel coercion) — never a real code; and the same
                // barcode twice in one sheet would otherwise import once and
                // explode the rest on the unique index. Both get cleared so
                // the row imports barcode-less and the operator sees why.
                $barcode = trim((string) $row->get(1));
                $notes = [];
                if ($barcode !== ''
                    && preg_match('/^[\d.]+[eE]\+?\d{1,3}$/', $barcode)) {
                    $barcode = '';
                    $notes[] = 'scientific-notation barcode cleared';
                } elseif ($barcode !== '' && ($bcCount[$barcode] ?? 0) > 1) {
                    $barcode = '';
                    $notes[] = 'duplicate barcode in sheet — cleared';
                }
                $name = trim((string) $row->get(2));
                if ($sku === '' && $name !== '') {
                    // operator gave no SKU for a named item - mint one so the row is usable
                    $sku = $draft['sku'] = $genSku();
                    $draft['auto_sku'] = true;
                    $product = null;
                    $draft['exists'] = false;
                }
                if ($notes) {
                    $draft['info'] = trim($draft['info'].' · '.implode(' · ', $notes), ' ·');
                }
                $draft += [
                    'barcode' => $barcode,
                    'name' => $name,
                    'category' => trim((string) $row->get(3)),
                    'brand' => trim((string) $row->get(4)),
                    'cost_price' => max(0, (float) ($row->get(5) ?? 0)),
                    'selling_price' => max(0, (float) ($row->get(6) ?? 0)),
                    'tax_rate' => (float) ($row->get(7) ?? 0),
                    'reorder_level' => max(0, (float) ($row->get(8) ?? 0)),
                    'quantity' => max(0, (float) ($row->get(9) ?? 0)),
                ];
                $draft['include'] = $sku !== '';
            } else {
                $draft += [
                    'quantity' => (float) ($row->get(1) ?? 0),
                ];
                $draft['include'] = $sku !== '' && $product !== null;
            }

            $drafts[] = $draft;
        }

        return $drafts;
    }

    /**
     * Flutter preview/analysis parity: dry-run the spreadsheet and report what
     * WOULD happen, without writing anything.
     */
    public function analyze(Collection $rows, string $kind): array
    {
        $new = 0;
        $updates = 0;
        $errors = [];
        $unitsIn = 0.0;
        $unitsOut = 0.0;
        $invalid = 0;
        $valid = 0;

        foreach ($rows->values() as $i => $row) {
            $line = $i + 2;
            $sku = trim((string) $row->get(0));

            if ($kind === 'products') {
                $stock = (float) ($row->get(9) ?? 0);
                if ($stock !== 0.0) {
                    $unitsIn += max(0, $stock);
                    $unitsOut += abs(min(0, $stock));
                }
                if ($sku === '' && trim((string) $row->get(2)) === '') {
                    // no SKU AND no name = unusable row (a named item gets an auto SKU)
                    $errors[] = "Row {$line}: missing SKU and name";
                    $invalid++;
                    continue;
                }
            } else {
                $quantity = (float) ($row->get(1) ?? 0);
                if ($sku === '') {
                    $errors[] = "Row {$line}: missing SKU";
                    $invalid++;
                    continue;
                }
                $product = Product::query()->where('sku', $sku)->first();
                if (! $product) {
                    $errors[] = "Row {$line}: unknown SKU '{$sku}'";
                    $invalid++;
                    continue;
                }
                $delta = $quantity; // add mode; 'set' handled at commit time
                $unitsIn += max(0, $delta);
                $unitsOut += abs(min(0, $delta));
            }

            $exists = Product::query()->where('sku', $sku)->exists();
            $exists ? $updates++ : $new++;
            $valid++;
        }

        $total = $rows->count();

        return [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
            'new' => $new,
            'updates' => $updates,
            'units_in' => round($unitsIn, 2),
            'units_out' => round($unitsOut, 2),
            'errors' => array_slice($errors, 0, 20),
            'error_count' => count($errors),
        ];
    }

    private function recordMovement(Product $product, float $qty, MovementType $type, string $reason): void
    {
        $product->stockMovements()->create([
            'movement_type' => $type->value,
            'quantity' => $qty,
            'user_id' => auth()->id(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
        $product->fill(['stock_cached' => $product->stock_cached + $qty])->save();
    }

    private function finishBatch(string $fileName, string $type, int $total, int $ok, int $failed, array $errors, string $mode = ''): ImportBatch
    {
        $batch = ImportBatch::create([
            'batch_number' => $this->numbers->next('IMP'),
            'import_type' => $type,
            'mode' => $mode,
            'file_name' => $fileName,
            'total_rows' => $total,
            'successful_rows' => $ok,
            'failed_rows' => $failed,
            'created_by' => auth()->id(),
            'status' => $failed > 0 ? 'COMPLETED' : 'COMPLETED', // per-row failures don't poison the batch
        ]);

        AuditLog::record('IMPORT', auth()->id(), 'import_batch', $batch->id, sprintf('%s · %s · %d ok, %d failed', $batch->batch_number, $type, $ok, $failed));

        session()->flash('import_errors', array_slice($errors, 0, 25));
        session()->flash('import_error_count', count($errors));

        return $batch;
    }

    private function categoryFor($value): ?int
    {
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        return Category::firstOrCreate(['name' => $name])->id;
    }

    private function brandFor($value): ?int
    {
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        return Brand::firstOrCreate(['name' => $name])->id;
    }

    private function nullIfBlank($value): ?string
    {
        return trim((string) $value) === '' ? null : trim((string) $value);
    }
}
