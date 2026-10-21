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
        $fail = function (string $msg, array $steps) {
            Log::error('deploy: ' . $msg);
            return back()->with('deploy_error', $msg)->with('deploy_steps', $steps);
        };

        try {
            @ini_set('max_execution_time', '300');
            @set_time_limit(300);
        } catch (\Throwable) {
            $steps[] = 'note: could not extend the PHP time limit - large repos may hit the request timeout';
        }

        $base = base_path();                       // .../zion-pos
        $home = dirname($base);                    // /home/ziongrou
        $newDir = $home . DIRECTORY_SEPARATOR . 'zion-pos-new';
        $oldDir = $home . DIRECTORY_SEPARATOR . 'zion-pos-old';

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
        if (!rename($entries[0], $newDir)) { return $fail('Could not move the snapshot into place.', $steps); }
        $this->rmDir($staging);
        $steps[] = 'snapshot staged to ' . basename($newDir);

        // 5. carry production state over
        $carry = function (string $src, string $dst, array $skip = []) use (&$steps) {
            if (!file_exists($src)) { $steps[] = 'carry: source missing - ' . basename($src); return false; }
            return $this->copyDir($src, $dst, $skip);
        };
        if (!$carry($base . DIRECTORY_SEPARATOR . '.env', $newDir . DIRECTORY_SEPARATOR . '.env')) {
            return $fail('Production .env could not be carried into the stage - deploy aborted, live app untouched.', $steps);
        }
        $steps[] = 'carried .env';
        if (!$carry($base . DIRECTORY_SEPARATOR . 'vendor', $newDir . DIRECTORY_SEPARATOR . 'vendor')) {
            return $fail('vendor/ could not be carried into the stage.', $steps);
        }
        $steps[] = 'carried vendor/';
        if (!$carry($base . DIRECTORY_SEPARATOR . 'storage', $newDir . DIRECTORY_SEPARATOR . 'storage')) {
            $steps[] = 'WARNING: storage/ carry failed (deployed skeleton storage is used instead)';
        } else { $steps[] = 'carried storage/'; }
        if (!$carry($base . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache', $newDir . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache')) {
            $steps[] = 'note: bootstrap/cache carry skipped';
        }

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

        Log::info('deploy: live commit ' . $sha . ' deployed by ' . auth()->user()->username);
        return back()->with('deploy_result', $state)->with('deploy_steps', $steps);
    }

    /* ---------- helpers ---------- */

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
