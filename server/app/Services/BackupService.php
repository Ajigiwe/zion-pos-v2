<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
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
                array_splice($cmd, 6, 0, ['--password='.env('DB_PASSWORD')]);
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
            array_splice($cmd, 6, 0, ['--password='.env('DB_PASSWORD')]);
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
