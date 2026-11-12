<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                     Activity: what other users are playing                 */
/* ────────────────────────────────────────────────────────────────────────── */
// Each signed-in user's player reports its song now and then (activity.js →
// nowPlaying). Users who turned on "Show others what I'm playing" appear in
// everyone else's Activity panel for ACTIVITY_FRESH_MS after their last report.

const ACTIVITY_FRESH_MS = 90000;

/** API: report this user's player state. */
function nowPlaying($song, $position, $playing): void {
    $u = currentUser();
    if (!$u || (int) $u['id'] <= 0 || empty($u['share_playing'])) {
        apiResponse("success", "Not shared.", ["shared" => false]);
    }
    $song = is_string($song) && $song !== '' && songPath($song) !== null ? $song : null;
    $playing = $song !== null && filter_var($playing, FILTER_VALIDATE_BOOLEAN);
    $code = $_SESSION['session_code'] ?? null;
    $code = is_string($code) && sessionValidCode($code) ? $code : null;
    $now  = (int) round(microtime(true) * 1000);
    if ($song === null) {
        db()->prepare('DELETE FROM presence WHERE user_id = ?')->execute([(int) $u['id']]);
    } else {
        db()->prepare('INSERT INTO presence(user_id, song, position, playing, updated_ms, session_code) VALUES(?, ?, ?, ?, ?, ?)
                       ON CONFLICT(user_id) DO UPDATE SET song = excluded.song, position = excluded.position,
                       playing = excluded.playing, updated_ms = excluded.updated_ms, session_code = excluded.session_code')
            ->execute([(int) $u['id'], $song, max(0, (float) $position), $playing ? 1 : 0, $now, $code]);
    }
    apiResponse("success", "ok", ["shared" => true]);
}

/** API: what other users are playing right now. */
function activityList(): void {
    $me  = currentUserId();
    $now = (int) round(microtime(true) * 1000);
    session_write_close();
    $st = db()->prepare('SELECT p.*, u.username, u.display_name FROM presence p JOIN users u ON u.id = p.user_id
                         WHERE u.share_playing = 1 AND p.user_id != ? AND p.updated_ms >= ? ORDER BY p.playing DESC, p.updated_ms DESC');
    $st->execute([$me, $now - ACTIVITY_FRESH_MS]);
    $library = musicLibrary();
    $list = [];
    foreach ($st->fetchAll() as $row) {
        $song = (string) $row['song'];
        if (!isset($library[$song])) continue;
        $meta = getSongMeta($library[$song]);
        // Where they are now, if still playing
        $position = (float) $row['position'];
        if ($row['playing']) $position += ($now - (int) $row['updated_ms']) / 1000;
        $sessionOpen = false;
        if (!empty($row['session_code']) && sessionValidCode($row['session_code'])) {
            $sessionOpen = is_file(sessionPath($row['session_code']));
        }
        $list[] = [
            "user"     => userDisplayName($row),
            "song"     => $song,
            "title"    => (string) ($meta['title'] ?? '') ?: pathinfo($song, PATHINFO_FILENAME),
            "artist"   => (string) ($meta['artist'] ?? ''),
            "position" => round($position, 1),
            "playing"  => (bool) $row['playing'],
            "session"  => $sessionOpen ? $row['session_code'] : null,
            "ago"      => (int) round(($now - (int) $row['updated_ms']) / 1000),
        ];
    }
    apiResponse("success", count($list) . " listening.", ["activity" => $list]);
}
