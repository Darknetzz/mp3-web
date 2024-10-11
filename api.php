<?php
require_once('_includes.php');

# The API should always return JSON, no matter what.
# You want to return something else? You're in the wrong place.
header('Content-Type: application/json');

if (empty(CONFIG["audio_path"]["value"])) {
    apiError("The audio path is not set.");
}



do {

    if (getConfig("env") === "demo") {
        apiError("File actions have been disabled in this demo.");
    }

    if (empty($_GET["action"]) && empty($_POST["action"])) {
        apiError("No action specified.");
    }

    $action = isset($_GET["action"]) ? $_GET["action"] : $_POST["action"];

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
        $res = getConfig();
        break;
    }

    # REVIEW: 
    if ($action === 'setconfig') {
        $postConfig = $_POST['config'];
        if (!isset($postConfig['key']) || !isset($postConfig['value'])) {
            apiError("Key or value not set: " . json_encode($_POST));
        }
        $res = setConfig($postConfig['key'], $postConfig['value']);
        break;
    }

    if ($action === 'upload') {
        $res = uploadFile($_FILES["file"]);
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

    if (isset($_FILES["files"])) {
        $result = [];
        foreach ($_FILES["files"]["name"] as $key => $name) {
            $result[] = uploadFile(
                [
                    "name"     => $name,
                    "type"     => $_FILES["files"]["type"][$key],
                    "tmp_name" => $_FILES["files"]["tmp_name"][$key],
                    "error"    => $_FILES["files"]["error"][$key],
                    "size"     => $_FILES["files"]["size"][$key]
                ]
            );
        }
        $res = $result;
    }

} while (false);

if (empty($res)) {
    apiResponse("error", "Invalid request (empty response).");
}
apiResponse("success", $res);

?>