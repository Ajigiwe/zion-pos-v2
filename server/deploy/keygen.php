<?php

/**
 * One-off helper: prints a Laravel APP_KEY for the cPanel .env — no shell,
 * no artisan, no framework needed (pure PHP), so it can be dropped into any
 * web root (app docroot or the layout-B shim folder) and visited once.
 *
 * Visit https://…/keygen.php once in a browser, paste the line into .env,
 * THEN DELETE THIS FILE from the web root.
 */

header('Content-Type: text/plain; charset=utf-8');

// The real APP_ENV lives in the app's .env, which we don't load. Hunt for it
// from common locations and require ?confirm=1 once it says production.
$envFile = null;
foreach ([dirname(__DIR__), dirname(__DIR__, 2), dirname(__DIR__, 3)] as $ancestor) {
    foreach ([$ancestor.'/.env', $ancestor.'/zion-pos/.env'] as $candidate) {
        if (is_file($candidate)) { $envFile = $candidate; break 2; }
    }
}
if ($envFile
    && preg_match('/^APP_ENV\s*=\s*production\b/m', (string) file_get_contents($envFile))
    && ! isset($_GET['confirm'])) {
    exit("Add ?confirm=1 to prove it is you, get your key, and delete this file.\n");
}

echo "Copy this line into .env:\n\nAPP_KEY=base64:".base64_encode(random_bytes(32))."\n\nThen DELETE keygen.php from the web root.\n";
