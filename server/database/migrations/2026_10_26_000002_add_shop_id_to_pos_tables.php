<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-shop spine (P1): every shop-owned table learns shop_id. All existing
 * rows are backfilled into shop 1 (the MAIN shop), then uniqueness switches
 * from global to per-shop: SKU/barcode/category name can now repeat across
 * shops but never within one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $targets = [
            'products' => true,
            'categories' => true,
            'brands' => true,
            'suppliers' => true,
            'sales' => true,
            'refunds' => true,
            'exchanges' => true,
            'stock_movements' => true,
            'import_batches' => true,
            'audit_logs' => false,
        ];

        foreach ($targets as $table => $notNull) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, function (Blueprint $t) use ($notNull): void {
                    $t->foreignId('shop_id')->nullable()->after('id')->index();
                });
            }
        }

        // -- backfill into the MAIN shop --------------------------------
        $mainShopId = (int) (DB::table('shops')->orderBy('id')->value('id') ?? 1);
        foreach (array_keys($targets) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'shop_id')) {
                DB::table($table)->whereNull('shop_id')->update(['shop_id' => $mainShopId]);
            }
        }

        // -- per-shop uniqueness ----------------------------------------
        // SKU: was unique globally; barcode: loose index (unique per shop now)
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            $dropIndex = function (string $table, string $index) {
                $exists = DB::table('information_schema.statistics')
                    ->where('table_schema', DB::connection()->getDatabaseName())
                    ->where('table_name', $table)
                    ->where('index_name', $index)
                    ->exists();
                if ($exists) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
                }
            };

            $dropIndex('products', 'products_sku_unique');
            $dropIndex('products', 'products_barcode_index');
            $dropIndex('categories', 'categories_name_unique');
            $dropIndex('brands', 'brands_name_unique');

            Schema::table('products', function (Blueprint $t): void {
                $t->unique(['sku', 'shop_id']);       // SKU unique per shop
                $t->unique(['barcode', 'shop_id']);   // barcode unique per shop (NULLs exempt in MySQL)
            });
            Schema::table('categories', function (Blueprint $t): void {
                $t->unique(['name', 'shop_id']);
            });
            Schema::table('brands', function (Blueprint $t): void {
                $t->unique(['name', 'shop_id']);
            });
        }

        // -- not null first, then FK (change() and constraint conflict when ordered together)
        foreach ($targets as $table => $notNull) {
            if ($notNull && Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->foreignId('shop_id')->nullable(false)->change();
                });
            }
        }
        foreach ($targets as $table => $notNull) {
            if ($notNull && Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, function (Blueprint $t): void {
                    $t->foreign('shop_id')->references('id')->on('shops');
                });
            }
        }

        // audit_logs keeps a nullable FK
        Schema::table('audit_logs', function (Blueprint $t): void {
            $t->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        foreach (['products', 'categories', 'brands', 'suppliers', 'sales', 'refunds', 'exchanges', 'stock_movements', 'import_batches', 'audit_logs'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'shop_id')) {
                Schema::table($table, function (Blueprint $t) use ($table): void {
                    $t->dropForeign([$table.'_shop_id_foreign']);
                });
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropIndex(['shop_id']);
                });
                Schema::table($table, function (Blueprint $t): void {
                    $t->dropColumn('shop_id');
                });
            }
        }
    }
};
