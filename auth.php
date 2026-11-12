<?php

/* ────────────────────────────────────────────────────────────────────────── */
/*                          Accounts, login and setup                         */
/* ────────────────────────────────────────────────────────────────────────── */
// Accounts live in a SQLite database (data/mp3web.sqlite, or $DATA_DIR). The
// logged-in user's id is $_SESSION['user_id']. With login turned off (setting
// "auth_required" or env AUTH_REQUIRED=false) every request acts as the first
// admin, or as a built-in "local" user (id 0) when no accounts exist yet,
// which behaves like the app did before accounts.

const AUTH_REMEMBER_COOKIE = 'mp3web_remember';
const AUTH_REMEMBER_DAYS   = 30;
const AUTH_THROTTLE_MAX    = 5;    // failed logins per IP ...
const AUTH_THROTTLE_SECS   = 300;  // ... in this many seconds
const AUTH_USERNAME_RE     = '/^[A-Za-z0-9._-]{2,32}$/';
const AUTH_PASSWORD_MIN    = 6;
const AUTH_DISPLAY_MAX     = 30;
const STREAM_TOKEN_TTL     = 604800; // 7 days

/* ──────────────────────────────── Env ───────────────────────────────────── */
/** An environment variable (real env first, then .env), or $default when unset/empty. */
function envValue(string $key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') $v = $_ENV[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

/** true/false for a boolean env variable, null when it isn't set. */
function envBool(string $key): ?bool {
    $v = envValue($key);
    if ($v === null) return null;
    return filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
}

/* ──────────────────────────────── Database ──────────────────────────────── */
function dataDir(): string {
    $dir = rtrim((string) envValue('DATA_DIR', __DIR__ . '/data'), '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
        @chmod($dir, 0775);
    }
    return $dir;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dir = dataDir();
    if (!is_dir($dir) || !is_writable($dir)) {
        authFatal("The data folder <code>" . htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') . "</code> is missing or not writable by the web server.");
    }
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        authFatal("PHP's pdo_sqlite extension is missing.");
    }
    $pdo = new PDO('sqlite:' . $dir . '/mp3web.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    authMigrate($pdo);
    return $pdo;
}

/** Stop with a message: JSON for the API, plain text otherwise. */
function authFatal(string $message): void {
    if (PHP_SAPI !== 'cli' && authIsApi()) {
        apiResponse("error", $message);
    }
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    exit($message . "\n");
}

function authMigrate(PDO $db): void {
    $version = (int) $db->query('PRAGMA user_version')->fetchColumn();
    if ($version < 1) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                username        TEXT NOT NULL UNIQUE COLLATE NOCASE,
                display_name    TEXT NOT NULL DEFAULT '',
                password_hash   TEXT NOT NULL,
                role            TEXT NOT NULL DEFAULT 'user',
                share_playing   INTEGER NOT NULL DEFAULT 1,
                settings        TEXT NOT NULL DEFAULT '{}',
                prefs           TEXT NOT NULL DEFAULT '{}',
                playlist_layout TEXT NOT NULL DEFAULT '{}',
                created         INTEGER NOT NULL,
                last_login      INTEGER
            );
            CREATE TABLE IF NOT EXISTS remember_tokens (
                selector       TEXT PRIMARY KEY,
                validator_hash TEXT NOT NULL,
                user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                expires        INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS login_attempts (
                ip TEXT NOT NULL,
                at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts(ip, at);
            CREATE TABLE IF NOT EXISTS presence (
                user_id      INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
                song         TEXT,
                position     REAL NOT NULL DEFAULT 0,
                playing      INTEGER NOT NULL DEFAULT 0,
                updated_ms   INTEGER NOT NULL DEFAULT 0,
                session_code TEXT
            );
            CREATE TABLE IF NOT EXISTS meta (
                key   TEXT PRIMARY KEY,
                value TEXT
            );
            PRAGMA user_version = 1;
        ");
    }
}

function metaGet(string $key): ?string {
    $st = db()->prepare('SELECT value FROM meta WHERE key = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function metaSet(string $key, ?string $value): void {
    db()->prepare('INSERT INTO meta(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
}

/* ─────────────────────────────── Users ──────────────────────────────────── */
function userRow(int $id): ?array {
    if ($id <= 0) return null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function userByName(string $username): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $row = $st->fetch();
    return $row ?: null;
}

function userCount(): int {
    return (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function userAdminCount(): int {
    return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
}

function userFirstAdminId(): int {
    static $id = null;
    if ($id === null) {
        $id = (int) db()->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
    }
    return $id;
}

/** The name to show for a user. */
function userDisplayName(array $u): string {
    $name = trim((string) ($u['display_name'] ?? ''));
    return $name !== '' ? $name : (string) ($u['username'] ?? 'User');
}

/** id => display name, for every user. */
function userNames(): array {
    static $names = null;
    if ($names === null) {
        $names = [];
        foreach (db()->query('SELECT id, username, display_name FROM users') as $u) {
            $names[(int) $u['id']] = userDisplayName($u);
        }
    }
    return $names;
}

/** What the browser may see about a user. */
function userPublic(array $u): array {
    return [
        "id"            => (int) $u['id'],
        "username"      => (string) $u['username'],
        "display_name"  => userDisplayName($u),
        "role"          => (string) $u['role'],
        "share_playing" => !empty($u['share_playing']),
        "created"       => (int) ($u['created'] ?? 0),
        "last_login"    => isset($u['last_login']) ? (int) $u['last_login'] : null,
    ];
}

function userCleanDisplayName($name): string {
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    return mb_substr($name, 0, AUTH_DISPLAY_MAX);
}

/** An error message for an invalid username/password, or null when fine. */
function userValidateUsername(string $username): ?string {
    if (!preg_match(AUTH_USERNAME_RE, $username)) {
        return "Usernames are 2–32 characters: letters, digits, dot, dash or underscore.";
    }
    return null;
}

function userValidatePassword(string $password): ?string {
    if (mb_strlen($password) < AUTH_PASSWORD_MIN) {
        return "Passwords need at least " . AUTH_PASSWORD_MIN . " characters.";
    }
    if (strlen($password) > 4096) {
        return "That password is too long.";
    }
    return null;
}

/** Create a user. Returns the row, or an error message string. */
function userCreate(string $username, string $password, string $role = 'user', string $displayName = '', bool $sharePlaying = true) {
    $username = trim($username);
    if ($err = userValidateUsername($username)) return $err;
    if ($err = userValidatePassword($password)) return $err;
    if (userByName($username)) return "That username is taken.";
    $role = $role === 'admin' ? 'admin' : 'user';
    $firstUser = userCount() === 0;
    if ($firstUser) $role = 'admin';
    db()->prepare('INSERT INTO users(username, display_name, password_hash, role, share_playing, created) VALUES(?, ?, ?, ?, ?, ?)')
        ->execute([$username, userCleanDisplayName($displayName), password_hash($password, PASSWORD_DEFAULT), $role, $sharePlaying ? 1 : 0, time()]);
    $user = userRow((int) db()->lastInsertId());
    if ($firstUser && $user) {
        authAdoptLegacyData((int) $user['id']);
    }
    return $user;
}

/** Update some columns of a user. */
function userUpdate(int $id, array $fields): void {
    $allowed = ['display_name', 'password_hash', 'role', 'share_playing', 'settings', 'prefs', 'playlist_layout', 'last_login', 'username'];
    $sets = [];
    $vals = [];
    foreach ($fields as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $sets[] = "$col = ?";
        $vals[] = $val;
    }
    if (!$sets) return;
    $vals[] = $id;
    db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    if ($id === currentUserId()) authSetUser(userRow($id));
}

/** A JSON column of the current user (settings, prefs, playlist_layout) as an array. */
function userJson(?array $u, string $col): array {
    $v = $u ? json_decode((string) ($u[$col] ?? '{}'), true) : null;
    return is_array($v) ? $v : [];
}

/**
 * The first account takes over what was shared before accounts existed: the
 * playlists become theirs (private), Favorites becomes their Favorites and
 * the sidebar order becomes theirs.
 */
function authAdoptLegacyData(int $adminId): void {
    if (!defined('PLAYLIST_DIR') || !is_dir(PLAYLIST_DIR)) return;
    foreach (glob(PLAYLIST_DIR . '/*.json') ?: [] as $file) {
        $base = basename($file, '.json');
        if ($base === '_layout') continue;
        $p = json_decode((string) @file_get_contents($file), true);
        if (!is_array($p)) continue;
        if ($base === PLAYLIST_FAVORITES) {
            $dest = playlistFavoritesPath($adminId);
            if (!is_dir(dirname($dest))) {
                @mkdir(dirname($dest), 0775, true);
                @chmod(dirname($dest), 0775);
            }
            if (!is_file($dest)) @rename($file, $dest);
            continue;
        }
        if (!isset($p['owner'])) {
            $p['owner'] = $adminId;
            $p['shared'] = false;
            @file_put_contents($file, json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }
    $layoutFile = PLAYLIST_DIR . '/_layout.json';
    if (is_file($layoutFile)) {
        $layout = json_decode((string) @file_get_contents($layoutFile), true);
        if (is_array($layout)) {
            db()->prepare('UPDATE users SET playlist_layout = ? WHERE id = ?')->execute([json_encode($layout), $adminId]);
        }
    }
}

/* ─────────────────────────── Current user ───────────────────────────────── */
function authSetUser(?array $user): void {
    $GLOBALS['__authUser'] = $user;
    $GLOBALS['__authResolved'] = true;
}

/** Login is required (env AUTH_REQUIRED wins over the setting). */
function authRequired(): bool {
    $env = envBool('AUTH_REQUIRED');
    if ($env !== null) return $env;
    $cfg = defined('GLOBAL_CONFIG') ? GLOBAL_CONFIG : ($GLOBALS['config'] ?? []);
    $v = $cfg['auth_required']['value'] ?? true;
    return $v === true || $v === 'true' || $v === 1 || $v === '1';
}

/** The "local" user that stands in when login is off and nobody has an account. */
function authLocalUser(): array {
    return ['id' => 0, 'username' => 'local', 'display_name' => 'Local', 'role' => 'admin', 'share_playing' => 0,
            'settings' => '{}', 'prefs' => '{}', 'playlist_layout' => '{}', 'created' => 0, 'last_login' => null];
}

/** The signed-in user (or the stand-in when login is off), or null. */
function currentUser(): ?array {
    if (!empty($GLOBALS['__authResolved'])) return $GLOBALS['__authUser'];
    $user = null;
    if (PHP_SAPI === 'cli') {
        authSetUser(null);
        return null;
    }
    if (!empty($_SESSION['user_id'])) {
        $user = userRow((int) $_SESSION['user_id']);
        if (!$user) unset($_SESSION['user_id']);
    }
    if (!$user) {
        $user = authFromRememberCookie();
    }
    if (!$user && !authRequired()) {
        $first = userFirstAdminId();
        $user = $first ? userRow($first) : authLocalUser();
    }
    authSetUser($user);
    return $user;
}

function currentUserId(): int {
    $u = currentUser();
    return $u ? (int) $u['id'] : 0;
}

function isAdmin(): bool {
    if (PHP_SAPI === 'cli') return true;
    $u = currentUser();
    return $u !== null && $u['role'] === 'admin';
}

/** Signed in with a real account (not the login-off stand-in). */
function isSignedIn(): bool {
    return !empty($_SESSION['user_id']) && currentUser() !== null && (int) currentUser()['id'] === (int) $_SESSION['user_id'];
}

function authIsApi(): bool {
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'api.php';
}

/** The app's URL path ("/mp3/"), for cookies and redirects. */
function appBasePath(): string {
    $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    return rtrim($dir, '/') . '/';
}

function authRedirect(string $page): void {
    header('Location: ' . $page);
    exit;
}

/* ──────────────────────────────── Setup ─────────────────────────────────── */
/** The onboarding wizard hasn't been finished and nobody has an account. */
function setupNeeded(): bool {
    if (PHP_SAPI === 'cli') return false;
    if (metaGet('setup_done') === '1') return false;
    return userCount() === 0;
}

/**
 * First boot with ADMIN_USERNAME + ADMIN_PASSWORD set: create that admin and
 * apply MUSIC_FOLDERS / SITE_TITLE, so the wizard is skipped.
 */
function authBootstrapFromEnv(): void {
    if (PHP_SAPI === 'cli') return;
    if (metaGet('setup_done') === '1' || userCount() > 0) return;
    $username = (string) envValue('ADMIN_USERNAME', '');
    $password = (string) envValue('ADMIN_PASSWORD', '');
    if ($username === '' || $password === '') return;
    $user = userCreate($username, $password, 'admin', (string) envValue('ADMIN_DISPLAY_NAME', ''));
    if (!is_array($user)) {
        error_log("mp3-web: could not create the admin from ADMIN_USERNAME/ADMIN_PASSWORD: $user");
        return;
    }
    $changes = [];
    $folders = setupFoldersFromEnv();
    if ($folders) $changes['music_folders'] = $folders;
    $title = envValue('SITE_TITLE');
    if ($title !== null) $changes['site_title'] = (string) $title;
    if ($changes) writeGlobalSettings($changes);
    metaSet('setup_done', '1');
}

/** MUSIC_FOLDERS="/music:/more!" → folders setting ("!" = without subfolders). */
function setupFoldersFromEnv(): array {
    $raw = (string) envValue('MUSIC_FOLDERS', '');
    $folders = [];
    foreach (preg_split('/[:;,]/', $raw) as $path) {
        $path = trim($path);
        if ($path === '') continue;
        $recursive = !str_ends_with($path, '!');
        $folders[] = ['path' => rtrim($path, '!'), 'recursive' => $recursive];
    }
    return $folders;
}

/** Save global setting values to the config file (no API response). */
function writeGlobalSettings(array $values): bool {
    $config = defined('GLOBAL_CONFIG') ? GLOBAL_CONFIG : ($GLOBALS['config'] ?? []);
    foreach ($values as $key => $value) {
        if (!isset($config[$key])) continue;
        $config[$key]['value'] = cleanConfigValue($config[$key], $value);
    }
    return writeConfigFile($config);
}

/* ─────────────────────────────── Guards ─────────────────────────────────── */
/** Pages and API calls: send setup / login when needed. */
function requireLogin(): void {
    if (PHP_SAPI === 'cli') return;
    if (setupNeeded()) {
        if (authIsApi()) {
            apiResponse("error", "Finish setting up the app first.", ["setup" => true]);
        }
        authRedirect('setup.php');
    }
    if (currentUser() !== null) return;
    if (authIsApi()) {
        http_response_code(401);
        apiResponse("error", "Please sign in.", ["auth" => false]);
    }
    $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    authRedirect('login.php' . ($next !== 'index.php' ? '?next=' . rawurlencode($next) : ''));
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        if (authIsApi()) {
            http_response_code(403);
            apiResponse("error", "Only an admin can do that.", ["admin" => false]);
        }
        http_response_code(403);
        exit("Only an admin can do that.");
    }
}

/** stream.php, cover.php, playlist images: a session or a signed media token. */
function requireMediaAccess(): void {
    if (PHP_SAPI === 'cli') return;
    if (!setupNeeded() && currentUser() !== null) return;
    if (streamTokenUser((string) ($_GET['t'] ?? '')) !== null) return;
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('Please sign in.');
}

/* ─────────────────────────── Login / logout ─────────────────────────────── */
function authClientIp(): string {
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function authThrottled(): bool {
    $db = db();
    $db->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - AUTH_THROTTLE_SECS]);
    $st = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $st->execute([authClientIp()]);
    return (int) $st->fetchColumn() >= AUTH_THROTTLE_MAX;
}

/** Sign in (API). */
function authLogin($username, $password, bool $remember): void {
    $username = trim((string) $username);
    $password = (string) $password;
    if (authThrottled()) {
        apiResponse("error", "Too many failed sign-ins. Wait a few minutes and try again.");
    }
    $user = $username !== '' ? userByName($username) : null;
    // Verify against a dummy hash when the user doesn't exist, so timing doesn't reveal it.
    $hash = $user['password_hash'] ?? password_hash(random_bytes(8), PASSWORD_DEFAULT);
    if (!password_verify($password, $hash) || !$user) {
        db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([authClientIp(), time()]);
        apiResponse("error", "Wrong username or password.");
    }
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([authClientIp()]);
    $fields = ['last_login' => time()];
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    authStartSession($user);
    userUpdate((int) $user['id'], $fields);
    if ($remember) authIssueRemember((int) $user['id']);
    apiResponse("success", "Signed in.", ["user" => userPublic($user)]);
}

function authStartSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['display_name']); // the account's name is used from now on
    db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([time(), (int) $user['id']]);
    authSetUser($user);
}

function authCookieOptions(int $expires): array {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ['expires' => $expires, 'path' => appBasePath(), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax'];
}

function authIssueRemember(int $userId): void {
    $selector  = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $expires   = time() + AUTH_REMEMBER_DAYS * 86400;
    db()->prepare('DELETE FROM remember_tokens WHERE expires < ?')->execute([time()]);
    db()->prepare('INSERT INTO remember_tokens(selector, validator_hash, user_id, expires) VALUES(?, ?, ?, ?)')
        ->execute([$selector, hash('sha256', $validator), $userId, $expires]);
    setcookie(AUTH_REMEMBER_COOKIE, $selector . ':' . $validator, authCookieOptions($expires));
}

function authFromRememberCookie(): ?array {
    $raw = (string) ($_COOKIE[AUTH_REMEMBER_COOKIE] ?? '');
    if (!preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/', $raw, $m)) return null;
    $st = db()->prepare('SELECT * FROM remember_tokens WHERE selector = ?');
    $st->execute([$m[1]]);
    $row = $st->fetch();
    if (!$row || $row['expires'] < time() || !hash_equals($row['validator_hash'], hash('sha256', $m[2]))) {
        if (!headers_sent()) setcookie(AUTH_REMEMBER_COOKIE, '', authCookieOptions(time() - 3600));
        return null;
    }
    $user = userRow((int) $row['user_id']);
    if (!$user) return null;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
    }
    return $user;
}

function authLogout(): void {
    $raw = (string) ($_COOKIE[AUTH_REMEMBER_COOKIE] ?? '');
    if (preg_match('/^([a-f0-9]{18}):/', $raw, $m)) {
        db()->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$m[1]]);
    }
    setcookie(AUTH_REMEMBER_COOKIE, '', authCookieOptions(time() - 3600));
    if (function_exists('sessionLeaveCurrent') && !empty($_SESSION['session_code'])) {
        sessionLeaveCurrent();
    }
    if (currentUserId() > 0) {
        db()->prepare('DELETE FROM presence WHERE user_id = ?')->execute([currentUserId()]);
    }
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    authSetUser(null);
    apiResponse("success", "Signed out.", ["login_required" => authRequired()]);
}

/* ──────────────────────────── Media tokens ──────────────────────────────── */
// Cast devices and external players fetch songs without our cookie, so song
// and cover URLs carry a signed token: "<uid>.<expires>.<signature>".

function streamSecret(): string {
    static $secret = null;
    if ($secret === null) {
        $secret = metaGet('stream_secret');
        if ($secret === null) {
            $secret = bin2hex(random_bytes(32));
            metaSet('stream_secret', $secret);
        }
    }
    return $secret;
}

function streamToken(?int $userId = null): string {
    $userId ??= currentUserId();
    $payload = $userId . '.' . (time() + STREAM_TOKEN_TTL);
    return $payload . '.' . substr(hash_hmac('sha256', $payload, streamSecret()), 0, 32);
}

/** The user id a media token was issued to, or null when it's invalid/expired. */
function streamTokenUser(string $token): ?int {
    if (!preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{32})$/', $token, $m)) return null;
    if ((int) $m[2] < time()) return null;
    $expected = substr(hash_hmac('sha256', $m[1] . '.' . $m[2], streamSecret()), 0, 32);
    if (!hash_equals($expected, $m[3])) return null;
    $uid = (int) $m[1];
    if ($uid > 0 && !userRow($uid)) return null;
    return $uid;
}

/* ─────────────────────────── Account actions ────────────────────────────── */
function authRequireRealUser(): array {
    $u = currentUser();
    if (!$u || (int) $u['id'] <= 0) {
        apiResponse("error", "Create an account first (Settings → Users).");
    }
    return $u;
}

/** API: change your own display name / password / sharing. */
function accountUpdate(array $post): void {
    $u = authRequireRealUser();
    $fields = [];
    if (array_key_exists('display_name', $post)) {
        $fields['display_name'] = userCleanDisplayName($post['display_name']);
    }
    if (array_key_exists('share_playing', $post)) {
        $fields['share_playing'] = filter_var($post['share_playing'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if (!$fields['share_playing']) {
            db()->prepare('DELETE FROM presence WHERE user_id = ?')->execute([(int) $u['id']]);
        }
    }
    if (isset($post['new_password']) && $post['new_password'] !== '') {
        if (!password_verify((string) ($post['current_password'] ?? ''), $u['password_hash'])) {
            apiResponse("error", "Your current password is wrong.");
        }
        if ($err = userValidatePassword((string) $post['new_password'])) apiResponse("error", $err);
        $fields['password_hash'] = password_hash((string) $post['new_password'], PASSWORD_DEFAULT);
        // Sign out other devices
        db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([(int) $u['id']]);
    }
    userUpdate((int) $u['id'], $fields);
    $msg = isset($fields['password_hash']) ? "Password changed." : "Saved.";
    apiResponse("success", $msg, ["user" => userPublic(userRow((int) $u['id']))]);
}

/* ───────────────────────────── User admin ───────────────────────────────── */
function usersList(): void {
    requireAdmin();
    $users = [];
    foreach (db()->query('SELECT * FROM users ORDER BY username COLLATE NOCASE') as $u) {
        $users[] = userPublic($u);
    }
    apiResponse("success", count($users) . " user(s).", ["users" => $users, "me" => currentUserId()]);
}

function usersCreate(array $post): void {
    requireAdmin();
    $user = userCreate((string) ($post['username'] ?? ''), (string) ($post['password'] ?? ''), (string) ($post['role'] ?? 'user'), (string) ($post['display_name'] ?? ''));
    if (!is_array($user)) apiResponse("error", $user);
    apiResponse("success", "Created " . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . ".", ["user" => userPublic($user)]);
}

function usersUpdate(array $post): void {
    requireAdmin();
    $id = (int) ($post['id'] ?? 0);
    $u  = userRow($id);
    if (!$u) apiResponse("error", "That user doesn't exist.");
    $fields = [];
    if (isset($post['role'])) {
        $role = $post['role'] === 'admin' ? 'admin' : 'user';
        if ($role === 'user' && $u['role'] === 'admin' && userAdminCount() <= 1) {
            apiResponse("error", "There has to be at least one admin.");
        }
        $fields['role'] = $role;
    }
    if (array_key_exists('display_name', $post)) {
        $fields['display_name'] = userCleanDisplayName($post['display_name']);
    }
    if (isset($post['password']) && $post['password'] !== '') {
        if ($err = userValidatePassword((string) $post['password'])) apiResponse("error", $err);
        $fields['password_hash'] = password_hash((string) $post['password'], PASSWORD_DEFAULT);
        db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$id]);
    }
    userUpdate($id, $fields);
    apiResponse("success", isset($fields['password_hash']) ? "Password reset." : "Saved.", ["user" => userPublic(userRow($id))]);
}

function usersDelete(array $post): void {
    requireAdmin();
    $id = (int) ($post['id'] ?? 0);
    $u  = userRow($id);
    if (!$u) apiResponse("error", "That user doesn't exist.");
    if ($id === currentUserId()) apiResponse("error", "You can't delete your own account.");
    if ($u['role'] === 'admin' && userAdminCount() <= 1) apiResponse("error", "There has to be at least one admin.");
    // Their private playlists go with them; shared ones pass to you so others keep them.
    if (defined('PLAYLIST_DIR')) playlistsOfDeletedUser($id, currentUserId());
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    apiResponse("success", "Deleted " . htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') . ".", ["id" => $id]);
}

/* ────────────────────────────── Setup API ───────────────────────────────── */
function setupRequireOpen(): void {
    if (!setupNeeded()) {
        apiResponse("error", "The app is already set up.");
    }
    $token = (string) envValue('SETUP_TOKEN', '');
    if ($token !== '' && !hash_equals($token, (string) ($_POST['setup_token'] ?? $_GET['setup_token'] ?? ''))) {
        apiResponse("error", "The setup token is wrong.", ["setup_token" => false]);
    }
}

/** Finish the wizard: account, folders and basics in one go. */
function setupFinish(array $post): void {
    setupRequireOpen();
    $loginOff = envBool('AUTH_REQUIRED') === false || (isset($post['auth_required']) && !filter_var($post['auth_required'], FILTER_VALIDATE_BOOLEAN));
    $username = trim((string) ($post['username'] ?? ''));
    $user = null;
    if ($username !== '' || !$loginOff) {
        if ((string) ($post['password'] ?? '') !== (string) ($post['password2'] ?? '')) {
            apiResponse("error", "The passwords don't match.", ["step" => 1]);
        }
        $user = userCreate($username, (string) ($post['password'] ?? ''), 'admin', (string) ($post['display_name'] ?? ''),
            filter_var($post['share_playing'] ?? true, FILTER_VALIDATE_BOOLEAN));
        if (!is_array($user)) apiResponse("error", $user, ["step" => 1]);
    }

    $values = [];
    $folders = $post['music_folders'] ?? null;
    if (is_string($folders)) $folders = json_decode($folders, true);
    if (is_array($folders)) $values['music_folders'] = $folders;
    if (isset($post['site_title']) && trim((string) $post['site_title']) !== '') $values['site_title'] = (string) $post['site_title'];
    if (isset($post['auth_required'])) $values['auth_required'] = filter_var($post['auth_required'], FILTER_VALIDATE_BOOLEAN);
    if ($values && !writeGlobalSettings($values)) {
        apiResponse("error", "Could not write <code>" . htmlspecialchars(CONFIG_FILE, ENT_QUOTES, 'UTF-8') . "</code>. Check that it is writable by the web server.", ["step" => 2]);
    }
    if ($user && isset($post['player_theme'])) {
        $theme = (string) $post['player_theme'];
        if (isset(GLOBAL_CONFIG['player_theme']['options'][$theme])) {
            userUpdate((int) $user['id'], ['settings' => json_encode(['player_theme' => $theme])]);
        }
    }
    metaSet('setup_done', '1');
    if ($user) authStartSession($user);
    apiResponse("success", "All set.", ["fetch_metadata" => filter_var($post['fetch_metadata'] ?? false, FILTER_VALIDATE_BOOLEAN)]);
}
