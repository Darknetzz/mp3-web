<?php
/* ──────────────────────────────────────────────────────────────────────────
 * "Fetch metadata" job: looks the whole library up online (metadata.php) in
 * a background process, so it keeps going when the page is closed.
 *
 *   Start   api.php?action=metaJobStart (POST)  → runs tools/fetch-metadata.php
 *   Status  api.php?action=metaJobStatus        → progress + songs without info
 *   Stop    api.php?action=metaJobStop (POST)   → stops after the current song
 *
 * The runner also works by hand or from cron:
 *   php tools/fetch-metadata.php [--mode=missing|all]
 *
 * State lives in cache/online/job.json. A job counts as running while its
 * heartbeat (written for every song) is fresh, so a killed runner never
 * blocks the next one for long.
 * ────────────────────────────────────────────────────────────────────────── */

const METAJOB_FILE      = META_CACHE_DIR . '/job.json';
const METAJOB_LOG       = META_CACHE_DIR . '/job.log';
const METAJOB_STALE     = 180;  // seconds without a heartbeat before a job counts as dead
const METAJOB_LOG_LINES = 50;
const METAJOB_MODES     = ['missing' => 'Songs not looked up yet', 'all' => 'Every song (refresh)'];

/** The job's state, or a blank "idle" one. */
function metaJobRead(): array {
    $job = is_file(METAJOB_FILE) ? json_decode((string) @file_get_contents(METAJOB_FILE), true) : null;
    return is_array($job) ? $job : ['state' => 'idle'];
}

/** Read-modify-write the job state under a lock. */
function metaJobUpdate(callable $fn): array {
    if (!is_dir(META_CACHE_DIR)) @mkdir(META_CACHE_DIR, 0775, true);
    $fh = @fopen(METAJOB_FILE, 'c+');
    if (!$fh) return metaJobRead();
    flock($fh, LOCK_EX);
    $job = json_decode((string) stream_get_contents($fh), true);
    $job = $fn(is_array($job) ? $job : ['state' => 'idle']);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $job;
}

/** Is a job running (or about to)? */
function metaJobAlive(array $job): bool {
    return in_array($job['state'] ?? '', ['starting', 'running', 'stopping'], true)
        && time() - (int) ($job['heartbeat'] ?? 0) < METAJOB_STALE;
}

/** Add a line to the job's log (kept short). */
function metaJobLog(array &$job, string $line): void {
    $job['log'][] = [time(), $line];   // the page formats the time in the viewer's timezone
    $job['log'] = array_slice($job['log'], -METAJOB_LOG_LINES);
}

/** Every song in the library: file name => absolute path. */
function metaJobLibrary(): array {
    $dir = realpath((string) getConfig('audio_path'));
    if ($dir === false) return [];
    $out = [];
    foreach (array_keys(playlistLibrary()) as $file) {
        $out[$file] = $dir . DIRECTORY_SEPARATOR . $file;
    }
    return $out;
}

/** The command that runs the job, for the background start and for people to run by hand. */
function metaJobCommand(string $mode, ?string $php = null): string {
    return ($php ? escapeshellarg($php) : 'php') . ' ' . escapeshellarg(__DIR__ . '/tools/fetch-metadata.php') . ' --mode=' . $mode;
}

/** The PHP command-line binary, or null. */
function metaJobPhp(): ?string {
    if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
        return null;
    }
    if (PHP_SAPI === 'cli' && PHP_BINARY) return PHP_BINARY;
    foreach (['php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, 'php'] as $name) {
        $bin = trim((string) @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($bin !== '') return $bin;
    }
    return null;
}

/* ───────────────────────────────── Runner ───────────────────────────────── */
/** Run the job in this process (tools/fetch-metadata.php). Returns the exit code. */
function metaJobRun(string $mode, bool $verbose = false): int {
    $mode = array_key_exists($mode, METAJOB_MODES) ? $mode : 'missing';
    $say  = function (string $line) use ($verbose) { if ($verbose) echo $line, "\n"; };

    $id = bin2hex(random_bytes(4));
    $claimed = false;
    metaJobUpdate(function ($job) use ($id, $mode, &$claimed) {
        // A job started from the page is "starting" and waiting for us; anything alive otherwise wins.
        if (metaJobAlive($job) && ($job['state'] ?? '') !== 'starting') return $job;
        $claimed = true;
        return [
            'id' => $id, 'state' => 'running', 'mode' => $mode, 'pid' => getmypid(),
            'started' => time(), 'heartbeat' => time(), 'finished' => null,
            'total' => 0, 'done' => 0, 'found' => 0, 'notFound' => 0, 'skipped' => 0, 'errors' => 0,
            'current' => '', 'message' => '', 'log' => [],
        ];
    });
    if (!$claimed) {
        $say('Another fetch is already running.');
        return 2;
    }

    $songs = metaJobLibrary();
    metaJobUpdate(function ($job) use ($songs) {
        $job['total'] = count($songs);
        metaJobLog($job, 'Started: ' . count($songs) . ' songs (' . $job['mode'] . ').');
        return $job;
    });
    $say('Fetching metadata for ' . count($songs) . " songs ($mode).");

    $errorsInRow = 0;
    $backoffs = 0;
    $final = 'done';
    $message = '';
    foreach ($songs as $file => $path) {
        // Asked to stop, or the setting was turned off?
        $job = metaJobRead();
        if (($job['id'] ?? '') !== $id) { $final = 'stopped'; $message = 'Replaced by another job.'; break; }
        if (($job['state'] ?? '') === 'stopping') { $final = 'stopped'; break; }

        metaJobUpdate(function ($job) use ($file) {
            $job['current'] = $file;
            $job['heartbeat'] = time();
            return $job;
        });

        $result = 'skipped';
        $line = '';
        try {
            $cached = metaCached($path);
            if ($mode === 'missing' && $cached) {
                $result = 'skipped';
            } else {
                $r = metaRefresh($path, $mode === 'all');
                $result = !empty($r['found']) ? 'found' : 'notFound';
                $line = ($result === 'found' ? 'Found ' : 'Not found ') . $file
                    . ($result === 'found' ? ' → ' . trim(($r['artist']['name'] ?? '') . ' – ' . ($r['recording']['title'] ?? ''), ' –') : '');
            }
            $errorsInRow = 0;
        } catch (Throwable $e) {
            $result = 'errors';
            $line = 'Error ' . $file . ': ' . $e->getMessage();
            $errorsInRow++;
        }

        metaJobUpdate(function ($job) use ($result, $line) {
            $job['done']++;
            $job[$result]++;
            $job['heartbeat'] = time();
            if ($line !== '') metaJobLog($job, $line);
            return $job;
        });
        if ($line !== '') $say($line);

        // MusicBrainz down or rate limiting us: wait, then give up after a while.
        if ($errorsInRow >= 3) {
            $backoffs++;
            if ($backoffs > 5) { $final = 'error'; $message = 'Too many network errors; stopped. Try again later.'; break; }
            metaJobUpdate(function ($job) { metaJobLog($job, 'Network trouble: waiting a minute.'); return $job; });
            $say('Network trouble: waiting a minute.');
            for ($i = 0; $i < 60; $i++) {
                sleep(1);
                if ($i % 10 === 0) metaJobUpdate(function ($job) { $job['heartbeat'] = time(); return $job; });
            }
            $errorsInRow = 0;
        }
    }

    $job = metaJobUpdate(function ($job) use ($id, $final, $message) {
        if (($job['id'] ?? '') !== $id) return $job;
        $job['state'] = $final;
        $job['message'] = $message;
        $job['current'] = '';
        $job['finished'] = time();
        $job['heartbeat'] = time();
        metaJobLog($job, $final === 'done' ? 'Finished.' : ($final === 'stopped' ? 'Stopped.' : $message));
        return $job;
    });
    $say(sprintf('%s: %d found, %d not found, %d already known, %d errors.',
        ucfirst($final), $job['found'] ?? 0, $job['notFound'] ?? 0, $job['skipped'] ?? 0, $job['errors'] ?? 0));
    return $final === 'error' ? 1 : 0;
}

/* ───────────────────────────────── API ──────────────────────────────────── */
/** Songs that weren't found online and haven't been edited by hand, plus how many were never looked up. */
function metaJobUnknownSongs(): array {
    $notFound = [];
    $unchecked = 0;
    foreach (metaJobLibrary() as $file => $path) {
        $cacheFile = metaCacheBase($path) . '.json';
        $rec = is_file($cacheFile) ? json_decode((string) @file_get_contents($cacheFile), true) : null;
        if (!is_array($rec)) { $unchecked++; continue; }
        if (!empty($rec['found'])) continue;
        $o = songOverride($file);
        $meta = getSongMeta($path);
        $notFound[] = [
            'file'   => $file,
            'title'  => $meta['title'],
            'artist' => $meta['artist'],
            'edited' => !empty($o),
        ];
    }
    usort($notFound, function ($a, $b) { return [$a['edited'], strcasecmp($a['file'], $b['file'])] <=> [$b['edited'], 0]; });
    return ['notFound' => $notFound, 'unchecked' => $unchecked];
}

function metaJobStatus(): void {
    session_write_close();
    $job = metaJobRead();
    if (in_array($job['state'] ?? '', ['starting', 'running', 'stopping'], true) && !metaJobAlive($job)) {
        $job['message'] = ($job['state'] ?? '') === 'starting'
            ? 'The job didn’t start. Run it by hand on the server: ' . metaJobCommand($job['mode'] ?? 'missing')
            : 'The job stopped unexpectedly.';
        $job['state'] = 'error';
    }
    $job['alive'] = metaJobAlive($job);
    unset($job['pid']);
    apiResponse("success", "ok", [
        'job'     => $job,
        'library' => metaJobUnknownSongs(),
        'enabled' => (bool) getConfig('online_metadata'),
        'command' => metaJobCommand('missing'),
    ]);
}

function metaJobStart($mode): void {
    if (!getConfig('online_metadata')) {
        apiResponse("error", "Turn on Online song info first.");
    }
    $mode = array_key_exists((string) $mode, METAJOB_MODES) ? (string) $mode : 'missing';
    if (metaJobAlive(metaJobRead())) {
        apiResponse("error", "A fetch is already running.");
    }
    $php = metaJobPhp();
    if ($php === null) {
        apiResponse("error", "The server can’t start background jobs. Run this on the server instead: <code>"
            . htmlspecialchars(metaJobCommand($mode)) . "</code>");
    }
    $job = metaJobUpdate(function ($job) use ($mode) {
        if (metaJobAlive($job)) return $job;
        return ['state' => 'starting', 'mode' => $mode, 'heartbeat' => time(), 'started' => time(),
                'total' => 0, 'done' => 0, 'found' => 0, 'notFound' => 0, 'skipped' => 0, 'errors' => 0,
                'current' => '', 'message' => '', 'log' => []];
    });
    if (($job['state'] ?? '') !== 'starting') {
        apiResponse("error", "A fetch is already running.");
    }
    // Detached, so it outlives this request (and the PHP-FPM worker).
    $setsid = trim((string) @shell_exec('command -v setsid 2>/dev/null'));
    $cmd = ($setsid !== '' ? 'setsid ' : '') . 'nohup ' . metaJobCommand($mode, $php)
        . ' > ' . escapeshellarg(METAJOB_LOG) . ' 2>&1 < /dev/null &';
    exec($cmd);
    apiResponse("success", "Fetching metadata in the background.", ['job' => metaJobRead()]);
}

function metaJobStop(): void {
    $job = metaJobUpdate(function ($job) {
        if (metaJobAlive($job)) {
            $job['state'] = ($job['state'] ?? '') === 'starting' ? 'stopped' : 'stopping';
        }
        return $job;
    });
    apiResponse("success", "Stopping after the current song.", ['job' => $job]);
}
