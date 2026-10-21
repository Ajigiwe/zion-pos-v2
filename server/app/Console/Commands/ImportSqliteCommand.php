<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\Setting;
use App\Services\DocumentNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Phase 7 — ETL from the Flutter app's Drift/SQLite database.
 *
 *     php artisan pos:import-sqlite C:\path\to\instrument_pos.db
 *     php artisan pos:import-sqlite ... --dry-run
 *
 * Scope (per PHP-PLAN §6 Phase 7 decision): catalog (products, categories,
 * brands, suppliers), store.* settings, and stock balances — imported as one
 * OPENING_STOCK/ADJUSTMENT movement per product under a single ImportBatch.
 * Sales history is NOT migrated; the Flutter DB stays archived read-only.
 */
class ImportSqliteCommand extends Command
{
    protected $signature = 'pos:import-sqlite
        {path : Path to the Flutter app\'s instrument_pos.db file}
        {--skip-stock : Do not import stock balances, catalog only}';

    protected $description = 'Import catalog + store profile (+ stock balances) from the legacy Flutter SQLite database';

    public function handle(DocumentNumberService $numbers): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            $this->line('The Flutter DB lives on the host station. Copy instrument_pos.db first
(search the host PC for "instrument_pos.db" — typically under AppData).');

            return self::FAILURE;
        }

        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $this->importSettings($pdo);
        $count = $this->importCatalog($pdo, $numbers);
        $this->info("Catalog: created {$count['created']}, updated {$count['updated']}.");

        if ($this->option('skip-stock')) {
            AuditLog::record('IMPORT', null, 'import_batch', null, 'SQLite legacy import (catalog only)');

            return self::SUCCESS;
        }

        $stock = $this->importStock($pdo, $numbers);
        $this->info("Stock: {$stock['moved']} products imported via one movement each; batch {$stock['batch']}.");

        AuditLog::record('IMPORT', null, 'import_batch', null, "SQLite legacy catalog import · {$count['created']}+{$count['updated']} products");

        return self::SUCCESS;
    }

    private function importSettings(PDO $pdo): void
    {
        foreach ($pdo->query("SELECT key, value FROM settings WHERE key LIKE 'store.%' OR key = 'currency'") as $row) {
            Setting::put($row['key'], (string) $row['value']);
        }
        $this->line('Store profile: '.(Setting::get('store.name') ?? '(unset)'));
    }

    private function importCatalog(PDO $pdo, DocumentNumberService $numbers): array
    {
        $created = 0;
        $updated = 0;

        $categoryId = [];

        foreach ($pdo->query('SELECT name, description FROM categories') as $row) {
            $category = Category::firstOrCreate(['name' => $row['name']], ['description' => $row['description']]);
            $categoryId[$row['name']] = $category->id;
        }

        $brandId = [];

        foreach ($pdo->query('SELECT name, description FROM brands') as $row) {
            $brand = Brand::firstOrCreate(['name' => $row['name']], ['description' => $row['description']]);
            $brandId[$row['name']] = $brand->id;
        }
        foreach ($pdo->query('SELECT name, phone, email, address FROM suppliers') as $row) {
            DB::table('suppliers')->updateOrInsert(['name' => $row['name']], [
                'phone' => $row['phone'], 'email' => $row['email'], 'address' => $row['address'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Drift stores category/brand as ids; resolve names with joins.
        $rows = $pdo->query('
            SELECT p.sku, p.barcode, p.name, c.name AS category_name, b.name AS brand_name,
                   p.description, p.cost_price, p.selling_price, p.tax_rate, p.reorder_level,
                   p.tracking_type, p.is_active
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id')->fetchAll(PDO::FETCH_ASSOC);

        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $attributes = [
                'barcode' => $row['barcode'] ?: null,
                'name' => $row['name'],
                'category_id' => $categoryId[$row['category_name'] ?? ''] ?? null,
                'brand_id' => $brandId[$row['brand_name'] ?? ''] ?? null,
                'description' => $row['description'] ?: null,
                'cost_price' => (float) $row['cost_price'],
                'selling_price' => (float) $row['selling_price'],
                'tax_rate' => (float) $row['tax_rate'],
                'reorder_level' => (float) $row['reorder_level'],
                'tracking_type' => $row['tracking_type'] ?: 'quantity',
                'is_active' => (bool) $row['is_active'],
            ];

            $existing = Product::query()->where('sku', $row['sku'])->first();
            if ($existing) {
                $existing->update($attributes);
                $updated++;
            } else {
                Product::create($attributes + ['sku' => $row['sku']]);
                $created++;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        return compact('created', 'updated');
    }

    /** One ledger movement per product with the legacy cached balance. */
    private function importStock(PDO $pdo, DocumentNumberService $numbers): array
    {
        $userId = \App\Models\User::query()->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->first()?->id;

        $batch = ImportBatch::create([
            'batch_number' => $numbers->next('IMP'),
            'import_type' => 'OPENING_STOCK',
            'mode' => 'legacy_sqlite_import',
            'file_name' => basename($this->argument('path')),
            'total_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'created_by' => $userId,
            'status' => 'COMPLETED',
        ]);

        $rows = $pdo->query('
            SELECT p.sku, p.stock_cached
            FROM products p')->fetchAll(PDO::FETCH_ASSOC);

        $moved = 0;

        foreach ($rows as $row) {
            $product = Product::query()->where('sku', $row['sku'])->first();
            if (! $product) {
                $batch->increment('failed_rows');
                continue;
            }

            $balance = (float) ($row['stock_cached'] ?? 0);
            $delta = $balance - (float) $product->stock_cached;

            if (abs($delta) > 0.0001) {
                $product->stockMovements()->create([
                    'movement_type' => $delta > 0 ? 'opening_stock' : 'adjustment',
                    'quantity' => $delta,
                    'reference_type' => 'import_batch',
                    'reference_id' => $batch->id,
                    'user_id' => $userId,
                    'reason' => 'Opening balance from legacy Flutter database',
                    'created_at' => now(),
                ]);
                $product->update(['stock_cached' => $balance]);
                $batch->increment('successful_rows');
                $moved++;
            }
            $batch->increment('total_rows');
        }

        return ['moved' => $moved, 'batch' => $batch->batch_number];
    }
}
