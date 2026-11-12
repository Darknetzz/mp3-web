<?php
require_once('_includes.php');

# The API should always return JSON, no matter what.
# You want to return something else? You're in the wrong place.
header('Content-Type: application/json');

if (empty(getConfig("audio_path"))) {
    apiResponse("error", "The audio path is not set.");
}

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
        apiResponse("success", "ok", ["config" => getConfig()]);
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
        createPlaylist($_POST['name'] ?? '', $_POST['songs'] ?? []);
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
    if ($action === 'setPlaylistPinned') {
        require_csrf_token();
        setPlaylistPinned($_POST['id'] ?? '', $_POST['pinned'] ?? '');
    }
    if ($action === 'importPlaylist') {
        require_csrf_token();
        importPlaylist($_FILES['playlist'] ?? []);
    }

    /* ─────────────────────────────── Song info ──────────────────────────── */
    if ($action === 'songInfo') {
        $file = basename(str_replace('\\', '/', (string) ($_GET['file'] ?? '')));
        $path = getConfig("audio_path") . '/' . $file;
        if ($file === '' || $file === '.' || $file === '..' || !is_file($path)
            || !in_array(pathinfo($file, PATHINFO_EXTENSION), (array) getConfig('allowed_types'), true)) {
            apiResponse("error", "Song not found.");
        }
        $details = getSongDetails($path);
        $details['file']      = $file;
        $details['playlists'] = playlistsContaining($file);
        apiResponse("success", "ok", $details);
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
        joinSession($_POST['sessionCode'] ?? $_POST['code'] ?? '');
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