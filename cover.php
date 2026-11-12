<?php
/**
 * cover.php — embedded cover art (ID3v2 APIC) for a song.
 *
 *   cover.php?file=<song file name>&size=sm|lg[&v=<mtime>]
 *
 * sm = 96px thumbnail (table rows), lg = 512px (playbar, Media Session, Cast).
 * Responds 204 (cacheable) when the file has no cover, so the <img> fails quietly
 * (a 404 would log a console error for every song) and the placeholder shows.
 * Extracted/resized covers are cached in cache/covers/ (git-ignored).
 */
require_once('_includes.php');
requireMediaAccess(); // a session, or a signed token (?t=) for Cast devices
// Covers don't need the session; release its lock so thumbnails load in parallel.
session_write_close();

const COVER_SIZES = ['sm' => 96, 'lg' => 512];

function coverFail(int $code, string $message, bool $cacheable = false): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    if ($cacheable) {
        header('Cache-Control: public, max-age=86400');
    } else {
        header('Cache-Control: no-store');
    }
    exit($message);
}

// No cover: an empty, cacheable 204. The <img> fires "error" and the placeholder shows.
function coverNone(): void {
    http_response_code(204);
    exit;
}

/* ───────────────────────────── Validate input ───────────────────────────── */
$file = (string) ($_GET['file'] ?? '');
$size = array_key_exists($_GET['size'] ?? '', COVER_SIZES) ? $_GET['size'] : 'sm';
if ($file === '') {
    coverFail(400, 'No file given.');
}
$path = songPath($file);
if ($path === null) {
    coverFail(404, 'Not found.');
}

/* ─────────────────────────────── Conditional ────────────────────────────── */
// A cover edited in the app (songedit.php) or found online (metadata.php)
// counts as a change to the song.
$edited = songOverrideCoverFile($path);
$online = metaCoverFile($path);
$mtime  = songCoverVersion($path);
$etag   = '"' . sha1($path . '|' . $mtime . '|' . filesize($path) . '|' . $size . '|' . ($edited ? 'e' : '') . ($online ? 'o' : '')) . '"';
// The page adds &v=<mtime> to the URL, so a versioned URL can be cached for good.
$maxAge = isset($_GET['v']) ? 31536000 : 86400;
header('Cache-Control: public, max-age=' . $maxAge);
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModified  = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
if (($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag)
    || ($ifNoneMatch === '' && $ifModified !== '' && strtotime($ifModified) >= $mtime)) {
    http_response_code(304);
    exit;
}

/* ──────────────────────────────── Disk cache ────────────────────────────── */
$cacheDir  = __DIR__ . '/cache/covers';
$cacheBase = $cacheDir . '/' . sha1($path) . '-' . $size;
$canCache  = is_dir($cacheDir) || @mkdir($cacheDir, 0775, true);

if ($canCache) {
    if (is_file($cacheBase . '.none') && filemtime($cacheBase . '.none') >= $mtime) {
        coverNone();
    }
    foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'] as $ext => $mime) {
        $cached = $cacheBase . '.' . $ext;
        if (is_file($cached) && filemtime($cached) >= $mtime) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($cached));
            readfile($cached);
            exit;
        }
    }
}

/* ───────────────────────────────── Extract ──────────────────────────────── */
$data = $edited ? (string) @file_get_contents($edited) : null;   // an edited cover wins
if (!$data) try {
    $info = new wapmorgan\Mp3Info\Mp3Info($path, true);
    if ($info->hasCover) {
        $data = $info->getCover();
    }
} catch (Throwable $e) {
    $data = null;
}

$mime = $data ? coverMime($data) : null;
if ($mime === null && $online) {
    // No cover in the file: use the one the song info card found online.
    $data = (string) @file_get_contents($online);
    $mime = $data !== '' ? coverMime($data) : null;
}
if ($mime === null) {
    if ($canCache) {
        @touch($cacheBase . '.none');
    }
    coverNone();
}

[$out, $mime] = coverResize($data, $mime, COVER_SIZES[$size]);
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
if ($canCache) {
    @file_put_contents($cacheBase . '.' . $ext, $out, LOCK_EX);
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($out));
echo $out;
exit;

/* ──────────────────────────────── Helpers ───────────────────────────────── */
// Detect the image type from its bytes; the APIC mime field is often wrong.
function coverMime(string $data): ?string {
    if (strncmp($data, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
    if (strncmp($data, "\x89PNG", 4) === 0) return 'image/png';
    if (strncmp($data, 'GIF8', 4) === 0) return 'image/gif';
    if (strncmp($data, 'RIFF', 4) === 0 && substr($data, 8, 4) === 'WEBP') return 'image/webp';
    return null;
}

// Scale the cover down to fit $max px (JPEG). Falls back to the original bytes.
function coverResize(string $data, string $mime, int $max): array {
    if (!function_exists('imagecreatefromstring')) {
        return [$data, $mime];
    }
    $dims = @getimagesizefromstring($data);
    if (!$dims || $dims[0] < 1 || $dims[1] < 1) {
        return [$data, $mime];
    }
    [$w, $h] = $dims;
    if ($w <= $max && $h <= $max && $mime === 'image/jpeg') {
        return [$data, $mime];
    }
    $src = @imagecreatefromstring($data);
    if ($src === false) {
        return [$data, $mime];
    }
    $scale = min(1, $max / max($w, $h));
    $nw    = max(1, (int) round($w * $scale));
    $nh    = max(1, (int) round($h * $scale));
    $dst   = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // flatten transparency
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start();
    imagejpeg($dst, null, 85);
    $out = (string) ob_get_clean();
    return $out !== '' ? [$out, 'image/jpeg'] : [$data, $mime];
}
