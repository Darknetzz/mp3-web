<?php
/* ──────────────────────────────────────────────────────────────────────────
 * Streams a song from the library: stream.php?file=<song key>
 * Music folders can be anywhere on the server (not only under the web root),
 * so songs are served from here, with Range support for seeking and casting.
 * ────────────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/_includes.php';
requireMediaAccess(); // a session, or a signed token (?t=) for Cast devices
session_write_close(); // don't hold the session lock for the whole download

const STREAM_TYPES = [
    'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg',
    'wav' => 'audio/wav', 'flac' => 'audio/flac', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
    'webm' => 'audio/webm',
];

function streamFail(int $code): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $code === 416 ? 'Range not satisfiable.' : 'Not found.';
    exit;
}

$path = songPath($_GET['file'] ?? '');
if ($path === null || !is_readable($path)) streamFail(404);

$size  = filesize($path);
$mtime = filemtime($path);
$etag  = '"' . sha1($path . '|' . $size . '|' . $mtime) . '"';
$ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));

header('Content-Type: ' . (STREAM_TYPES[$ext] ?? 'application/octet-stream'));
header('Accept-Ranges: bytes');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
// Chromecast fetches the song itself
header('Access-Control-Allow-Origin: *');

if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}

$start = 0;
$end   = $size - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';
// One range only ("bytes=a-b", "bytes=a-", "bytes=-n"); If-Range must match
if ($range !== '' && (empty($_SERVER['HTTP_IF_RANGE']) || trim($_SERVER['HTTP_IF_RANGE']) === $etag)) {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m) || ($m[1] === '' && $m[2] === '')) {
        header('Content-Range: bytes */' . $size);
        streamFail(416);
    }
    if ($m[1] === '') {                     // last n bytes
        $start = max(0, $size - (int) $m[2]);
    } else {
        $start = (int) $m[1];
        if ($m[2] !== '') $end = min((int) $m[2], $size - 1);
    }
    if ($start > $end || $start >= $size) {
        header('Content-Range: bytes */' . $size);
        streamFail(416);
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;
header('Content-Length: ' . $length);
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;

while (ob_get_level()) ob_end_clean();
$fh = fopen($path, 'rb');
if ($fh === false) streamFail(404);
fseek($fh, $start);
while ($length > 0 && !feof($fh) && !connection_aborted()) {
    $chunk = fread($fh, min(1 << 16, $length));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    flush();
    $length -= strlen($chunk);
}
fclose($fh);
