<?php
require_once('_includes.php');

# The API should always return JSON, no matter what.
# You want to return something else? You're in the wrong place.
header('Content-Type: application/json');

if (empty(CONFIG["audio_path"]["value"])) {
    apiResponse("error", "The audio path is not set.");
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
        $res = remove($_POST["file"]);
        break;
    }

    if ($action === "ls") {
        $res = listSongs();
        break;
    }

    if ($action === 'getconfig') {
        if (isset($_GET['key'])) {
            $res = getConfig($_GET['key']);
            break;
        }
        $res = getConfig();
        break;
    }

    # REVIEW: 
    if ($action === 'setconfig') {
        $postConfig = $_POST['config'];
        if (!isset($postConfig['key']) || !isset($postConfig['value'])) {
            apiResponse("error", "Key or value not set: " . json_encode($_POST));
        }
        $res = setConfig($postConfig['key'], $postConfig['value']);
        break;
    }

    if ($action == 'upload') {
        $result = [];
        foreach ($_FILES["files"]["name"] as $key => $name) {
            $file = [
                "name"     => $_FILES["files"]["name"][$key],
                "type"     => $_FILES["files"]["type"][$key],
                "tmp_name" => $_FILES["files"]["tmp_name"][$key],
                "error"    => $_FILES["files"]["error"][$key],
                "size"     => $_FILES["files"]["size"][$key]
            ];
            $result[] = uploadFile($file);
        }
        $res = $result;
        break;
    }

    if ($action == 'createSession') {
        $res = createSession();
        break;
    }

    if ($action == 'joinSession') {
        $res = joinSession();
        break;
    }

} while (false);

if (empty($res)) {
    apiResponse("error", "Invalid response (empty). Probably the endpoint doesn't exist: " . $action);
}
if (!json_validate($res)) {
    apiResponse("error", "Invalid response: " . $res);
}
apiResponse("success", $res);

?>