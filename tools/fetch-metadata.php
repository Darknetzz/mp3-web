<?php
/* ──────────────────────────────────────────────────────────────────────────
 * Look the whole library up online (see metajob.php). Started in the
 * background by Settings → Song info → Fetch metadata, or by hand / cron:
 *
 *   php tools/fetch-metadata.php [--mode=missing|all]
 *
 *   missing  songs not looked up yet (default)
 *   all      every song again
 * ────────────────────────────────────────────────────────────────────────── */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
chdir(dirname(__DIR__));          // _includes.php works with paths relative to the app
require dirname(__DIR__) . '/_includes.php';
set_time_limit(0);
ignore_user_abort(true);

$opts = getopt('', ['mode::', 'help']);
if (isset($opts['help'])) {
    echo "Usage: php tools/fetch-metadata.php [--mode=missing|all]\n";
    exit(0);
}
if (!getConfig('online_metadata')) {
    fwrite(STDERR, "Online song info is turned off in Settings.\n");
    exit(1);
}
exit(metaJobRun((string) ($opts['mode'] ?? 'missing'), true));
