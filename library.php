<?php
/* ──────────────────────────────────────────────────────────────────────────
 * The music library: every song in the configured music folders.
 *
 * A song is identified by its key: its path relative to its music folder,
 * with "/" separators ("Artist/Album/song.mp3"). Songs at the top of a folder
 * keep their plain file name as the key, so playlists, favorites, edits and
 * sessions saved before subfolders were supported keep working.
 * When two folders hold the same relative path, the first folder wins.
 *
 * Every endpoint resolves a key with songPath(): only keys in the library
 * resolve, so a key can never point outside the music folders.
 * ────────────────────────────────────────────────────────────────────────── */

const AUDIO_WHITELIST   = ['mp3', 'ogg', 'oga', 'wav', 'flac', 'm4a', 'aac', 'opus', 'webm'];
const LIBRARY_MAX_DEPTH = 20;
const LIBRARY_SKIP_DIRS = ['@eaDir', '#recycle', 'lost+found'];

/** Lowercase extensions the library lists: allowed_types limited to audio files. */
function audioTypes(): array {
    static $types = null;
    if ($types === null) {
        $types = array_values(array_intersect(array_map('strtolower', (array) getConfig('allowed_types')), AUDIO_WHITELIST));
    }
    return $types;
}

function isAudioFile(string $name): bool {
    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), audioTypes(), true);
}

/** Absolute path for a folder as written in the settings (relative = to the app). */
function musicFolderReal(string $path): ?string {
    $path = trim($path);
    if ($path === '') return null;
    $abs  = ($path[0] === '/' || preg_match('#^[a-z]:[\\\\/]#i', $path)) ? $path : __DIR__ . '/' . $path;
    $real = realpath($abs);
    return ($real !== false && is_dir($real)) ? $real : null;
}

/** The configured folders: [{path, real (null when missing), recursive}]. */
function musicFolders(): array {
    $out = [];
    foreach ((array) getConfig('music_folders') as $f) {
        if (is_string($f)) $f = ['path' => $f];
        if (!is_array($f) || trim((string) ($f['path'] ?? '')) === '') continue;
        $out[] = [
            'path'      => trim((string) $f['path']),
            'real'      => musicFolderReal((string) $f['path']),
            'recursive' => !array_key_exists('recursive', $f) || !empty($f['recursive']),
        ];
    }
    return $out;
}

/** The folder uploads go to: the first one that exists, or null. */
function uploadFolder(): ?array {
    foreach (musicFolders() as $f) if ($f['real'] !== null) return $f;
    return null;
}

/** Keys of the songs in one folder: key => absolute path. */
function scanMusicFolder(string $real, bool $recursive): array {
    $out  = [];
    $seen = [$real => true]; // symlinked folders that loop back
    $walk = function (string $dir, string $prefix, int $depth) use (&$walk, &$out, &$seen, $recursive) {
        $names = @scandir($dir);
        if ($names === false) return;
        $subdirs = [];
        foreach ($names as $name) {
            if ($name === '' || $name[0] === '.') continue; // ., .., hidden
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                if ($recursive && $depth < LIBRARY_MAX_DEPTH && !in_array($name, LIBRARY_SKIP_DIRS, true)) $subdirs[] = $name;
            } elseif (isAudioFile($name) && is_file($path)) {
                $out[$prefix . $name] = $path;
            }
        }
        foreach ($subdirs as $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            $real = realpath($path);
            if ($real === false || isset($seen[$real])) continue;
            $seen[$real] = true;
            $walk($path, $prefix . $name . '/', $depth + 1);
        }
    };
    $walk($real, '', 0);
    return $out;
}

/** Every song: key => absolute path, in folder order. */
function musicLibrary(): array {
    static $library = null;
    if ($library !== null) return $library;
    $library = [];
    foreach (musicFolders() as $f) {
        if ($f['real'] === null) continue;
        $library += scanMusicFolder($f['real'], $f['recursive']); // first folder wins
    }
    return $library;
}

/** Absolute path of a song, or null when the key is not in the library. */
function songPath($key): ?string {
    $key = (string) $key;
    return $key === '' ? null : (musicLibrary()[$key] ?? null);
}

/** Key of a song from its absolute path, or null. */
function songKey(string $path): ?string {
    static $byPath = null;
    if ($byPath === null) $byPath = array_flip(musicLibrary());
    if (isset($byPath[$path])) return (string) $byPath[$path];
    $real = realpath($path);
    return ($real !== false && isset($byPath[$real])) ? (string) $byPath[$real] : null;
}

/** Song key from a key or an absolute path; for a file that is gone, its name. */
function songKeyOf(string $keyOrPath): string {
    if (songPath($keyOrPath) !== null) return $keyOrPath;
    return songKey($keyOrPath) ?? basename(str_replace('\\', '/', $keyOrPath));
}

/** URL path that streams a song, relative to the app. */
function songUrl(string $key): string {
    return 'stream.php?file=' . rawurlencode($key);
}

/** For the settings list: song count and duplicates hidden by earlier folders. */
function musicFolderStats(): array {
    $stats = [];
    $taken = [];
    foreach (musicFolders() as $i => $f) {
        $songs = $f['real'] === null ? [] : scanMusicFolder($f['real'], $f['recursive']);
        $dupes = count(array_intersect_key($songs, $taken));
        $taken += $songs;
        $stats[$i] = ['exists' => $f['real'] !== null, 'songs' => count($songs) - $dupes, 'hidden' => $dupes];
    }
    return $stats;
}
