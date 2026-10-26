<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * In-panel self-deploy (Superadmin only).
 * Pulls the latest build of Ajigiwe/zion-pos-v2 from GitHub, stages it beside
 * the live app, carries over the production .env + vendor + storage, then
 * swaps the folders atomically. No shell, no FTP - the panel is the deploy
 * platform and the repository is the source of truth.
 */
class DeployController extends Controller
{
    public const REPO = 'Ajigiwe/zion-pos-v2';
    public const BRANCH = 'main';

    public function index()
    {
        $state = $this->readState();
        $remote = $this->latestRemote();
        return view('deploy.index', ['state' => $state, 'remote' => $remote]);
    }

    public function run(Request $request)
    {
        abort_unless(auth()->user()->hasRole('superadmin'), 403);

        $steps = [];
        $base = base_path();                       // .../zion-pos
        $home = dirname($base);                    // /home/ziongrou
        $newDir = $home . DIRECTORY_SEPARATOR . 'zion-pos-new';
        $oldDir = $home . DIRECTORY_SEPARATOR . 'zion-pos-old';
        $fail = function (string $msg, array $steps) use ($home, $base) {
            // if carried dirs were renamed out of the live tree, put them back
            foreach (['vendor', 'storage'] as $d) {
                $carried = $home . DIRECTORY_SEPARATOR . 'zion-pos-new' . DIRECTORY_SEPARATOR . $d;
                if (!is_dir($base . DIRECTORY_SEPARATOR . $d) && is_dir($carried)) {
                    @rename($carried, $base . DIRECTORY_SEPARATOR . $d);
                    $steps[] = 'restored ' . $d . '/ into the live tree';
                }
            }
            foreach (['zion-pos-new', 'zion-pos-old', '.deploy-stage'] as $d) {
                $p = $home . DIRECTORY_SEPARATOR . $d;
                if (is_dir($p)) { $this->rmDir($p); }
            }
            Log::error('deploy: ' . $msg);
            return back()->with('deploy_error', $msg)->with('deploy_steps', $steps);
        };

        try {
            @ini_set('max_execution_time', '300');
            @set_time_limit(300);
        } catch (\Throwable) {
            $steps[] = 'note: could not extend the PHP time limit - large repos may hit the request timeout';
        }

        // 1. clean previous stage artifacts
        foreach ([$newDir, $oldDir] as $d) {
            if (is_dir($d)) { $this->rmDir($d); $steps[] = 'removed leftover stage dir ' . basename($d); }
        }

        // 2. resolve the commit to ship
        $pin = trim((string) $request->input('pin'));
        if ($pin !== '' && !preg_match('/^[0-9a-f]{7,40}$/i', $pin)) {
            return $fail('The pinned ref must be a 7-40 hex character commit SHA.', $steps);
        }
        $headers = [];
        $token = env('GITHUB_TOKEN');
        if ($token) { $headers['Authorization'] = 'token ' . $token; }

        if ($pin === '') {
            $sha = $this->latestSha($headers, $steps);
            if (is_null($sha)) { return $fail('Could not read the latest commit from GitHub (network or rate limit).', $steps); }
        } else {
            $sha = strtolower($pin);
            $steps[] = "pinned SHA: {$sha}";
        }

        // 3. download the source zip
        $zipPath = storage_path('app/deploy-' . $sha . '.zip');
        try {
            $res = Http::withHeaders($headers)->timeout(120)
                ->sink($zipPath)
                ->get('https://codeload.github.com/' . self::REPO . '/zip/' . $sha);
            if (!$res->successful() || filesize($zipPath) < 1000) {
                return $fail('GitHub zip download failed (HTTP ' . $res->status() . ').', $steps);
            }
        } catch (\Throwable $e) {
            return $fail('GitHub zip download threw: ' . $e->getMessage(), $steps);
        }
        $steps[] = 'downloaded repo snapshot: ' . basename($zipPath) . ' (' . number_format(filesize($zipPath)) . ' bytes)';

        // 4. extract
        $staging = $home . DIRECTORY_SEPARATOR . '.deploy-stage';
        $this->rmDir($staging); mkdir($staging, 0755, true);
        $zip = new ZipArchive();
        if (($err = $zip->open($zipPath)) !== true || !$zip->extractTo($staging)) {
            return $fail('ZipArchive could not extract the snapshot (' . $err . ').', $steps);
        }
        $zip->close();
        $entries = glob($staging . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        if (count($entries) !== 1) { return $fail('Unexpected snapshot layout (got ' . count($entries) . ' root dirs).', $steps); }
        $snapshot = $entries[0];
        // our repo keeps the Laravel app under server/ - fall back to a flat snapshot root
        if (is_dir($snapshot . DIRECTORY_SEPARATOR . 'server')
            && is_file($snapshot . DIRECTORY_SEPARATOR . 'server' . DIRECTORY_SEPARATOR . 'artisan')) {
            $snapshot = $snapshot . DIRECTORY_SEPARATOR . 'server';
        }
        if (!rename($snapshot, $newDir)) { return $fail('Could not move the snapshot into place.', $steps); }
        $this->rmDir($staging);
        $steps[] = 'snapshot staged to ' . basename($newDir);

        // 5. carry production state over
        //    .env  : tiny file copy
        //    vendor/: RENAME (same filesystem -> instant, no 120s execution-time kill)
        //    storage/: RENAME over the skeleton
        $carryFile = function (string $src, string $dst, array &$steps) {
            if (!is_file($src)) { $steps[] = 'carry: source file missing - ' . basename($src); return false; }
            return @copy($src, $dst);
        };
        if (!$carryFile($base . DIRECTORY_SEPARATOR . '.env', $newDir . DIRECTORY_SEPARATOR . '.env', $steps)) {
            return $fail('Production .env could not be carried into the stage - deploy aborted, live app untouched.', $steps);
        }
        $steps[] = 'carried .env';
        $newVendor = $newDir . DIRECTORY_SEPARATOR . 'vendor';
        if (is_dir($newVendor)) { $this->rmDir($newVendor); }
        if (!@rename($base . DIRECTORY_SEPARATOR . 'vendor', $newVendor)) {
            return $fail('vendor/ could not be renamed into the stage.', $steps);
        }
        $steps[] = 'carried vendor/ (renamed)';
        $newStorage = $newDir . DIRECTORY_SEPARATOR . 'storage';
        if (is_dir($newStorage)) { $this->rmDir($newStorage); }
        if (!@rename($base . DIRECTORY_SEPARATOR . 'storage', $newStorage)) {
            $steps[] = 'WARNING: storage/ rename failed (deployed skeleton storage is used instead)';
        } else {
            $steps[] = 'carried storage/ (renamed)';
        }
        if (!$this->copyDir($base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache', $newDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache', [])) {
            $steps[] = 'note: bootstrap/cache carry skipped';
        }

        // compiled blade views must NOT survive the swap: zip entries keep commit
        // timestamps, so sources can appear OLDER than carried compiled views and
        // Laravel would keep rendering the previous release's templates
        $viewsDir = $newDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views';
        if (is_dir($viewsDir)) {
            $this->rmDir($viewsDir);
            @mkdir($viewsDir, 0755, true);
            $steps[] = 'cleared compiled views (stale-template hazard)';
        }

        // sanity gate: the stage must be a complete Laravel app root before it may be swapped in
        foreach (['artisan', 'public' . DIRECTORY_SEPARATOR . 'index.php', '.env', 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php'] as $f) {
            if (!is_file($newDir . DIRECTORY_SEPARATOR . $f)) {
                return $fail('Sanity check failed: ' . $f . ' is missing from the staged tree - deploy aborted.', $steps);
            }
        }
        $steps[] = 'sanity check passed (artisan, public/index.php, .env, vendor/autoload.php)';

        // 6. fixture the state file INSIDE the new tree so it survives the swap
        $state = ['sha' => $sha, 'deployed_at' => now()->format('Y-m-d H:i:s'), 'by' => auth()->user()->username,
                  'repo' => self::REPO . '@' . self::BRANCH];
        @file_put_contents($newDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'deploy-state.json', json_encode($state, JSON_PRETTY_PRINT));

        // 7. atomic-ish swap
        if (!rename($base, $oldDir)) { return $fail('Swapping out the live folder failed - live app still serving.', $steps); }
        if (!rename($newDir, $base)) {
            rename($oldDir, $base); // roll back immediately
            return $fail('Swapping the new folder in failed - rolled back, live app still serving.', $steps);
        }
        $steps[] = 'folder swap complete';
        $this->rmDir($oldDir);
        @unlink($zipPath);
        $steps[] = 'cleanup done (old tree + zip removed)';

        // 8. finalize the new release: apply pending migrations and clear
        //    compiled caches. Executed in-process against the freshly swapped
        //    tree — the migrator reads database/migrations fresh from disk, and
        //    the config/view caches it clears are files on disk too. Without
        //    this step every schema-bound release served SQLSTATE 500s until
        //    migrations were applied by hand.
        try {
            $out = \Artisan::call('migrate', ['--force' => true]);
            $steps[] = 'migrate: '.trim(str_replace(["\r", "\n"], ' ', \Artisan::output()) ?: 'nothing to migrate');
        } catch (\Throwable $e) {
            $steps[] = 'MIGRATE FAILED — data intact, app may serve 500s until migrations are applied: '.$e->getMessage();
            Log::error('deploy: migrate failed for ' . $sha . ': ' . $e->getMessage());
        }
        try {
            \Artisan::call('optimize:clear');
            $steps[] = 'caches cleared (config/routes/views/events)';
        } catch (\Throwable) {
            $steps[] = 'note: cache clear skipped';
        }

        Log::info('deploy: live commit ' . $sha . ' deployed by ' . auth()->user()->username);
        return back()->with('deploy_result', $state)->with('deploy_steps', $steps);
    }

    /* ---------- helpers ---------- */

    /**
     * Post-release kicker: apply pending migrations and clear compiled caches
     * against the LIVE tree. Exists because the deploy swap runs in the old
     * app instance — a migrate step added to the new release only executes on
     * the deploy AFTER the one that ships it, and a schema-behind release 500s.
     */
    public function finalize()
    {
        abort_unless(auth()->user()->hasRole('superadmin'), 403);

        $steps = [];
        try {
            \Artisan::call('migrate', ['--force' => true]);
            $out = trim(\Artisan::output());
            $steps[] = 'migrate: '.str_replace(["\r", "\n"], ' · ', $out !== '' ? $out : 'nothing to migrate');
        } catch (\Throwable $e) {
            $steps[] = 'MIGRATE FAILED: '.$e->getMessage();
            Log::error('finalize: migrate failed: '.$e->getMessage());

            return back()->with('deploy_error', 'Migrate failed — '.$e->getMessage())->with('deploy_steps', $steps);
        }
        try {
            \Artisan::call('optimize:clear');
            $steps[] = 'caches cleared';
        } catch (\Throwable) {
            $steps[] = 'note: cache clear skipped';
        }

        return back()->with('deploy_result', ['sha' => readState()['sha'] ?? null, 'ran' => 'finalize'])
            ->with('deploy_steps', $steps);
    }

    private function readState(): ?array
    {
        $f = storage_path('app/deploy-state.json');
        return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    }

    private function latestRemote(): ?array
    {
        try {
            $headers = [];
            if ($token = env('GITHUB_TOKEN')) { $headers['Authorization'] = 'token ' . $token; }
            $r = Http::withHeaders($headers)->timeout(15)
                ->accept('application/vnd.github+json')
                ->get('https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH);
            if (!$r->successful()) { return null; }
            $j = $r->json();
            return ['sha' => $j['sha'] ?? null,
                    'message' => (isset($j['commit']['message']) ? strtok($j['commit']['message'], "\n") : null),
                    'date' => $j['commit']['committer']['date'] ?? null];
        } catch (\Throwable) { return null; }
    }

    private function latestSha(array $headers, array &$steps): ?string
    {
        try {
            $r = Http::withHeaders($headers)->timeout(20)
                ->accept('application/vnd.github+json')
                ->get('https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH);
            if (!$r->successful()) { return null; }
            $sha = $r->json('sha');
            if (!$sha) { return null; }
            $steps[] = 'latest ' . self::BRANCH . ' = ' . $sha;
            return strtolower($sha);
        } catch (\Throwable $e) {
            $steps[] = 'GitHub API error: ' . $e->getMessage();
            return null;
        }
    }

    private function copyDir(string $src, string $dst, array $skip = []): bool
    {
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            if (!is_dir($dst)) { @mkdir($dst, 0755, true); }
            foreach ($it as $item) {
                $rel = substr($item->getPathname(), strlen($src) + 1);
                foreach ($skip as $prefix) { if (str_starts_with($rel, $prefix)) { continue 2; } }
                $target = $dst . DIRECTORY_SEPARATOR . $rel;
                if ($item->isDir()) { @mkdir($target, 0755, true); }
                else { if (!@copy($item->getPathname(), $target)) { return false; } }
            }
            return is_dir($dst);
        } catch (\Throwable) { return false; }
    }

    private function rmDir(string $dir): void
    {
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
            @rmdir($dir);
        } catch (\Throwable) { }
    }
}
