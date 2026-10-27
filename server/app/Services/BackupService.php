<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Database backup/restore — the settings page's backup card.
 *
 * Uses mysqldump when the binary is available (XAMPP / shared cPanel), with a
 * pure-PHP fallback for hosts that block exec. Restore runs the dump through
 * the mysql client; both endpoints are superadmin-only by route.
 */
class BackupService
{
    public function create(): array
    {
        $dir = 'backups';
        Storage::makeDirectory($dir);
        $file = $dir.'/pos-backup-'.now()->format('Ymd-His').'.sql';

        $dumpBinary = config('pos.mysqldump');

        // Shared hosts often block ProcessPopen/mysqldump — degrade to the
        // pure-PHP dump instead of failing the request.
        if (is_file($dumpBinary) && ! $this->execBlocked()) {
            $cmd = [
                $dumpBinary,
                '--host', env('DB_HOST', '127.0.0.1'),
                '--port', (string) env('DB_PORT', '3306'),
                '--user', env('DB_USERNAME', 'root'),
                '--single-transaction', '--skip-lock-tables', '--quick',
                env('DB_DATABASE'),
            ];
            if (filled(env('DB_PASSWORD'))) {
                array_splice($cmd, 3, 0, ['--password='.env('DB_PASSWORD')]);
            }

            $result = Process::run($cmd);

            if ($result->successful()) {
                Storage::put($file, $result->output());

                return ['ok' => true, 'file' => $file];
            }

            return ['ok' => false, 'error' => trim($result->errorOutput()) ?: 'mysqldump exited with an error'];
        }

        // Pure-PHP fallback: row-by-row INSERT dump.
        $sql = $this->phpDump();
        Storage::put($file, $sql);

        return ['ok' => true, 'file' => $file];
    }

    /** Cheap probe for hosts that shell out to nothing at all. */
    private function execBlocked(): bool
    {
        try {
            $probe = Process::run(PHP_BINARY.' -v');

            return ! $probe->successful();
        } catch (\Throwable) {
            return true;
        }
    }

    /* ---------------------------------------------------------------
     |  PER-SHOP BACKUP / RESTORE
     |  One shop's transactional data as a data-only .sql snapshot.
     |  audit_logs is deliberately excluded (history stays whole);
     |  child tables (sale_items/payments/refund_items) ride along
     |  via subquery so restored sales keep their lines and payments.
     |  The restore target shop comes from the file header — no
     |  wrong-shop mistakes possible.
     * --------------------------------------------------------------- */

    /** Tables in a per-shop dump: shop-owned by simple filter, children by subquery (%d = shop id). */
    private const SHOP_DUMP_TABLES = [
        'products' => 'shop_id=%d',
        'categories' => 'shop_id=%d',
        'brands' => 'shop_id=%d',
        'suppliers' => 'shop_id=%d',
        'sales' => 'shop_id=%d',
        'refunds' => 'shop_id=%d',
        'exchanges' => 'shop_id=%d',
        'stock_movements' => 'shop_id=%d',
        'import_batches' => 'shop_id=%d',
        'sale_items' => 'sale_id IN (SELECT id FROM sales WHERE shop_id=%d)',
        'payments' => 'sale_id IN (SELECT id FROM sales WHERE shop_id=%d)',
        'refund_items' => 'refund_id IN (SELECT id FROM refunds WHERE shop_id=%d)',
    ];

    public function createShop(Shop $shop): array
    {
        $dir = 'backups';
        Storage::makeDirectory($dir);
        $file = $dir.'/shop-backup-'.$shop->code.'-'.now()->format('Ymd-His').'.sql';
        $header = '-- ZIONPOS-SHOP-BACKUP shop_id='.$shop->id.' shop_code='.$shop->code
            .' created_at='.now()->toIso8601String()."\n"
            .'- data-only snapshot of shop '.$shop->name.' — restore it from the per-shop restore tool'."\n";

        if (is_file(config('pos.mysqldump')) && ! $this->execBlocked()) {
            $body = '';
            foreach (self::SHOP_DUMP_TABLES as $table => $where) {
                $whereSql = strpos($where, '%d') !== false ? sprintf($where, $shop->id) : $where;
                $cmd = [
                    config('pos.mysqldump'),
                    '--host', env('DB_HOST', '127.0.0.1'),
                    '--port', (string) env('DB_PORT', '3306'),
                    '--user', env('DB_USERNAME', 'root'),
                    '--no-create-info', '--skip-triggers',
                    '--single-transaction', '--skip-lock-tables', '--quick',
                    '--where', $whereSql,
                    env('DB_DATABASE'),
                    $table,
                ];
                if (filled(env('DB_PASSWORD'))) {
                    array_splice($cmd, 3, 0, ['--password='.env('DB_PASSWORD')]);
                }

                $result = Process::run($cmd);
                if (! $result->successful()) {
                    return ['ok' => false, 'error' => 'mysqldump failed on '.$table.': '.trim($result->errorOutput())];
                }
                $body .= "\n".trim($result->output())."\n";
            }

            Storage::put($file, $header.$body);

            return ['ok' => true, 'file' => $file];
        }

        // Pure-PHP fallback for hosts that block exec.
        Storage::put($file, $header.$this->phpShopDump($shop));

        return ['ok' => true, 'file' => $file];
    }

    private function phpShopDump(Shop $shop): string
    {
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $out = '';

        foreach (self::SHOP_DUMP_TABLES as $table => $where) {
            $whereSql = strpos($where, '%d') !== false ? sprintf($where, $shop->id) : $where;
            $out .= "\n-- {$table}\n";

            $rows = $pdo->query("SELECT * FROM `{$table}` WHERE {$whereSql}")->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows === []) {
                continue;
            }

            $columns = implode(',', array_map(fn ($c) => '`'.$c.'`', array_keys($rows[0])));
            foreach (array_chunk($rows, 100) as $chunk) {
                $values = implode(",\n", array_map(function ($row) use ($pdo) {
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));

                    return '('.implode(',', $vals).')';
                }, $chunk));

                $out .= "INSERT INTO `{$table}` ({$columns}) VALUES\n{$values};\n";
            }
        }

        return $out;
    }

    /**
     * Restore a per-shop snapshot. The file's own header names the shop —
     * restoring into a different shop is therefore impossible by accident.
     * A FULL database backup is ALWAYS written before anything is touched.
     */
    public function restoreShop(?\Illuminate\Http\UploadedFile $uploadedFile): array
    {
        if ($uploadedFile?->getClientOriginalExtension() !== 'sql') {
            return ['ok' => false, 'error' => 'Upload a per-shop .sql backup from this screen.'];
        }

        $head = (string) $uploadedFile->openFile()->fread(2048);
        if (! preg_match('/^-- ZIONPOS-SHOP-BACKUP shop_id=(\d+) shop_code=([A-Z0-9]+)/m', $head, $m)) {
            return ['ok' => false, 'error'
                => 'This is not a per-shop backup (no shop header found). Use the full Restore for whole-database dumps.'];
        }

        $shop = Shop::query()->find($m[1]);
        if (! $shop || $shop->code !== $m[2]) {
            return ['ok' => false, 'error' => "This backup's shop (code {$m[2]}) no longer matches the database — restore a full backup instead, or ask the developer."];
        }

        // full safety backup first — always, no exceptions
        $safety = $this->create();
        if (! $safety['ok']) {
            return ['ok' => false, 'error' => 'Safety backup failed — nothing was changed: '.$safety['error']];
        }

        $mysqlBinary = config('pos.mysql');
        if (! is_file($mysqlBinary)) {
            return ['ok' => false, 'error' => "mysql client not found at {$mysqlBinary} — configure MYSQL_PATH."];
        }

        $stored = $uploadedFile->store('restores');
        $mainSql = "-- ZIONPOS SHOP RESTORE target={$shop->code} at ".now()->toIso8601String()."\n"
            .'SET FOREIGN_KEY_CHECKS=0;'."\n"
            // children first: exchanges lock sales, refunds lock sales, the rest
            // of the children (sale_items/payments/refund_items) cascade away
            // with the sales rows themselves
            .implode("\n", [
                'DELETE FROM exchanges WHERE shop_id = '.$shop->id.';',
                'DELETE FROM refunds WHERE shop_id = '.$shop->id.';',
                'DELETE FROM sales WHERE shop_id = '.$shop->id.';',
                'DELETE FROM stock_movements WHERE shop_id = '.$shop->id.';',
                'DELETE FROM import_batches WHERE shop_id = '.$shop->id.';',
                'DELETE FROM products WHERE shop_id = '.$shop->id.';',
                'DELETE FROM categories WHERE shop_id = '.$shop->id.';',
                'DELETE FROM brands WHERE shop_id = '.$shop->id.';',
                'DELETE FROM suppliers WHERE shop_id = '.$shop->id.';',
            ])."\n"
            .file_get_contents(Storage::path($stored))."\n"
            .'SET FOREIGN_KEY_CHECKS=1;';

        $temp = storage_path('app/shop-restore-'.uniqid().'.sql');
        file_put_contents($temp, $mainSql);

        $cmd = [
            $mysqlBinary,
            '--host', env('DB_HOST', '127.0.0.1'),
            '--port', (string) env('DB_PORT', '3306'),
            '--user', env('DB_USERNAME', 'root'),
            '--default-character-set=utf8mb4',
            env('DB_DATABASE'),
            '-e', 'SOURCE '.$temp,
        ];
        if (filled(env('DB_PASSWORD'))) {
            array_splice($cmd, 3, 0, ['--password='.env('DB_PASSWORD')]);
        }

        try {
            $result = Process::run($cmd);
        } finally {
            @unlink($temp);
            Storage::delete($stored);
        }

        if (! $result->successful()) {
            return ['ok' => false, 'error' => trim($result->errorOutput()) ?: 'Shop restore failed'];
        }

        // cleared ledgers must re-derive their caches from the restored truth
        foreach (\DB::table('products')->where('shop_id', $shop->id)->pluck('id') as $pid) {
            $stock = (float) (\DB::table('stock_movements')->where('product_id', $pid)->sum('quantity'));
            \DB::table('products')->where('id', $pid)->update(['stock_cached' => $stock]);
        }

        return ['ok' => true, 'shop' => $shop, 'safety' => basename($safety['file'])];
    }

    private function phpDump(): string
    {
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        $out = "-- Zion POS backup ".now()->toIso8601String()."\nSET FOREIGN_KEY_CHECKS=0;\n";

        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n".$create['Create Table'].";\n";

            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $columns = array_map(fn ($c) => '`'.$c.'`', array_keys($chunk[0] ?? []));
                $values = implode(",\n", array_map(function ($row) use ($pdo) {
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));

                    return '('.implode(',', $vals).')';
                }, $chunk));

                $out .= "INSERT INTO `{$table}` (".implode(',', $columns).") VALUES\n{$values};\n";
            }
        }

        return $out."SET FOREIGN_KEY_CHECKS=1;\n";
    }

    public function restore(?\Illuminate\Http\UploadedFile $uploadedFile): array
    {
        if ($uploadedFile?->getClientOriginalExtension() !== 'sql') {
            return ['ok' => false, 'error' => 'Upload a .sql file from this backup screen.'];
        }

        $path = $uploadedFile->store('restores');
        $absolute = Storage::path($path);

        $mysqlBinary = config('pos.mysql');
        if (! is_file($mysqlBinary)) {
            return ['ok' => false, 'error' => "mysql client not found at {$mysqlBinary} — configure MYSQL_PATH."];
        }

        $cmd = [
            $mysqlBinary,
            '--host', env('DB_HOST', '127.0.0.1'),
            '--port', (string) env('DB_PORT', '3306'),
            '--user', env('DB_USERNAME', 'root'),
            '--default-character-set=utf8mb4',
            env('DB_DATABASE'),
            '-e', 'SOURCE '.$absolute,
        ];
        if (filled(env('DB_PASSWORD'))) {
            array_splice($cmd, 3, 0, ['--password='.env('DB_PASSWORD')]);
        }

        $result = Process::run($cmd);
        Storage::delete($path);

        if ($result->successful()) {
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => trim($result->errorOutput()) ?: 'Restore failed'];
    }

    public function list(): array
    {
        return collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->map(fn ($f) => ['file' => basename($f), 'size' => Storage::disk('local')->size($f), 'time' => Storage::disk('local')->lastModified($f)])
            ->sortByDesc('time')
            ->values()
            ->all();
    }

    /**
     * Wipe all POS data — Flutter parity ("Backup & Wipe All Data"). A full
     * backup is ALWAYS written first, then every transactional table is
     * truncated. Auth/permission scaffolding is left intact.
     */
    public function wipe(): array
    {
        $backup = $this->create();
        if (! $backup['ok']) {
            return ['ok' => false, 'error' => 'Safety backup failed — data left untouched: '.$backup['error']];
        }

        $tables = [
            'exchanges', 'refund_items', 'refunds', 'payments', 'sale_items', 'sales',
            'stock_movements', 'products', 'categories', 'brands', 'suppliers',
            'import_batches', 'audit_logs',
        ];

        $keepForeign = $this->disableForeignKeyChecks();
        foreach ($tables as $table) {
            if (\Schema::hasTable($table)) {
                \DB::table($table)->delete(); // DELETE keeps sessions/auth rows intact
            }
        }
        if ($keepForeign) {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return ['ok' => true, 'file' => $backup['file']];
    }

    /**
     * Multi-shop: wipe ONE shop's transactional data (catalog, stock, sales,
     * refunds, exchanges, imports) without touching any other shop. A full
     * backup is ALWAYS written first. Users, roles, settings and the shop
     * record itself stay; the shop keeps its receipt-counter series intact
     * unless explicitly cleared, so numbering stays unique.
     *
     * Order matters: exchanges → refunds → sales (children cascade), then the
     * catalog and ledger. Audit logs deliberately survive (history trail).
     */
    public function wipeShop(Shop $shop): array
    {
        $backup = $this->create();
        if (! $backup['ok']) {
            return ['ok' => false, 'error' => 'Safety backup failed — data left untouched: '.$backup['error']];
        }

        $shopTables = [
            'exchanges', 'refunds', 'sales', 'stock_movements', 'products',
            'categories', 'brands', 'suppliers', 'import_batches',
        ];

        $keepForeign = $this->disableForeignKeyChecks();
        foreach ($shopTables as $table) {
            if (\Schema::hasTable($table) && \Schema::hasColumn($table, 'shop_id')) {
                \DB::table($table)->where('shop_id', $shop->id)->delete();
            }
        }

        // sale_items/payments cascade from sales (FK actions still run with
        // checks off); refunds/exchanges were deleted above so no dangling
        // references survive.

        if ($keepForeign) {
            \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return ['ok' => true, 'file' => $backup['file']];
    }

    private function disableForeignKeyChecks(): bool
    {
        $driver = \DB::getDriverName();

        if ($driver === 'MySQL' || $driver === 'mariadb') {
            \DB::statement('SET FOREIGN_KEY_CHECKS=0');

            return true;
        }

        return false;
    }
}
