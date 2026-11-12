<?php
/* ──────────────────────────────────────────────────────────────────────────
 * Lyrics from LRCLIB (https://lrclib.net): free, open, no API key.
 *
 * api.php?action=lyrics&song=<file name>
 *
 * The track is identified from its ID3 tags, falling back to the file name
 * ("06_bad_spit_-_dritfrækk.mp3" → artist "bad spit", title "dritfrækk").
 * Synced (LRC) lyrics are preferred over plain ones. Every answer, including
 * "not found", is cached in cache/lyrics/ so a track is only looked up once.
 * Network errors and rate limiting are not cached, so the next request tries again.
 * ────────────────────────────────────────────────────────────────────────── */

const LYRICS_API          = 'https://lrclib.net/api';
const LYRICS_CACHE_DIR    = __DIR__ . '/cache/lyrics';
const LYRICS_CACHE_FORMAT = 4;            // bump to invalidate old cache entries
const LYRICS_RETRY_AFTER  = 30 * 86400;   // look a "not found" up again after 30 days
const LYRICS_MAX_DIFF     = 3;            // seconds a search result's duration may differ

/** Absolute path of a song in the library, or null. */
function lyricsSongPath($song): ?string {
    $file = basename((string) $song);
    if ($file === '' || $file === '.' || $file === '..') return null;
    $audioDir = realpath((string) getConfig('audio_path'));
    if ($audioDir === false) return null;
    $path = realpath($audioDir . DIRECTORY_SEPARATOR . $file);
    if ($path === false || strpos($path, $audioDir . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) return null;
    $allowed = array_map('strtolower', (array) getConfig('allowed_types'));
    if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $allowed, true)) return null;
    return $path;
}

/** "Dritfrækk (Produced By Bad Spit)" → "Dritfrækk"; drops (prod…), [..], feat… */
function lyricsCleanTitle(string $title): string {
    $t = preg_replace('/\s*[\(\[][^\)\]]*[\)\]]/u', '', $title);
    $t = preg_replace('/\s+(?:ft\.?|feat\.?|featuring)\s+.*$/iu', '', $t);
    $t = preg_replace('/\.(mp3|m4a|ogg|flac|wav)$/iu', '', $t);
    return trim(preg_replace('/\s+/u', ' ', $t), " \t-–—_.");
}

/** The first credited artist: "A, B & C ft. D" → "A". */
function lyricsFirstArtist(string $artist): string {
    $parts = preg_split('/\s*(?:,|&|\bx\b|\band\b|\bog\b|\bft\.?|\bfeat\.?|\bfeaturing\b|\bvs\.?)\s*/iu', $artist);
    return trim($parts[0] ?? $artist);
}

/** Lower-case letters and digits only, for loose comparisons. */
function lyricsNorm(string $s): string {
    return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s));
}

/** Artist/title guesses for a song, best first: [[artist, title], …] (artist may be "").
 *  $haystack gets everything known about the song (file name, tags), normalized. */
function lyricsCandidates(string $path, ?float &$duration, ?string &$haystack = null): array {
    $tags = [];
    $duration = null;
    try {
        $info = new wapmorgan\Mp3Info\Mp3Info($path, true);
        $tags = $info->tags ?? [];
        $duration = $info->duration ?: null;
    } catch (Throwable $e) {
    }

    $out = [];
    $add = function ($artist, $title) use (&$out) {
        $artist = trim((string) $artist);
        $title  = trim((string) $title);
        if ($title === '') return;
        $key = mb_strtolower($artist . '|' . $title);
        if (!isset($out[$key])) $out[$key] = [$artist, $title];
    };

    $tagArtist = trim((string) ($tags['artist'] ?? ''));
    $tagTitle  = trim((string) ($tags['song'] ?? ''));

    // Edited in the app (songedit.php): the best guess there is.
    $edit = function_exists('songOverride') ? songOverride($path) : [];
    if (($edit['title'] ?? '') !== '' || ($edit['artist'] ?? '') !== '') {
        $editArtist = (string) ($edit['artist'] ?? $tagArtist);
        $editTitle  = (string) ($edit['title'] ?? $tagTitle);
        $add($editArtist, $editTitle);
        $add($editArtist, lyricsCleanTitle($editTitle));
        $add(lyricsFirstArtist($editArtist), lyricsCleanTitle($editTitle));
        $tagArtist .= ' ' . $editArtist;   // for the haystack below
        $tagTitle  .= ' ' . $editTitle;
    }
    if ($tagTitle !== '') {
        $add($tagArtist, $tagTitle);
        $add($tagArtist, lyricsCleanTitle($tagTitle));
        $add(lyricsFirstArtist($tagArtist), lyricsCleanTitle($tagTitle));
    }

    // File name: drop the extension and a leading track number, "_" → " ".
    $name = pathinfo($path, PATHINFO_FILENAME);
    $name = str_replace('_', ' ', $name);
    $name = preg_replace('/^\s*\d{1,3}\s*[\.\-)]?\s+/u', '', $name);
    $name = trim(preg_replace('/\s+/u', ' ', $name));
    if (preg_match('/^(.+?)\s+[-–—]\s+(.+)$/u', $name, $m)) {
        $add($m[1], lyricsCleanTitle($m[2]));
        $add(lyricsFirstArtist($m[1]), lyricsCleanTitle($m[2]));
    } elseif (preg_match('/^(.+?)-(.+)$/u', $name, $m)) {   // "BassHunter-Tetris"
        $add(trim($m[1]), lyricsCleanTitle($m[2]));
    } else {
        $add('', lyricsCleanTitle($name));                  // just a title
    }

    $haystack = lyricsNorm(basename($path) . ' ' . $tagArtist . ' ' . $tagTitle);
    return array_values($out);
}

/** GET a LRCLIB endpoint. Returns [httpCode, decoded JSON or null]; code 0 = network error. */
function lyricsFetch(string $endpoint, array $query): array {
    $url = LYRICS_API . $endpoint . '?' . http_build_query($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'mp3-web (self-hosted music player)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = $body === false ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = is_string($body) ? json_decode($body, true) : null;
    return [$code, $json];
}

/** Parse LRC text into [[seconds, line], …] sorted by time. */
function lyricsParseLrc(string $lrc): array {
    $lines = [];
    foreach (preg_split('/\r\n|\r|\n/', $lrc) as $raw) {
        if (!preg_match_all('/\[(\d{1,3}):(\d{1,2}(?:[\.:]\d{1,3})?)\]/', $raw, $stamps, PREG_SET_ORDER)) continue;
        $text = trim(preg_replace('/\[[^\]]*\]/', '', $raw));
        foreach ($stamps as $s) {
            $lines[] = [round((int) $s[1] * 60 + (float) str_replace(':', '.', $s[2]), 2), $text];
        }
    }
    usort($lines, function ($a, $b) { return $a[0] <=> $b[0]; });
    return $lines;
}

/** The record to return/cache for a LRCLIB track (or a "not found"). */
function lyricsRecord(?array $track, array $tried): array {
    if (!$track) {
        return ['v' => LYRICS_CACHE_FORMAT, 'found' => false, 'tried' => $tried, 'checked' => time()];
    }
    $synced = trim((string) ($track['syncedLyrics'] ?? ''));
    $plain  = trim((string) ($track['plainLyrics'] ?? ''));
    return [
        'v'            => LYRICS_CACHE_FORMAT,
        'found'        => true,
        'instrumental' => !empty($track['instrumental']),
        'synced'       => $synced !== '' ? lyricsParseLrc($synced) : null,
        'plain'        => $plain !== '' ? $plain : null,
        'match'        => [
            'artist'   => (string) ($track['artistName'] ?? ''),
            'title'    => (string) ($track['trackName'] ?? ''),
            'album'    => (string) ($track['albumName'] ?? ''),
            'duration' => $track['duration'] ?? null,
            'id'       => $track['id'] ?? null,
        ],
        'checked'      => time(),
    ];
}

/** Does a LRCLIB result plausibly belong to this song? Its title must match
 *  ours exactly, or loosely when one of its artists appears in the file name
 *  or tags. (A title-only search otherwise turns up unrelated songs.) */
function lyricsResultMatches(array $r, string $title, string $haystack): bool {
    $want = lyricsNorm(lyricsCleanTitle($title));
    $got  = lyricsNorm(lyricsCleanTitle((string) ($r['trackName'] ?? '')));
    if ($want === '' || $got === '') return false;
    if ($want === $got) return true;

    $artistOk = false;
    foreach (preg_split('/\s*(?:,|&|\bx\b|\band\b|\bog\b|\bft\.?|\bfeat\.?|\bfeaturing\b|\bvs\.?)\s*/iu', (string) ($r['artistName'] ?? '')) as $a) {
        $a = lyricsNorm($a);
        if ($a === $want) return false; // our "title" is just the artist's name
        if (mb_strlen($a) >= 3 && mb_strpos($haystack, $a) !== false) { $artistOk = true; }
    }
    if (!$artistOk) return false;
    return mb_strlen($want) >= 4 && mb_strlen($got) >= 4
        && (mb_strpos($want, $got) !== false || mb_strpos($got, $want) !== false);
}

/** Pick the best search result: a matching song, duration within LYRICS_MAX_DIFF,
 *  synced lyrics first. */
function lyricsPickResult(array $results, ?float $duration, string $title, string $haystack): ?array {
    $best = null;
    $bestScore = null;
    foreach ($results as $r) {
        if (!is_array($r)) continue;
        if (!lyricsResultMatches($r, $title, $haystack)) continue;
        $hasSynced = !empty($r['syncedLyrics']);
        $hasAny    = $hasSynced || !empty($r['plainLyrics']) || !empty($r['instrumental']);
        if (!$hasAny) continue;
        $diff = ($duration && isset($r['duration'])) ? abs((float) $r['duration'] - $duration) : null;
        if ($duration && ($diff === null || $diff > LYRICS_MAX_DIFF)) continue;
        $score = ($hasSynced ? 0 : 10) + ($diff ?? 5);
        if ($bestScore === null || $score < $bestScore) {
            $best = $r;
            $bestScore = $score;
        }
    }
    return $best;
}

/** Look a song up on LRCLIB. Returns the record, or null on a network error. */
function lyricsLookup(string $path): ?array {
    $duration = null;
    $haystack = '';
    $candidates = lyricsCandidates($path, $duration, $haystack);
    $tried = [];
    $networkError = false;

    // 1. Exact lookups (artist + title + duration), best guesses first.
    if ($duration) {
        foreach ($candidates as [$artist, $title]) {
            if ($artist === '') continue;
            $tried[] = $artist . ' – ' . $title;
            [$code, $json] = lyricsFetch('/get', [
                'artist_name' => $artist,
                'track_name'  => $title,
                'duration'    => (int) round($duration),
            ]);
            if ($code !== 200 && $code !== 404) { $networkError = true; break; } // offline, rate-limited (429), server error
            if ($code === 200 && is_array($json)) {
                $hit = lyricsPickResult([$json], $duration, $title, $haystack);
                if ($hit) return lyricsRecord($hit, $tried);
            }
        }
    }

    // 2. Searches; a result must match the song's duration to be used.
    $searches = [];
    foreach ($candidates as [$artist, $title]) {
        $searches[] = [$title, $artist !== '' ? ['track_name' => $title, 'artist_name' => $artist] : ['q' => $title]];
    }
    foreach (array_slice($searches, 0, 4) as [$title, $query]) {
        [$code, $json] = lyricsFetch('/search', $query);
        if ($code !== 200 && $code !== 404) { $networkError = true; break; } // offline, rate-limited (429), server error
        if ($code === 200 && is_array($json)) {
            $hit = lyricsPickResult($json, $duration, $title, $haystack);
            if ($hit) return lyricsRecord($hit, $tried);
        }
    }

    if ($networkError) return null;
    return lyricsRecord(null, array_values(array_unique(array_map(function ($c) {
        return ($c[0] !== '' ? $c[0] . ' – ' : '') . $c[1];
    }, $candidates))));
}

/** API: lyrics for a song, from the cache or LRCLIB. */
function getLyrics($song): void {
    $path = lyricsSongPath($song);
    if ($path === null) {
        apiResponse("error", "Song not found.");
    }

    $cacheFile = LYRICS_CACHE_DIR . '/' . sha1($path) . '.json';
    $canCache  = is_dir(LYRICS_CACHE_DIR) || @mkdir(LYRICS_CACHE_DIR, 0775, true);
    if ($canCache && is_file($cacheFile)) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        $fresh  = is_array($cached)
            && ($cached['v'] ?? 0) === LYRICS_CACHE_FORMAT
            && @filemtime($cacheFile) >= @filemtime($path)
            && (!empty($cached['found']) || time() - (int) ($cached['checked'] ?? 0) < LYRICS_RETRY_AFTER);
        if ($fresh) {
            $cached['cached'] = true;
            apiResponse("success", "ok", $cached);
        }
    }

    // Looking up can take a few seconds; don't hold the PHP session lock meanwhile.
    session_write_close();
    $record = lyricsLookup($path);
    if ($record === null) {
        apiResponse("error", "Couldn't reach LRCLIB. Try again later.");
    }
    if ($canCache) {
        @file_put_contents($cacheFile, json_encode($record, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    $record['cached'] = false;
    apiResponse("success", "ok", $record);
}
