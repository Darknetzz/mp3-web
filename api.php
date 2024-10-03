<?php

# Configuration
if (file_exists('config.local.php')) {
    include_once('config.local.php');
} elseif (file_exists('config.php')) {
    include_once('config.php');
} else {
    die(json_encode(["error" => "The configuration file is missing."]));
}


if (empty($config["audio_path"])) {
    die(json_encode(["error" => "The audio path is not set."]));
}

/* ───────────────────────────── FUNCTION: download ─────────────────────────── */
function download($file) {
    $filePath = $config["audio_path"] . '/' . $file;
    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

/* ───────────────────────────── FUNCTION: remove ─────────────────────────── */
function remove($file) {
    $filePath = $config["audio_path"] . '/' . $file;
    $deletedDir = 'deleted/';
    if (!is_dir($deletedDir)) {
        mkdir($deletedDir, 0777, true);
    }
    if (file_exists($filePath)) {
        rename($filePath, $deletedDir . $file);
    }
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
        return ["error" => "Invalid or empty file."];
    }

    if (empty($file["name"])) {
        return ["error" => "The file name is empty."];
    }

    if (!isset($config["allowed_types"]) || !is_array($config["allowed_types"])) {
        return ["error" => "The allowed types are not set or invalid."];
    }

    if (!isset($config["audio_path"]) || empty($config["audio_path"]) || !is_dir($config["audio_path"])) {
        return ["error" => "The audio path is not set."];
    }

    $targetDir  = rtrim($config['audio_path'], DIRECTORY_SEPARATOR);
    $targetFile = $targetDir . "/" . htmlspecialchars(basename($file["name"]));

    if (file_exists($targetFile)) {
        return ["error" => "Sorry, file <code>".$targetFile."</code> already exists."];
    }

    $fileType   = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    if (!in_array(strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)), $config["allowed_types"])) {
        return ["error" => "Only ". implode(", ", $config["allowed_types"]). " files are allowed."];
    }

    if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
        return ["error" => "There was an error moving the temporary file to it's destination."];
    }

    return ["success" => "The file ". basename($targetFile). " has been uploaded."];
}

do {

    if (isset($_GET["action"]) && $_GET["action"] === "dl") {
        download($_GET["file"]);
        break;
    }

    if (isset($_POST["action"]) && $_POST["action"] === "rm") {
        remove($_POST["file"]);
        break;
    }

    if (isset($_FILES["files"])) {
        header('Content-Type: application/json');
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
        die(json_encode($result));
        break;
    }

} while (false);

die(json_encode(["error" => "Invalid request."]));

?>