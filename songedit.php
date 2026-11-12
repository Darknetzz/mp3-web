<?php
/* ──────────────────────────────────────────────────────────────────────────
 * Edited song info.
 *
 * Two ways to save an edit from the song editor:
 *   - an override, kept by the app in metadata/overrides.json (the file is
 *     not touched; works on read-only folders and is easy to undo), or
 *   - the file's own ID3 tags, written with getID3 (MP3 only).
 *
 * Overrides are applied on read by getSongMeta()/getSongDetails(), so the
 * song table, song info card, media session and lyrics all see them. An
 * override can also pin a MusicBrainz recording (mbid) for metadata.php, and
 * hold a cover image (metadata/covers/), which cover.php prefers over all.
 * ────────────────────────────────────────────────────────────────────────── */

const SONGEDIT_DIR        = __DIR__ . '/metadata';
const SONGEDIT_FILE       = SONGEDIT_DIR . '/overrides.json';
const SONGEDIT_COVER_DIR  = SONGEDIT_DIR . '/covers';
const SONGEDIT_FIELDS     = ['title', 'artist', 'album', 'year', 'genre', 'track', 'comment'];
const SONGEDIT_MAX_LEN    = 300;
const SONGEDIT_COVER_MAX_BYTES = 8388608;
const SONGEDIT_COVER_SIZE = 1000;

/* ─────────────────────────────── Storage ──────────────────────────────── */
/** Every override, keyed by song key. Read once per request. */
function songOverrides(bool $reload = false): array {
    static $all = null;
    if ($all === null || $reload) {
        $raw = is_file(SONGEDIT_FILE) ? json_decode((string) @file_get_contents(SONGEDIT_FILE), true) : null;
        $all = is_array($raw) ? $raw : [];
    }
    return $all;
}

/** The override for one song (by key or path), or []. */
function songOverride(string $file): array {
    $o = songOverrides()[songKeyOf($file)] ?? [];
    return is_array($o) ? $o : [];
}

function songEditEnsureDir(string $dir = SONGEDIT_DIR): void {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        apiResponse("error", "The folder <code>metadata/</code> could not be created. Check that the app folder is writable.");
    }
    if (!is_writable($dir)) {
        apiResponse("error", "The folder <code>metadata/</code> is not writable by the web server.");
    }
}

/** Read-modify-write one song's override under a lock. $fn gets and returns
 *  the override ([] or null removes it). Returns the new override. */
function songUpdateOverride(string $file, callable $fn): array {
    songEditEnsureDir();
    $fh = @fopen(SONGEDIT_FILE, 'c+');
    if (!$fh) {
        apiResponse("error", "Could not save the song info.");
    }
    flock($fh, LOCK_EX);
    $all = json_decode((string) stream_get_contents($fh), true);
    $all = is_array($all) ? $all : [];
    $key = songKeyOf($file);
    $new = $fn(is_array($all[$key] ?? null) ? $all[$key] : []);
    $new = is_array($new) ? array_filter($new, function ($v) { return $v !== '' && $v !== null; }) : [];
    if (count(array_diff(array_keys($new), ['updated'])) === 0) {
        unset($all[$key]);
        $new = [];
    } else {
        $new['updated'] = time();
        $all[$key] = $new;
    }
    ksort($all);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode((object) $all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    songOverrides(true);
    return $new;
}

/** Merge a song's override over its tag values (getSongMeta/getSongDetails). */
function songApplyOverride(string $file, array $meta): array {
    $o = songOverride($file);
    $edited = [];
    foreach (SONGEDIT_FIELDS as $f) {
        if (isset($o[$f]) && $o[$f] !== '' && array_key_exists($f, $meta)) {
            $meta[$f] = (string) $o[$f];
            $edited[] = $f;
        }
    }
    if (array_key_exists('edited', $meta) || $edited) $meta['edited'] = $edited;
    return $meta;
}

/** The override cover of a song (by key or path), or null. */
function songOverrideCoverFile(string $file): ?string {
    $name = songOverride($file)['cover'] ?? '';
    if ($name === '' || basename($name) !== $name) return null;
    $path = SONGEDIT_COVER_DIR . '/' . $name;
    return is_file($path) ? $path : null;
}

/** Latest change to a song's cover: the file, an online cover, an edited one.
 *  Used as cover.php's version (?v=) and ETag. */
function songCoverVersion(string $path): int {
    $v = (int) @filemtime($path);
    foreach ([metaCoverFile($path), songOverrideCoverFile($path)] as $f) {
        if ($f) $v = max($v, (int) filemtime($f));
    }
    return $v;
}

/** Forget a deleted song's override and cover. */
function songForgetOverride(string $file): void {
    if (!songOverride($file)) return;
    $cover = songOverrideCoverFile($file);
    if ($cover) @unlink($cover);
    if (is_dir(SONGEDIT_DIR) && is_writable(SONGEDIT_DIR)) {
        songUpdateOverride($file, function () { return []; });
    }
}

/* ─────────────────────────────── Helpers ──────────────────────────────── */
/** Absolute path of a library song from user input, or an API error. */
function songEditPath($song): string {
    $path = lyricsSongPath($song);
    if ($path === null) apiResponse("error", "Song not found.");
    return $path;
}

/** Clean the editor's fields: trimmed single-line text (comment may have lines). */
function songEditClean(array $in): array {
    $out = [];
    foreach (SONGEDIT_FIELDS as $f) {
        if (!array_key_exists($f, $in)) continue;
        $v = str_replace(["\r\n", "\r"], "\n", (string) $in[$f]);
        $v = $f === 'comment' ? trim(preg_replace('/\n{3,}/', "\n\n", $v)) : trim(preg_replace('/\s+/u', ' ', $v));
        $out[$f] = mb_substr($v, 0, SONGEDIT_MAX_LEN);
    }
    if (isset($out['year']) && $out['year'] !== '' && !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $out['year'])) {
        apiResponse("error", "Year should look like 2009 (or 2009-05-01).");
    }
    if (isset($out['track']) && $out['track'] !== '' && !preg_match('/^\d{1,3}(\/\d{1,3})?$/', $out['track'])) {
        apiResponse("error", "Track should look like 6 or 6/12.");
    }
    return $out;
}

/** A MusicBrainz recording id from an id or a musicbrainz.org URL; '' clears; null = invalid. */
function songEditMbid($in): ?string {
    $in = trim((string) $in);
    if ($in === '') return '';
    if (preg_match('~(?:^|/recording/)([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})(?:[/?#]|$)~i', $in, $m)) {
        return strtolower($m[1]);
    }
    return null;
}

/** Image bytes from an upload, center-cropped and scaled down, as JPEG (or as-is without GD). */
function songEditCoverBytes(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        apiResponse("error", "The image could not be uploaded.");
    }
    if ($file['size'] > SONGEDIT_COVER_MAX_BYTES) {
        apiResponse("error", "That image is too large (max 8 MB).");
    }
    $data = (string) file_get_contents($file['tmp_name']);
    $info = @getimagesizefromstring($data);
    if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) || $info[0] < 1) {
        apiResponse("error", "Choose a JPEG, PNG, GIF or WebP image.");
    }
    $src = function_exists('imagecreatefromstring') ? @imagecreatefromstring($data) : false;
    if ($src === false) return $data;
    [$w, $h] = [$info[0], $info[1]];
    $side = min($w, $h);
    $out  = min(SONGEDIT_COVER_SIZE, $side);
    $dst  = imagecreatetruecolor($out, $out);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency
    imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $out, $out, $side, $side);
    ob_start();
    imagejpeg($dst, null, 88);
    return (string) ob_get_clean();
}

/** Can Save to file work for this song? Returns null if so, else the reason. */
function songWriteBlocker(string $path): ?string {
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'mp3') return 'Only MP3 files can be tagged.';
    // getID3's writer needs getid3.php loaded first (it checks on autoload).
    if (!class_exists('getID3') || !class_exists('getid3_writetags')) return 'The getID3 library is missing (run composer install).';
    if (!is_writable($path)) return 'The web server can’t write to this file.';
    return null;
}

/** Write ID3v2.3 tags into an MP3, keeping the tags this form doesn't cover.
 *  $fields: SONGEDIT_FIELDS values ('' removes a tag). $cover: JPEG/PNG bytes,
 *  '' to remove the cover, null to keep it. Returns error messages ([] = ok). */
function songWriteTags(string $path, array $fields, ?string $cover): array {
    $getID3 = new getID3();
    $getID3->setOption(['encoding' => 'UTF-8']);
    $info = $getID3->analyze($path);
    getid3_lib::CopyTagsToComments($info);

    // Start from what's in the file, so other frames (lyrics, BPM, …) survive.
    $data = [];
    foreach ((array) ($info['tags']['id3v2'] ?? []) as $k => $v) {
        $data[$k] = (array) $v;
    }
    $map = ['title' => 'title', 'artist' => 'artist', 'album' => 'album', 'year' => 'year',
            'genre' => 'genre', 'track' => 'track_number', 'comment' => 'comment'];
    foreach ($map as $f => $tag) {
        if (!array_key_exists($f, $fields)) continue;
        unset($data[$tag]);
        if ($tag === 'track_number') unset($data['track']);
        if ($fields[$f] !== '') $data[$tag] = [$fields[$f]];
    }

    // Embedded pictures: keep, replace or drop
    $pics = [];
    foreach ((array) ($info['id3v2']['APIC'] ?? []) as $pic) {
        if (!empty($pic['data'])) {
            $pics[] = ['data' => $pic['data'], 'picturetypeid' => $pic['picturetypeid'] ?? 3,
                       'description' => $pic['description'] ?? '', 'mime' => $pic['image_mime'] ?? 'image/jpeg'];
        }
    }
    if ($cover !== null) {
        $pics = array_values(array_filter($pics, function ($p) { return (int) $p['picturetypeid'] !== 3; }));
        if ($cover !== '') {
            $mime = (@getimagesizefromstring($cover)['mime'] ?? 'image/jpeg');
            array_unshift($pics, ['data' => $cover, 'picturetypeid' => 3, 'description' => 'Cover', 'mime' => $mime]);
        }
    }
    if ($pics) $data['attached_picture'] = $pics;
    unset($data['picture']);

    $w = new getid3_writetags();
    $w->filename          = $path;
    $w->tagformats        = ['id3v2.3'];
    $w->overwrite_tags    = true;
    $w->remove_other_tags = false;
    $w->tag_encoding      = 'UTF-8';
    $w->tag_data          = $data;
    $ok = $w->WriteTags();
    clearstatcache(true, $path);
    return $ok ? [] : array_merge($w->errors, $w->warnings);
}

/* ───────────────────────────────── API ────────────────────────────────── */
/** Everything the editor needs: the file's own tags, the override, whether the file can be written. */
function songEditInfo($song): array {
    $path = songEditPath($song);
    $tagged = getSongDetails($path, true);
    $o = songOverride($path);
    return [
        'tagged'      => array_intersect_key($tagged, array_flip(SONGEDIT_FIELDS)),
        'override'    => array_intersect_key($o, array_flip(array_merge(SONGEDIT_FIELDS, ['mbid']))),
        'coverEdited' => songOverrideCoverFile($path) !== null,
        'coverSource' => songOverrideCoverFile($path) ? 'edited'
            : (metaHasEmbeddedCover($path) ? 'file' : (metaCoverFile($path) ? 'online' : 'none')),
        'writeBlocker' => songWriteBlocker($path),
    ];
}

/** Save the editor: fields + mbid as an override. Cover is handled by songSetCover. */
function songSaveOverride($song, array $post): void {
    $path   = songEditPath($song);
    $fields = songEditClean($post);
    $tagged = getSongDetails($path, true);
    $mbid   = songEditMbid($post['mbid'] ?? '');
    if ($mbid === null) {
        apiResponse("error", "That doesn’t look like a MusicBrainz recording link.");
    }
    $prev   = songOverride($path);
    $before = $prev['mbid'] ?? '';
    songUpdateOverride($path, function ($o) use ($fields, $tagged, $mbid) {
        foreach ($fields as $f => $v) {
            // Same as the file's own tag: nothing to override.
            $o[$f] = ($v === (string) ($tagged[$f] ?? '')) ? '' : $v;
        }
        $o['mbid'] = $mbid;
        return $o;
    });
    $now = songOverride($path);
    $renamed = ($now['title'] ?? '') !== ($prev['title'] ?? '') || ($now['artist'] ?? '') !== ($prev['artist'] ?? '');
    if ($mbid !== $before || $renamed) metaForgetLookup($path);   // look it up again with the new match/name
    if ($renamed) songForgetLyrics($path);
    apiResponse("success", "Song info saved.", songEditResult($path));
}

/** Write the editor's fields (and cover change) into the file's tags, then drop the override for them. */
function songSaveToFile($song, array $post, array $files): void {
    $path = songEditPath($song);
    $why  = songWriteBlocker($path);
    if ($why) apiResponse("error", $why);
    $fields = songEditClean($post);

    // Cover: a new upload, "remove", the edited (override) cover, or keep the file's.
    $cover = null;
    if (!empty($files['cover']['tmp_name'])) {
        $cover = songEditCoverBytes($files['cover']);
    } elseif (($post['cover'] ?? '') === 'remove') {
        $cover = '';
    } elseif ($f = songOverrideCoverFile($path)) {
        $cover = (string) file_get_contents($f);
    }

    session_write_close();
    $errors = songWriteTags($path, $fields, $cover);
    if ($errors) {
        apiResponse("error", "Couldn’t write the tags: " . htmlspecialchars(implode(' ', array_slice($errors, 0, 2))));
    }
    $mbid = songEditMbid($post['mbid'] ?? '');
    songRemoveOverrideCover($path);
    songUpdateOverride($path, function ($o) use ($mbid) {
        foreach (SONGEDIT_FIELDS as $f) unset($o[$f]);
        unset($o['cover']);
        if ($mbid !== null) $o['mbid'] = $mbid;
        return $o;
    });
    metaForgetCovers($path);
    metaForgetLookup($path);
    apiResponse("success", "Saved to the file.", songEditResult($path));
}

/** Upload or remove the edited cover (an override; the file isn't touched). */
function songSetCover($song, array $files, bool $remove): void {
    $path = songEditPath($song);
    if ($remove) {
        songRemoveOverrideCover($path);
    } else {
        $data = songEditCoverBytes($files['cover'] ?? []);
        songEditEnsureDir(SONGEDIT_COVER_DIR);
        songRemoveOverrideCover($path);
        $name = sha1(songKeyOf($path)) . '-' . substr(sha1($data), 0, 8) . '.jpg';
        if (@file_put_contents(SONGEDIT_COVER_DIR . '/' . $name, $data, LOCK_EX) === false) {
            apiResponse("error", "The image could not be saved.");
        }
        songUpdateOverride($path, function ($o) use ($name) { $o['cover'] = $name; return $o; });
    }
    metaForgetCovers($path);
    apiResponse("success", $remove ? "Cover removed." : "Cover updated.", songEditResult($path));
}

/** The lyrics lookup for a song is stale (its name changed). */
function songForgetLyrics(string $path): void {
    @unlink(LYRICS_CACHE_DIR . '/' . sha1($path) . '.json');
}

function songRemoveOverrideCover(string $path): void {
    $f = songOverrideCoverFile($path);
    if ($f) @unlink($f);
    if (songOverride($path)['cover'] ?? '') {
        songUpdateOverride($path, function ($o) { unset($o['cover']); return $o; });
    }
}

/** Reset: drop the whole override (fields, match and cover). */
function songResetOverride($song): void {
    $path = songEditPath($song);
    songRemoveOverrideCover($path);
    songUpdateOverride($path, function () { return []; });
    metaForgetCovers($path);
    metaForgetLookup($path);
    songForgetLyrics($path);
    apiResponse("success", "Back to the file’s own info.", songEditResult($path));
}

/** What the page needs to refresh a song after an edit. */
function songEditResult(string $path): array {
    $meta = getSongMeta($path);
    return [
        'file'   => songKeyOf($path),
        'title'  => $meta['title'],
        'artist' => $meta['artist'],
        'album'  => $meta['album'],
        'year'   => $meta['year'],
        'cover'  => 'cover.php?file=' . rawurlencode(songKeyOf($path)) . '&size=sm&v=' . songCoverVersion($path),
        'edit'   => songEditInfo(songKeyOf($path)),
    ];
}
