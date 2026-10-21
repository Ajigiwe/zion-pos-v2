<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/** `php artisan pos:backup` — same guts as the settings-page backup card. */
class PosBackupCommand extends Command
{
    protected $signature = 'pos:backup {--prune=30 : Keep the newest N backups}';

    protected $description = 'Create a MySQL dump backup of the POS database';

    public function handle(BackupService $backups): int
    {
        $result = $backups->create();

        if (! $result['ok']) {
            $this->error('Backup failed: '.$result['error']);

            return self::FAILURE;
        }

        $this->info('Backup written: '.\Illuminate\Support\Facades\Storage::disk('local')->path($result['file']));
        $this->pruneOld((int) $this->option('prune'));

        return self::SUCCESS;
    }

    private function pruneOld(int $keep): void
    {
        $files = collect(\Illuminate\Support\Facades\Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.sql'))
            ->sortByDesc(fn ($f) => \Illuminate\Support\Facades\Storage::disk('local')->lastModified($f))
            ->values();

        $files->slice($keep)->each(fn ($f) => \Illuminate\Support\Facades\Storage::disk('local')->delete($f));
    }
}
