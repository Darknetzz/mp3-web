<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                                  Playlists                                 */
/* ────────────────────────────────────────────────────────────────────────── */
// One JSON file per playlist in PLAYLIST_DIR, shared by everyone using this
// server. Songs are stored by file name (basename) in the music folder. Songs
// whose file is gone stay in the list (reported as missing) so they come back
// if the file is restored.

const PLAYLIST_DIR       = __DIR__ . '/playlists';
const PLAYLIST_FAVORITES = 'favorites';
const PLAYLIST_NAME_MAX  = 60;
const PLAYLIST_SONGS_MAX = 5000;
const PLAYLIST_IMPORT_MAX_BYTES = 1048576;

/* ─────────────────────────────── Helpers ────────────────────────────────── */
function playlistValidId($id): bool {
    return is_string($id) && ($id === PLAYLIST_FAVORITES || preg_match('/^[a-f0-9]{8}$/', $id) === 1);
}

function playlistPath(string $id): string {
    return PLAYLIST_DIR . '/' . $id . '.json';
}

function playlistEnsureDir(): void {
    if (!is_dir(PLAYLIST_DIR)) {
        if (!@mkdir(PLAYLIST_DIR, 0775, true)) {
            apiResponse("error", "The playlists folder could not be created. Check that the app folder is writable.");
        }
        @chmod(PLAYLIST_DIR, 0775); // group-writable regardless of umask
    } elseif ((fileperms(PLAYLIST_DIR) & 0020) === 0) {
        @chmod(PLAYLIST_DIR, 0775); // fix folders created before this (owner only)
    }
    if (!is_writable(PLAYLIST_DIR)) {
        apiResponse("error", "The folder <code>playlists/</code> is not writable by the web server.");
    }
}

/** File names in the music folder with an allowed extension, keyed by name. */
function playlistLibrary(): array {
    static $library = null;
    if ($library !== null) return $library;
    $library = [];
    $path    = getConfig("audio_path");
    $allowed = (array) getConfig("allowed_types");
    if (!is_dir($path)) return $library;
    foreach (scandir($path) as $file) {
        if ($file === '.' || $file === '..' || !is_file($path . '/' . $file)) continue;
        if (!in_array(pathinfo($file, PATHINFO_EXTENSION), $allowed, true)) continue;
        $library[$file] = true;
    }
    return $library;
}

function playlistCleanName($name): string {
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    return mb_substr($name, 0, PLAYLIST_NAME_MAX);
}

/** Normalise a list of song names (basename, de-duplicated). */
function playlistCleanSongs($songs): array {
    if (is_string($songs)) {
        $decoded = json_decode($songs, true);
        $songs = is_array($decoded) ? $decoded : [$songs];
    }
    $clean = [];
    foreach ((array) $songs as $song) {
        if (!is_string($song)) continue;
        $song = basename(str_replace('\\', '/', $song));
        if ($song === '' || $song === '.' || $song === '..') continue;
        if (!in_array($song, $clean, true)) $clean[] = $song;
        if (count($clean) >= PLAYLIST_SONGS_MAX) break;
    }
    return $clean;
}

/** Summary sent to the browser. */
function playlistView(array $p): array {
    $library = playlistLibrary();
    $missing = array_values(array_filter($p['songs'], function ($s) use ($library) { return !isset($library[$s]); }));
    return [
        "id"      => $p['id'],
        "name"    => $p['name'],
        "version" => (int) $p['version'],
        "songs"   => array_values($p['songs']),
        "count"   => count($p['songs']) - count($missing),
        "missing" => $missing,
        "updated" => $p['updated'] ?? null,
    ];
}

function playlistNew(string $id, string $name, array $songs = []): array {
    $now = date('c');
    return ["id" => $id, "name" => $name, "created" => $now, "updated" => $now, "version" => 1, "songs" => $songs];
}

/**
 * Read-modify-write a playlist under an exclusive lock. $fn receives the
 * playlist (or null) and returns the new playlist array, false to delete,
 * or null to leave it unchanged. Returns the resulting playlist (or null).
 */
function playlistWithLock(string $id, callable $fn, bool $create = false) {
    playlistEnsureDir();
    $path = playlistPath($id);
    if (!$create && !is_file($path) && $id !== PLAYLIST_FAVORITES) {
        return null;
    }
    $fh = @fopen($path, 'c+');
    if (!$fh) {
        apiResponse("error", "Could not open the playlist.");
    }
    flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['songs'])) {
        $data = ($id === PLAYLIST_FAVORITES) ? playlistNew(PLAYLIST_FAVORITES, "Favorites") : null;
    }

    $result = $fn($data);

    if ($result === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($path);
        return null;
    }
    if (is_array($result)) {
        $result['updated'] = date('c');
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        $data = $result;
    } elseif ($data === null) {
        // Nothing to keep: don't leave an empty file behind.
        flock($fh, LOCK_UN);
        fclose($fh);
        if (filesize($path) === 0) @unlink($path);
        return null;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data;
}

function playlistRequireId($id): string {
    $id = (string) $id;
    if (!playlistValidId($id)) {
        apiResponse("error", "That playlist id is not valid.");
    }
    return $id;
}

function playlistNotFound(): void {
    apiResponse("error", "Playlist not found. It may have been deleted.");
}

/* ─────────────────────────────── Actions ────────────────────────────────── */
function listPlaylists(): void {
    $lists = [];
    $hasFavorites = false;
    foreach (glob(PLAYLIST_DIR . '/*.json') ?: [] as $file) {
        $p = json_decode((string) @file_get_contents($file), true);
        if (!is_array($p) || !isset($p['id'], $p['songs']) || !playlistValidId($p['id'])) continue;
        if ($p['id'] === PLAYLIST_FAVORITES) $hasFavorites = true;
        $view = playlistView($p);
        unset($view['songs']);
        $view['missing'] = count($view['missing']);
        $lists[] = $view;
    }
    if (!$hasFavorites) {
        $lists[] = ["id" => PLAYLIST_FAVORITES, "name" => "Favorites", "version" => 0, "count" => 0, "missing" => 0, "updated" => null];
    }
    usort($lists, function ($a, $b) {
        if ($a['id'] === PLAYLIST_FAVORITES) return -1;
        if ($b['id'] === PLAYLIST_FAVORITES) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    apiResponse("success", count($lists) . " playlist(s).", ["playlists" => $lists]);
}

/** Playlists (id + name) that contain $song, Favorites first. */
function playlistsContaining(string $song): array {
    $found = [];
    foreach (glob(PLAYLIST_DIR . '/*.json') ?: [] as $file) {
        $p = json_decode((string) @file_get_contents($file), true);
        if (!is_array($p) || !isset($p['id'], $p['songs']) || !playlistValidId($p['id'])) continue;
        if (in_array($song, (array) $p['songs'], true)) {
            $found[] = ["id" => $p['id'], "name" => (string) ($p['name'] ?? '')];
        }
    }
    usort($found, function ($a, $b) {
        if ($a['id'] === PLAYLIST_FAVORITES) return -1;
        if ($b['id'] === PLAYLIST_FAVORITES) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
    return $found;
}

function getPlaylist($id): void {
    $id = playlistRequireId($id);
    if ($id !== PLAYLIST_FAVORITES && !is_file(playlistPath($id))) {
        playlistNotFound();
    }
    $raw = $id === PLAYLIST_FAVORITES && !is_file(playlistPath($id)) ? null : json_decode((string) @file_get_contents(playlistPath($id)), true);
    $p = is_array($raw) && isset($raw['songs']) ? $raw : playlistNew(PLAYLIST_FAVORITES, "Favorites");
    apiResponse("success", "ok", playlistView($p));
}

function createPlaylist($name, $songs = []): void {
    $name = playlistCleanName($name);
    if ($name === '') {
        apiResponse("error", "Give the playlist a name.");
    }
    playlistEnsureDir();
    do {
        $id = bin2hex(random_bytes(4));
    } while (file_exists(playlistPath($id)));
    $songs = playlistCleanSongs($songs);
    $p = playlistWithLock($id, function () use ($id, $name, $songs) {
        return playlistNew($id, $name, $songs);
    }, true);
    apiResponse("success", "Created <code>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</code>.", playlistView($p));
}

function renamePlaylist($id, $name): void {
    $id = playlistRequireId($id);
    if ($id === PLAYLIST_FAVORITES) {
        apiResponse("error", "Favorites can't be renamed.");
    }
    $name = playlistCleanName($name);
    if ($name === '') {
        apiResponse("error", "Give the playlist a name.");
    }
    $p = playlistWithLock($id, function ($p) use ($name) {
        if ($p === null) return null;
        $p['name'] = $name;
        $p['version']++;
        return $p;
    });
    if ($p === null) playlistNotFound();
    apiResponse("success", "Renamed to <code>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</code>.", playlistView($p));
}

function deletePlaylist($id): void {
    $id = playlistRequireId($id);
    if ($id === PLAYLIST_FAVORITES) {
        apiResponse("error", "Favorites can't be deleted.");
    }
    if (!is_file(playlistPath($id))) playlistNotFound();
    playlistWithLock($id, function () { return false; });
    apiResponse("success", "Playlist deleted.", ["id" => $id]);
}

function addToPlaylist($id, $songs): void {
    $id    = playlistRequireId($id);
    $songs = playlistCleanSongs($songs);
    $library = playlistLibrary();
    $songs = array_values(array_filter($songs, function ($s) use ($library) { return isset($library[$s]); }));
    if (!$songs) {
        apiResponse("error", "Those songs aren't in the library.");
    }
    $added = 0;
    $p = playlistWithLock($id, function ($p) use ($songs, &$added) {
        if ($p === null) return null;
        foreach ($songs as $song) {
            if (!in_array($song, $p['songs'], true) && count($p['songs']) < PLAYLIST_SONGS_MAX) {
                $p['songs'][] = $song;
                $added++;
            }
        }
        if ($added === 0) return null;
        $p['version']++;
        return $p;
    });
    if ($p === null) playlistNotFound();
    $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
    $msg  = $added === 0 ? "Already in <code>$name</code>." : "Added $added song" . ($added === 1 ? "" : "s") . " to <code>$name</code>.";
    apiResponse("success", $msg, playlistView($p) + ["added" => $added]);
}

function removeFromPlaylist($id, $song): void {
    $id   = playlistRequireId($id);
    $song = playlistCleanSongs([$song])[0] ?? '';
    $p = playlistWithLock($id, function ($p) use ($song) {
        if ($p === null) return null;
        $index = array_search($song, $p['songs'], true);
        if ($index === false) return null;
        array_splice($p['songs'], $index, 1);
        $p['version']++;
        return $p;
    });
    if ($p === null) playlistNotFound();
    apiResponse("success", "Removed from <code>" . htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') . "</code>.", playlistView($p));
}

function reorderPlaylist($id, $songs, $ifVersion): void {
    $id    = playlistRequireId($id);
    $songs = playlistCleanSongs($songs);
    $conflict = false;
    $p = playlistWithLock($id, function ($p) use ($songs, $ifVersion, &$conflict) {
        if ($p === null) return null;
        $current = $p['songs'];
        $sortedA = $current; sort($sortedA);
        $sortedB = $songs;   sort($sortedB);
        if ((string) $ifVersion !== (string) $p['version'] || $sortedA !== $sortedB) {
            $conflict = true;
            return null;
        }
        $p['songs'] = $songs;
        $p['version']++;
        return $p;
    });
    if ($p === null) playlistNotFound();
    if ($conflict) {
        apiResponse("conflict", "The playlist changed meanwhile. It has been reloaded.", playlistView($p));
    }
    apiResponse("success", "Order saved.", playlistView($p));
}

function toggleFavorite($song): void {
    $song = playlistCleanSongs([$song])[0] ?? '';
    if ($song === '' || !isset(playlistLibrary()[$song])) {
        apiResponse("error", "That song isn't in the library.");
    }
    $favorite = false;
    $p = playlistWithLock(PLAYLIST_FAVORITES, function ($p) use ($song, &$favorite) {
        $index = array_search($song, $p['songs'], true);
        if ($index === false) {
            $p['songs'][] = $song;
            $favorite = true;
        } else {
            array_splice($p['songs'], $index, 1);
        }
        $p['version']++;
        return $p;
    });
    apiResponse("success", $favorite ? "Added to Favorites." : "Removed from Favorites.", playlistView($p) + ["favorite" => $favorite, "song" => $song]);
}

/** Send a playlist as an .m3u download (not JSON). */
function exportPlaylist($id): void {
    $id = playlistRequireId($id);
    $path = playlistPath($id);
    $p = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
    if (!is_array($p) || !isset($p['songs'])) {
        if ($id !== PLAYLIST_FAVORITES) playlistNotFound();
        $p = playlistNew(PLAYLIST_FAVORITES, "Favorites");
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $base   = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $audio  = trim((string) getConfig("audio_path"), '/');
    $library = playlistLibrary();

    $lines = ["#EXTM3U", "#PLAYLIST:" . $p['name']];
    foreach ($p['songs'] as $song) {
        if (!isset($library[$song])) continue;
        [$m, $s] = array_map('intval', explode(':', getDuration($audio . '/' . $song)) + [0, 0]);
        $lines[] = "#EXTINF:" . ($m * 60 + $s) . "," . pathinfo($song, PATHINFO_FILENAME);
        $lines[] = "$scheme://$host$base/" . implode('/', array_map('rawurlencode', explode('/', $audio))) . "/" . rawurlencode($song);
    }

    $filename = preg_replace('/[^\w\- ]+/u', '', $p['name']) ?: 'playlist';
    header('Content-Type: audio/x-mpegurl; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.m3u"; filename*=UTF-8\'\'' . rawurlencode($p['name']) . '.m3u');
    header('X-Content-Type-Options: nosniff');
    echo implode("\n", $lines) . "\n";
    exit;
}

/** Create a playlist from an uploaded .m3u / .m3u8 file. */
function importPlaylist(array $file): void {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        apiResponse("error", "The playlist file could not be uploaded.");
    }
    if ($file['size'] > PLAYLIST_IMPORT_MAX_BYTES) {
        apiResponse("error", "That playlist file is too large (max 1 MB).");
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['m3u', 'm3u8'], true)) {
        apiResponse("error", "Choose an .m3u or .m3u8 file.");
    }

    $text = (string) file_get_contents($file['tmp_name']);
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text); // BOM
    if ($ext === 'm3u' && !mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    // Library lookup by lower-case name for case-insensitive matching
    $byLower = [];
    foreach (array_keys(playlistLibrary()) as $song) {
        $byLower[mb_strtolower($song)] = $song;
    }

    $name = '';
    $matched = [];
    $missing = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (stripos($line, '#PLAYLIST:') === 0) {
            $name = substr($line, 10);
            continue;
        }
        if ($line[0] === '#') continue;
        $entry = rawurldecode(str_replace('\\', '/', parse_url($line, PHP_URL_PATH) ?? $line));
        $base  = basename($entry);
        $song  = $byLower[mb_strtolower($base)] ?? null;
        if ($song !== null) {
            if (!in_array($song, $matched, true)) $matched[] = $song;
        } else {
            $missing[] = $base;
        }
    }

    if (!$matched) {
        apiResponse("error", "None of the songs in that playlist are in your library.", ["missing" => array_slice($missing, 0, 50)]);
    }
    $name = playlistCleanName($name !== '' ? $name : pathinfo($file['name'], PATHINFO_FILENAME));
    if ($name === '') $name = "Imported playlist";

    playlistEnsureDir();
    do {
        $id = bin2hex(random_bytes(4));
    } while (file_exists(playlistPath($id)));
    $p = playlistWithLock($id, function () use ($id, $name, $matched) {
        return playlistNew($id, $name, $matched);
    }, true);

    $msg = "Imported <code>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</code>: " . count($matched) . " song" . (count($matched) === 1 ? "" : "s");
    if ($missing) {
        $msg .= ", " . count($missing) . " not found in your library";
    }
    apiResponse("success", $msg . ".", playlistView($p) + ["not_found" => array_slice($missing, 0, 50)]);
}
