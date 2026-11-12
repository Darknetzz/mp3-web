<?php
require_once('_includes.php');

# The API should always return JSON, no matter what.
# You want to return something else? You're in the wrong place.
header('Content-Type: application/json');

/**
 * Require a valid CSRF token for state-changing POST requests.
 */
function require_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiResponse("error", "Invalid request method for this action.");
    }

    if (
        empty($_SESSION['csrf_token']) ||
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
    ) {
        apiResponse("error", "Invalid CSRF token.");
    }
}

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

/* ───────────────────────── Before sign-in: login and setup ─────────────────── */
if ($action === 'login') {
    require_csrf_token();
    authLogin($_POST['username'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']));
}
if ($action === 'logout') {
    require_csrf_token();
    authLogout();
}
if ($action === 'setupFinish') {
    require_csrf_token();
    setupFinish($_POST);
}
$inSetup = $action === 'browseDirs' && setupNeeded();
if ($inSetup) {
    setupRequireOpen(); // the wizard's folder picker
} else {
    requireLogin();
}

// Actions only admins may use (the rest check per item, e.g. playlists).
const ADMIN_ACTIONS = ['rm', 'upload', 'browseDirs', 'songEdit', 'songEditReset', 'songCover', 'songWriteTags',
                       'metaJobStart', 'metaJobStop', 'usersList', 'usersCreate', 'usersUpdate', 'usersDelete'];
if (!$inSetup && ((!empty($_FILES) && empty($_POST['action'])) || in_array($action, ADMIN_ACTIONS, true))) {
    requireAdmin();
}

do {

    // Demo mode rejects every action, read-only ones (ls, lyrics, playlists, sessions) included.
    if (getConfig("env") === "demo") {
        $res = apiResponse("error", "File actions have been disabled in this demo.");
        break;
    }

    // Music uploads (Dropzone) send files without an action; other file posts name their action.
    if (!empty($_FILES) && empty($_POST['action'])) {
        $action = "upload";
    } elseif (array_key_exists("action", $_GET)) {
        $action = $_GET["action"];
    } elseif (array_key_exists("action", $_POST)) {
        $action = $_POST["action"];
    } else {
        $res = apiResponse("error", "Action was not found in GET or POST.");
        break;
    }

    // NOTE: download() is commented out in functions.php, so this action fails.
    // The UI downloads songs by opening the file URL directly instead.
    if ($action === "dl") {
        $res = download($_GET["file"]);
        break;
    }

    if ($action === "rm") {
        require_csrf_token();
        $res = remove($_POST["file"]);
        break;
    }

    if ($action === "ls") {
        $res = listSongs();
        break;
    }

    if ($action === 'getconfig') {
        if (isset($_GET['key'])) {
            $value = getConfig((string) $_GET['key']);
            // "response" keeps the old string form; "data.value" has the real type.
            $text  = is_bool($value) ? ($value ? 'true' : 'false') : (is_scalar($value) ? (string) $value : json_encode($value));
            apiResponse("success", $text, ["value" => $value]);
        }
        $visible = getConfig();
        if (!isAdmin()) {
            // Global settings (keys, folders) are for admins; site title is public anyway.
            $visible = array_filter($visible, function ($s, $k) {
                return $k === 'site_title' || (is_array($s) && configIsUserScope($s));
            }, ARRAY_FILTER_USE_BOTH);
        }
        apiResponse("success", "ok", ["config" => $visible]);
    }

    # REVIEW:
    if ($action === 'setconfig') {
        require_csrf_token();
        $postConfig = $_POST['config'] ?? [];
        if (!isset($postConfig['key'])) {
            apiResponse("error", "No setting key was sent.");
        }
        // An emptied array is omitted by jQuery's serializer, so a missing value means "empty".
        $res = setConfig((string) $postConfig['key'], $postConfig['value'] ?? '');
        break;
    }

    if ($action == 'upload') {
        require_csrf_token();
        $results = [];
        $hasError = false;
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($_FILES["files"]["name"] as $key => $name) {
            $file = [
                "name"     => $_FILES["files"]["name"][$key],
                "type"     => $_FILES["files"]["type"][$key],
                "tmp_name" => $_FILES["files"]["tmp_name"][$key],
                "error"    => $_FILES["files"]["error"][$key],
                "size"     => $_FILES["files"]["size"][$key]
            ];
            $fileResult = uploadFile($file, true); // Return result instead of calling apiResponse()
            $results[] = $fileResult;
            
            if ($fileResult["status"] === "error") {
                $hasError = true;
                $errorCount++;
            } else {
                $successCount++;
            }
        }
        
        // Build combined response message
        $totalFiles = count($results);
        if ($hasError) {
            $message = "Upload completed with errors: {$successCount} succeeded, {$errorCount} failed.";
            $status = "error";
        } else {
            $message = "All {$totalFiles} file(s) uploaded successfully. <a href='' class='btn btn-primary'>Refresh</a>";
            $status = "success";
        }
        
        apiResponse(
            $status,
            $message,
            ["results" => $results, "total" => $totalFiles, "succeeded" => $successCount, "failed" => $errorCount]
        );
        break;
    }

    /* ─────────────────────────────── Playlists ──────────────────────────── */
    if ($action === 'listPlaylists') {
        listPlaylists();
    }
    if ($action === 'getPlaylist') {
        getPlaylist($_GET['id'] ?? '');
    }
    if ($action === 'exportPlaylist') {
        exportPlaylist($_GET['id'] ?? '');
    }
    if ($action === 'createPlaylist') {
        require_csrf_token();
        createPlaylist($_POST['name'] ?? '', $_POST['songs'] ?? [], $_POST['description'] ?? '');
    }
    if ($action === 'renamePlaylist') {
        require_csrf_token();
        renamePlaylist($_POST['id'] ?? '', $_POST['name'] ?? '');
    }
    if ($action === 'deletePlaylist') {
        require_csrf_token();
        deletePlaylist($_POST['id'] ?? '');
    }
    if ($action === 'addToPlaylist') {
        require_csrf_token();
        addToPlaylist($_POST['id'] ?? '', $_POST['songs'] ?? []);
    }
    if ($action === 'removeFromPlaylist') {
        require_csrf_token();
        removeFromPlaylist($_POST['id'] ?? '', $_POST['song'] ?? '');
    }
    if ($action === 'reorderPlaylist') {
        require_csrf_token();
        reorderPlaylist($_POST['id'] ?? '', $_POST['songs'] ?? [], $_POST['if_version'] ?? '');
    }
    if ($action === 'toggleFavorite') {
        require_csrf_token();
        toggleFavorite($_POST['song'] ?? '');
    }
    if ($action === 'playlistImage') {
        playlistImage($_GET['id'] ?? '');
    }
    if ($action === 'updatePlaylistDetails') {
        require_csrf_token();
        updatePlaylistDetails($_POST['id'] ?? '', $_POST['name'] ?? '', $_POST['description'] ?? '');
    }
    if ($action === 'setPlaylistImage') {
        require_csrf_token();
        setPlaylistImage($_POST['id'] ?? '', $_FILES['image'] ?? []);
    }
    if ($action === 'removePlaylistImage') {
        require_csrf_token();
        removePlaylistImage($_POST['id'] ?? '');
    }
    if ($action === 'setPlaylistOrder') {
        require_csrf_token();
        setPlaylistOrder($_POST['ids'] ?? []);
    }
    if ($action === 'setPlaylistSharing') {
        require_csrf_token();
        setPlaylistSharing($_POST['id'] ?? '', $_POST['shared'] ?? '', $_POST['collab'] ?? '');
    }
    if ($action === 'setPlaylistPinned') {
        require_csrf_token();
        setPlaylistPinned($_POST['id'] ?? '', $_POST['pinned'] ?? '');
    }
    if ($action === 'importPlaylist') {
        require_csrf_token();
        importPlaylist($_FILES['playlist'] ?? []);
    }

    /* ─────────────────────────── Account and users ─────────────────────── */
    if ($action === 'me') {
        $u = currentUser();
        apiResponse("success", "ok", ["user" => $u ? userPublic($u) : null, "signed_in" => isSignedIn(), "login_required" => authRequired()]);
    }
    if ($action === 'accountUpdate') {
        require_csrf_token();
        accountUpdate($_POST);
    }
    if ($action === 'usersList') {
        usersList();
    }
    if ($action === 'usersCreate') {
        require_csrf_token();
        usersCreate($_POST);
    }
    if ($action === 'usersUpdate') {
        require_csrf_token();
        usersUpdate($_POST);
    }
    if ($action === 'usersDelete') {
        require_csrf_token();
        usersDelete($_POST);
    }

    /* ──────────────────────────────── Activity ──────────────────────────── */
    if ($action === 'nowPlaying') {
        require_csrf_token();
        nowPlaying($_POST['song'] ?? '', $_POST['position'] ?? 0, $_POST['playing'] ?? false);
    }
    if ($action === 'activity') {
        activityList();
    }

    /* ──────────────────────── Folder picker (settings) ─────────────────── */
    // Sub-folders of a folder on the server (names only, no files), for
    // choosing music folders. Starts at the app folder.
    if ($action === 'browseDirs') {
        $raw  = trim((string) ($_GET['path'] ?? ''));
        $real = musicFolderReal($raw === '' ? '.' : $raw);
        if ($real === null) {
            apiResponse("error", "That folder does not exist.");
        }
        $dirs = [];
        foreach (@scandir($real) ?: [] as $name) {
            if ($name === '' || $name[0] === '.' || !is_dir($real . DIRECTORY_SEPARATOR . $name)) continue;
            $dirs[] = $name;
        }
        natcasesort($dirs);
        // Inside the app folder, show it relative to the app ("music_local")
        $app   = realpath(__DIR__);
        $shown = $real === $app ? '' : (str_starts_with($real, $app . DIRECTORY_SEPARATOR) ? substr($real, strlen($app) + 1) : $real);
        $parent = dirname($real);
        apiResponse("success", "ok", [
            "path"     => $shown,
            "absolute" => $real,
            "parent"   => $parent === $real ? null : $parent,
            "readable" => is_readable($real),
            "dirs"     => array_values($dirs),
        ]);
    }

    /* ─────────────────────────────── Song info ──────────────────────────── */
    if ($action === 'songInfo') {
        $file = (string) ($_GET['file'] ?? '');
        $path = songPath($file);
        if ($path === null) {
            apiResponse("error", "Song not found.");
        }
        $details = getSongDetails($path);
        $details['file']      = $file;
        $details['playlists'] = playlistsContaining($file);
        apiResponse("success", "ok", $details);
    }

    if ($action === 'songOnline') {
        getSongOnline($_GET['file'] ?? '');
    }

    /* ─────────────────────────── Editing song info ─────────────────────── */
    if ($action === 'songEditInfo') {
        apiResponse("success", "ok", songEditInfo($_GET['file'] ?? ''));
    }
    if ($action === 'songEdit') {
        require_csrf_token();
        songSaveOverride($_POST['file'] ?? '', $_POST);
    }
    if ($action === 'songEditReset') {
        require_csrf_token();
        songResetOverride($_POST['file'] ?? '');
    }
    if ($action === 'songCover') {
        require_csrf_token();
        songSetCover($_POST['file'] ?? '', $_FILES, !empty($_POST['remove']));
    }
    if ($action === 'songWriteTags') {
        require_csrf_token();
        songSaveToFile($_POST['file'] ?? '', $_POST, $_FILES);
    }

    /* ──────────────────────────── Fetch metadata job ───────────────────── */
    if ($action === 'metaJobStatus') {
        metaJobStatus();
    }
    if ($action === 'metaJobStart') {
        require_csrf_token();
        metaJobStart($_POST['mode'] ?? 'missing');
    }
    if ($action === 'metaJobStop') {
        require_csrf_token();
        metaJobStop();
    }

    /* ──────────────────────────────── Lyrics ─────────────────────────────── */
    if ($action === 'lyrics') {
        getLyrics($_GET['song'] ?? '');
    }

    /* ─────────────────────────── Listening sessions ─────────────────────── */
    if ($action === 'createSession') {
        require_csrf_token();
        createSession($_POST['name'] ?? '', !empty($_POST['public']), !empty($_POST['allow_all']));
    }

    if ($action === 'joinSession') {
        require_csrf_token();
        joinSession($_POST['sessionCode'] ?? $_POST['code'] ?? '', ($_POST['remote'] ?? '') === '1');
    }

    if ($action === 'leaveSession') {
        require_csrf_token();
        leaveSession();
    }

    if ($action === 'listSessions') {
        listSessions();
    }

    if ($action === 'sessionState') {
        sessionState();
    }

    if ($action === 'sessionUpdate') {
        require_csrf_token();
        sessionUpdate($_POST['state'] ?? null, $_POST['if_version'] ?? null);
    }

    if ($action === 'setDisplayName') {
        require_csrf_token();
        setDisplayName($_POST['name'] ?? '');
    }

    if ($action === 'setUserPrefs') {
        require_csrf_token();
        setUserPrefs($_POST['prefs'] ?? null);
    }

    if ($action === 'sessionMember') {
        require_csrf_token();
        sessionMemberAction($_POST['do'] ?? '', $_POST['member'] ?? '');
    }

    if ($action === 'sessionSpeaker') {
        require_csrf_token();
        sessionSpeaker($_POST['remote'] ?? false);
    }

    if ($action === 'sessionSettings') {
        require_csrf_token();
        sessionSettings($_POST['allow_all'] ?? false);
    }

} while (false);

if (empty($res)) {
    apiResponse("error", "Invalid response (empty). Probably the endpoint doesn't exist: " . $action);
}

// $res may already be a structured value or string; it is wrapped once here.
apiResponse("success", $res);

?>