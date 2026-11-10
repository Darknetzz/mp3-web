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

    if (getConfig("env") === "demo") {
        $res = apiResponse("error", "File actions have been disabled in this demo.");
        break;
    }

    if (!empty($_FILES)) {
        $action = "upload";
    } elseif (array_key_exists("action", $_GET)) {
        $action = $_GET["action"];
    } elseif (array_key_exists("action", $_POST)) {
        $action = $_POST["action"];
    } else {
        $res = apiResponse("error", "Action was not found in GET or POST.");
        break;
    }

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

    /* ─────────────────────────── Listening sessions ─────────────────────── */
    if ($action === 'createSession') {
        require_csrf_token();
        createSession($_POST['name'] ?? '', !empty($_POST['public']));
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