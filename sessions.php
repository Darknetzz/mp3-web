<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                        Listening sessions ("listen together")              */
/* ────────────────────────────────────────────────────────────────────────── */
// One JSON file per session in SESSION_DIR, read-modify-written under flock.
// Members are identified by a random token in $_SESSION['member_id'] (the PHP
// session id is never exposed). $_SESSION['session_code'] is the session you're in.

const SESSION_DIR            = __DIR__ . '/session';
const SESSION_ACTIVE_SECS    = 30;     // member counts as "listening"
const SESSION_MEMBER_TTL     = 3600;   // drop members not seen for this long
const SESSION_HOST_STALE     = 60;     // hand over host after this much host inactivity
const SESSION_FILE_TTL       = 86400;  // delete sessions idle this long
const SESSION_NAME_MAX       = 40;
const SESSION_QUEUE_MAX      = 200;
const SESSION_DISPLAY_MAX    = 30;     // member display names

/* ─────────────────────────────── Helpers ────────────────────────────────── */
function sessionMemberId(): string {
    if (empty($_SESSION['member_id'])) {
        $_SESSION['member_id'] = bin2hex(random_bytes(8));
    }
    return $_SESSION['member_id'];
}

/** This browser's display name in sessions (defaults to "Guest XXXX"). */
function sessionDisplayName(): string {
    $name = $_SESSION['display_name'] ?? '';
    if (!is_string($name) || $name === '') {
        $name = "Guest " . strtoupper(substr(sessionMemberId(), 0, 4));
    }
    return $name;
}

function sessionCleanDisplayName($name): string {
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    return mb_substr($name, 0, SESSION_DISPLAY_MAX);
}

function sessionValidCode($code): bool {
    return is_string($code) && preg_match('/^[a-f0-9]{8}$/', $code) === 1;
}

function sessionPath(string $code): string {
    return SESSION_DIR . '/' . $code . '.json';
}

function sessionNowMs(): int {
    return (int) round(microtime(true) * 1000);
}

function sessionEnsureDir(): void {
    if (!is_dir(SESSION_DIR) && !@mkdir(SESSION_DIR, 0775, true)) {
        apiResponse("error", "The session directory could not be created.");
    }
    if (!is_writable(SESSION_DIR)) {
        apiResponse("error", "The session directory <code>session/</code> is not writable.");
    }
}

/**
 * Open a session file under an exclusive lock, pass the decoded data to $fn,
 * and write back whatever $fn returns (array), or delete the file (false),
 * or leave it untouched (null). Returns [$data, $fnResult].
 */
function sessionWithLock(string $code, callable $fn, bool $create = false) {
    $path = sessionPath($code);
    if (!$create && !is_file($path)) {
        return [null, null];
    }
    $fh = @fopen($path, $create ? 'c+' : 'r+');
    if (!$fh) {
        apiResponse("error", "Could not open the session.");
    }
    flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['members'])) {
        $data = null; // missing or old-format file
    }

    $result = $fn($data);

    if ($result === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        @unlink($path);
        return [null, false];
    }
    if (is_array($result)) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($fh);
        $data = $result;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    return [$data, $result];
}

/** Prune members, hand over a stale host. Returns the session (or false if empty). */
function sessionHousekeep(array $s, ?string $touchMember = null) {
    $now = time();
    if ($touchMember !== null && isset($s['members'][$touchMember])) {
        $s['members'][$touchMember]['last_seen'] = $now;
    }
    foreach ($s['members'] as $id => $m) {
        if ($now - ($m['last_seen'] ?? 0) > SESSION_MEMBER_TTL) {
            unset($s['members'][$id]);
        }
    }
    if (empty($s['members'])) {
        return false;
    }
    $host = $s['host'] ?? null;
    $hostStale = !isset($s['members'][$host]) || ($now - $s['members'][$host]['last_seen'] > SESSION_HOST_STALE);
    if ($hostStale) {
        $next = sessionNextHost($s, $host, true);
        if ($next !== null) {
            $s['host'] = $next;
        }
    }
    return $s;
}

/** Longest-joined member other than $exclude (active ones only if $activeOnly). */
function sessionNextHost(array $s, ?string $exclude, bool $activeOnly): ?string {
    $now = time();
    $best = null;
    foreach ($s['members'] as $id => $m) {
        if ($id === $exclude) continue;
        if ($activeOnly && $now - $m['last_seen'] > SESSION_ACTIVE_SECS) continue;
        if ($best === null || $m['joined'] < $s['members'][$best]['joined']) {
            $best = $id;
        }
    }
    return $best;
}

function sessionActiveCount(array $s): int {
    $now = time();
    $n = 0;
    foreach ($s['members'] as $m) {
        if ($now - $m['last_seen'] <= SESSION_ACTIVE_SECS) $n++;
    }
    return $n;
}

/** Delete idle or old-format session files. */
function sessionCleanup(): void {
    foreach (glob(SESSION_DIR . '/*.json') ?: [] as $file) {
        if (time() - filemtime($file) > SESSION_FILE_TTL) {
            @unlink($file);
        }
    }
}

/** Public view of a session for the current member. */
function sessionView(array $s, string $member): array {
    $canControl = ($s['host'] === $member) || !empty($s['allow_all']);
    $now = time();
    $members = [];
    foreach ($s['members'] as $id => $m) {
        $members[] = [
            "name"   => $m['name'] ?? "Guest",
            "host"   => $id === $s['host'],
            "me"     => $id === $member,
            "active" => $now - ($m['last_seen'] ?? 0) <= SESSION_ACTIVE_SECS,
            "joined" => $m['joined'] ?? 0,
        ];
    }
    // Host first, then by join time. Member ids are never exposed.
    usort($members, function ($a, $b) {
        return [$b['host'], $a['joined']] <=> [$a['host'], $b['joined']];
    });
    foreach ($members as &$m) unset($m['joined']);
    unset($m);
    return [
        "code"        => $s['code'],
        "name"        => $s['name'],
        "public"      => $s['public'],
        "allow_all"   => !empty($s['allow_all']),
        "version"     => $s['version'],
        "state"       => $s['state'],
        "is_host"     => $s['host'] === $member,
        "can_control" => $canControl,
        "listening"   => sessionActiveCount($s),
        "members"     => $members,
        "me"          => sessionDisplayName(),
        "server_ms"   => sessionNowMs(),
    ];
}

function sessionCurrentCode(): string {
    $code = $_SESSION['session_code'] ?? null;
    if (!sessionValidCode($code)) {
        unset($_SESSION['session_code']);
        apiResponse("error", "You are not in a session.", ["in_session" => false]);
    }
    return $code;
}

function sessionCleanName($name): string {
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    $name = mb_substr($name, 0, SESSION_NAME_MAX);
    return $name;
}

/** Sanitize a client-sent playback state. */
function sessionCleanState($state, array $previous): array {
    if (!is_array($state)) {
        apiResponse("error", "Invalid session state.");
    }
    $song = $state['song'] ?? null;
    if ($song !== null && $song !== '') {
        $song = songPath($song) !== null ? (string) $song : null;
    } else {
        $song = null;
    }
    $queue = [];
    foreach (array_slice((array) ($state['queue'] ?? []), 0, SESSION_QUEUE_MAX) as $q) {
        if (is_string($q) && songPath($q) !== null) $queue[] = $q;
    }
    $toBool = function ($v) {
        return $v === true || $v === 'true' || $v === '1' || $v === 1;
    };
    return [
        "song"       => $song,
        "position"   => max(0, (float) ($state['position'] ?? 0)),
        "playing"    => $song !== null && $toBool($state['playing'] ?? false),
        "updated_ms" => sessionNowMs(),
        "queue"      => $queue,
        "loop"       => $toBool($state['loop'] ?? ($previous['loop'] ?? false)),
        "shuffle"    => $toBool($state['shuffle'] ?? ($previous['shuffle'] ?? false)),
    ];
}

/* ──────────────────────────── User preferences ──────────────────────────── */
// Per-user defaults (currently: for new sessions). userPrefs() and userPrefsSave()
// are the only places that know where prefs are stored. Today that is the PHP
// session (mirrored to localStorage by session.js); with a user DB, read and
// write the logged-in user's row here instead and nothing else has to change.

function userPrefsDefaults(): array {
    return [
        "session_allow_all"      => false, // let everyone control playback in new sessions
        "session_public"         => false, // list new sessions publicly
        "session_name"           => "",    // default name for new sessions
        "session_share_playback" => true,  // new sessions start with what you're playing
    ];
}

/** Sanitize prefs against the defaults: unknown keys dropped, types enforced. */
function userPrefsClean($prefs): array {
    $clean = [];
    if (!is_array($prefs)) return $clean;
    foreach (userPrefsDefaults() as $key => $default) {
        if (!array_key_exists($key, $prefs)) continue;
        $v = $prefs[$key];
        if (is_bool($default)) {
            $clean[$key] = ($v === true || $v === 'true' || $v === '1' || $v === 1);
        } else {
            $clean[$key] = sessionCleanName($v);
        }
    }
    return $clean;
}

function userPrefs(): array {
    return array_merge(userPrefsDefaults(), userPrefsClean($_SESSION['user_prefs'] ?? []));
}

function userPrefsSave(array $prefs): array {
    $_SESSION['user_prefs'] = array_merge(userPrefs(), userPrefsClean($prefs));
    return userPrefs();
}

/** API: save some or all prefs (JSON object or array). */
function setUserPrefs($prefs): void {
    if (is_string($prefs)) {
        $prefs = json_decode($prefs, true);
    }
    if (!is_array($prefs)) {
        apiResponse("error", "Invalid preferences.");
    }
    apiResponse("success", "Session defaults saved.", ["prefs" => userPrefsSave($prefs)]);
}

/* ─────────────────────────────── Actions ────────────────────────────────── */
function createSession(string $name = "", bool $public = false, bool $allowAll = false): void {
    sessionEnsureDir();
    sessionCleanup();
    if (!empty($_SESSION['session_code'])) {
        sessionLeaveCurrent();
    }
    $member = sessionMemberId();
    $name   = sessionCleanName($name);

    for ($i = 0; $i < 5; $i++) {
        $code = bin2hex(random_bytes(4));
        if (!file_exists(sessionPath($code))) break;
    }
    if ($name === '') {
        $name = "Session " . strtoupper($code);
    }

    $now = time();
    $displayName = sessionDisplayName();
    [$data] = sessionWithLock($code, function ($existing) use ($code, $name, $public, $allowAll, $member, $now, $displayName) {
        return [
            "code"      => $code,
            "name"      => $name,
            "public"    => $public,
            "created"   => date("c", $now),
            "host"      => $member,
            "allow_all" => $allowAll,
            "version"   => 1,
            "state"     => [
                "song" => null, "position" => 0, "playing" => false,
                "updated_ms" => sessionNowMs(), "queue" => [], "loop" => false, "shuffle" => false,
            ],
            "members"   => [$member => ["name" => $displayName, "joined" => $now, "last_seen" => $now]],
        ];
    }, true);

    $_SESSION['session_code'] = $code;
    apiResponse("success", "Session <code>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</code> created.", sessionView($data, $member));
}

function joinSession($code): void {
    $code = strtolower(trim((string) $code));
    if (!sessionValidCode($code)) {
        apiResponse("error", "That session code is not valid. Codes are 8 characters (0-9, a-f).");
    }
    if (!empty($_SESSION['session_code']) && $_SESSION['session_code'] !== $code) {
        sessionLeaveCurrent();
    }
    $member = sessionMemberId();
    $displayName = sessionDisplayName();
    [$data] = sessionWithLock($code, function ($s) use ($member, $displayName) {
        if ($s === null) return null;
        $now = time();
        if (!isset($s['members'][$member])) {
            $s['members'][$member] = ["joined" => $now, "last_seen" => $now];
        }
        $s['members'][$member]['name'] = $displayName;
        return sessionHousekeep($s, $member);
    });
    if (!is_array($data)) {
        apiResponse("error", "Session not found. It may have ended.");
    }
    $_SESSION['session_code'] = $code;
    apiResponse("success", "Joined <code>" . htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8') . "</code>.", sessionView($data, $member));
}

/** Remove the current member from their session (no response). */
function sessionLeaveCurrent(): void {
    $code   = $_SESSION['session_code'] ?? null;
    $member = sessionMemberId();
    unset($_SESSION['session_code']);
    if (!sessionValidCode($code)) return;
    sessionWithLock($code, function ($s) use ($member) {
        if ($s === null) return false;
        unset($s['members'][$member]);
        if (empty($s['members'])) return false;
        if ($s['host'] === $member) {
            $s['host'] = sessionNextHost($s, null, true) ?? sessionNextHost($s, null, false);
        }
        return $s;
    });
}

function leaveSession(): void {
    sessionLeaveCurrent();
    apiResponse("success", "You left the session.", ["in_session" => false]);
}

function listSessions(): void {
    sessionEnsureDir();
    sessionCleanup();
    $list = [];
    foreach (glob(SESSION_DIR . '/*.json') ?: [] as $file) {
        $s = json_decode((string) @file_get_contents($file), true);
        if (!is_array($s) || empty($s['public']) || !isset($s['members'])) continue;
        $listening = sessionActiveCount($s);
        if ($listening === 0) continue;
        $list[] = [
            "code"      => $s['code'],
            "name"      => $s['name'],
            "listening" => $listening,
            "song"      => $s['state']['song'] ?? null,
        ];
    }
    usort($list, function ($a, $b) { return $b['listening'] <=> $a['listening']; });
    apiResponse("success", count($list) . " public session(s).", ["sessions" => $list]);
}

function sessionState(): void {
    $code   = sessionCurrentCode();
    $member = sessionMemberId();
    session_write_close(); // don't block this browser's other requests while polling
    [$data] = sessionWithLock($code, function ($s) use ($member) {
        if ($s === null) return false;
        if (!isset($s['members'][$member])) return null; // removed (e.g. idle too long)
        return sessionHousekeep($s, $member);
    });
    if (!is_array($data) || !isset($data['members'][$member])) {
        session_start();
        unset($_SESSION['session_code']);
        apiResponse("error", "The session has ended.", ["in_session" => false]);
    }
    apiResponse("success", "ok", sessionView($data, $member));
}

function sessionUpdate($state, $ifVersion = null): void {
    $code   = sessionCurrentCode();
    $member = sessionMemberId();
    session_write_close();
    if (is_string($state)) {
        $state = json_decode($state, true);
    }
    $denied = false;
    $conflict = false;
    [$data] = sessionWithLock($code, function ($s) use ($member, $state, $ifVersion, &$denied, &$conflict) {
        if ($s === null || !isset($s['members'][$member])) return null;
        $s = sessionHousekeep($s, $member);
        if ($s === false) return false;
        if ($s['host'] !== $member && empty($s['allow_all'])) {
            $denied = true;
            return $s;
        }
        if ($ifVersion !== null && $ifVersion !== '' && (int) $ifVersion !== (int) $s['version']) {
            $conflict = true;
            return $s;
        }
        $s['state']   = sessionCleanState($state, $s['state']);
        $s['version'] = (int) $s['version'] + 1;
        return $s;
    });
    if (!is_array($data)) {
        apiResponse("error", "The session has ended.", ["in_session" => false]);
    }
    if ($denied) {
        apiResponse("error", "Only the host can control playback.", sessionView($data, $member));
    }
    if ($conflict) {
        apiResponse("conflict", "Someone else changed the session first.", sessionView($data, $member));
    }
    apiResponse("success", "ok", sessionView($data, $member));
}

function sessionSettings($allowAll): void {
    $code   = sessionCurrentCode();
    $member = sessionMemberId();
    $notHost = false;
    [$data] = sessionWithLock($code, function ($s) use ($member, $allowAll, &$notHost) {
        if ($s === null) return null;
        if ($s['host'] !== $member) {
            $notHost = true;
            return null;
        }
        $s['allow_all'] = ($allowAll === true || $allowAll === 'true' || $allowAll === '1');
        $s['version']   = (int) $s['version'] + 1;
        return sessionHousekeep($s, $member);
    });
    if (!is_array($data)) {
        apiResponse("error", "The session has ended.", ["in_session" => false]);
    }
    if ($notHost) {
        apiResponse("error", "Only the host can change session settings.", sessionView($data, $member));
    }
    $msg = $data['allow_all'] ? "Everyone can now control playback." : "Only the host can control playback now.";
    apiResponse("success", $msg, sessionView($data, $member));
}

/** Set this browser's display name, and update it in the current session. */
function setDisplayName($name): void {
    $name = sessionCleanDisplayName($name);
    if ($name === '') {
        apiResponse("error", "Your name can't be empty.");
    }
    $_SESSION['display_name'] = $name;
    $member = sessionMemberId();
    $code   = $_SESSION['session_code'] ?? null;
    $view   = null;
    if (sessionValidCode($code)) {
        [$data] = sessionWithLock($code, function ($s) use ($member, $name) {
            if ($s === null || !isset($s['members'][$member])) return null;
            $s['members'][$member]['name'] = $name;
            return sessionHousekeep($s, $member);
        });
        if (is_array($data) && isset($data['members'][$member])) {
            $view = sessionView($data, $member);
        }
    }
    apiResponse("success", "You're now <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong>.", ["name" => $name, "session" => $view]);
}
