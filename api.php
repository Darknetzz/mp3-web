<?php
require_once('_includes.php');

# The API should always return JSON, no matter what.
# You want to return something else? You're in the wrong place.
header('Content-Type: application/json');

if (empty($config["audio_path"])) {
    apiError("The audio path is not set.");
}

/* ───────────────────────────── FUNCTION: download ─────────────────────────── */
function download($file) {
    global $config;
    $file     = urldecode($file);
    $filePath = $config["audio_path"] . '/' . $file;
    if (empty($filePath) || !file_exists($filePath)) {
        apiError("The file does not exist.");
    }
    return [
        "message" => "The file ". basename($filePath). " has been downloaded.",
        "file"    => basename($filePath),
        "path"    => $filePath
    ];
}

/* ───────────────────────────── FUNCTION: remove ─────────────────────────── */
function remove($file) {
    global $config;
    $file       = urldecode($file);
    $filePath   = $config["audio_path"] . '/' . $file;
    $deletedDir = 'deleted';
    if (!is_dir($deletedDir)) {
        mkdir($deletedDir, 0777, true);
    }
    if (!is_dir($deletedDir) || !is_writable($deletedDir)) {
        apiError("The directory <code>".$config["audio_path"]."</code> is not writable.");
    }
    if (!file_exists($filePath)) {
        apiError("The file <code>$filePath</code> does not exist.");
    }
    if (!is_file($filePath)) {
        apiError("The file <code>$filePath</code> is not a regular file.");
    }
    rename($filePath, $deletedDir . "/" . $file);
    return ["success" => "The file ". basename($filePath). " has been removed."];
}

/* ─────────────────────────── FUNCTION: listSongs ────────────────────────── */
function listSongs() {
    global $config;
    $audioPath = $config["audio_path"];
    if (!is_dir($audioPath) || !is_readable($audioPath)) {
        apiError("The audio path is not readable.");
    }
    $files = array_diff(scandir($audioPath), array('..', '.'));
    $songs = [];
    foreach ($files as $file) {
        $filePath = $audioPath . '/' . $file;
        if (is_file($filePath)) {
            $songs[] = [
                "name" => $file,
                "size" => filesize($filePath),
                "date" => date("Y-m-d H:i:s", filemtime($filePath))
            ];
        }
    }
    return $songs;
}

/* ───────────────────────────── FUNCTION: upload ─────────────────────────── */
function uploadFile(
    array $file = [
    "name"     => "",
    "type"     => "",
    "tmp_name" => "",
    "error"    => 4,
    "size"     => 0
    ]) : array {

    global $config;

    if (!is_array($file) || empty($file) || $file === []) {
        apiError("Invalid or empty file.");
    }

    if (empty($file["name"])) {
        apiError("The file name is empty.");
    }

    if (!isset($config["allowed_types"]) || !is_array($config["allowed_types"])) {
        apiError("The allowed types are not set or invalid.");
    }

    if (!isset($config["audio_path"]) || empty($config["audio_path"]) || !is_dir($config["audio_path"])) {
        apiError("The audio path is not set.");
    }

    $targetDir  = rtrim($config['audio_path'], DIRECTORY_SEPARATOR);
    $targetFile = $targetDir . "/" . htmlspecialchars(basename($file["name"]));

    if (file_exists($targetFile)) {
        apiError("Sorry, file <code>".$targetFile."</code> already exists.");
    }

    $fileType   = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    if (!in_array(strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)), $config["allowed_types"])) {
        apiError("Only ". implode(", ", $config["allowed_types"]). " files are allowed.");
    }

    if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
        apiError("There was an error moving the temporary file to its destination.");
    }

    return ["success" => "The file ". basename($targetFile). " has been uploaded. <a href='' class='btn btn-primary'>Refresh</a>", "file" => basename($targetFile)];
}

/* ──────────────────────────── FUNCTION: base64 ──────────────────────────── */
function b64_encode($fileName) {
    return ["success" => base64_encode($fileName)];
}

/* ──────────────────────────── FUNCTION: base64 ──────────────────────────── */
function b64_decode($fileName) {
    return ["success" => base64_decode($fileName)];
}

do {

    if (isset($_GET["action"]) && $_GET["action"] === "dl") {
        $res = download($_GET["file"]);
        break;
    }

    if (isset($_POST["action"]) && $_POST["action"] === "rm") {
        $res = remove($_POST["file"]);
        break;
    }

    if (isset($_GET["action"]) && $_GET["action"] === "ls") {
        $res = listSongs();
        break;
    }

    if (isset($_FILES["files"])) {
        if (is_array($_FILES["files"]["name"])) {
            $count  = count($_FILES["files"]["name"]);
            $result = [];
            for ($i = 0; $i < $count; $i++) {
                $result[] = uploadFile([
                    "name"     => $_FILES["files"]["name"][$i],
                    "type"     => $_FILES["files"]["type"][$i],
                    "tmp_name" => $_FILES["files"]["tmp_name"][$i],
                    "error"    => $_FILES["files"]["error"][$i],
                    "size"     => $_FILES["files"]["size"][$i]
                ]);
                if (!empty($result[$i]["error"])) {
                    continue;
                }
            }
        }
        $res = $result;
    }

    if (isset($_GET["action"]) && $_GET["action"] === "b64") {
        $res = b64_encode($_GET["file"]);
        break;
    }

    if (isset($_GET["action"]) && $_GET["action"] === "b64d") {
        $res = b64_decode($_GET["file"]);
        break;
    }

} while (false);

apiSuccess($res);

?>