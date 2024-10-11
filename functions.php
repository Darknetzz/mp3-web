<?php

    /* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
    function icon($icon = "", $size = 1.5, $margin = 1) {
        return '<i class="bi bi-' . $icon . ' m-' . $margin . '" style="font-size: ' . $size . 'em;"></i>';
    }

    /* ──────────────────────────── FUNCTION: mp3info ─────────────────────────── */
    function getDuration($file) {
        if (!COMPOSER || !class_exists('wapmorgan\Mp3Info\Mp3Info')) {
            return "0:00";
        }
        if (!file_exists($file)) {
            return "File <code>$file</code> not found.";
        }
        $mp3info  = new wapmorgan\Mp3Info\Mp3Info($file);
        $duration = $mp3info->duration;
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        return sprintf("%d:%02d", $minutes, $seconds);
    }

    /* ─────────────────────────── FUNCTION: apiError ────────────────────────── */
    function apiError($message = "An error occurred.") {
        die(json_encode(["error" => $message]));
    }

    /* ────────────────────────── FUNCTION: apiSuccess ───────────────────────── */
    function apiSuccess($response) {
        die(json_encode(["success" => $response]));
    }

    /* ─────────────────────────── FUNCTION: getConfig ────────────────────────── */
    function getConfig($key = Null) : mixed {
        if (!defined('CONFIG')) {
            return False;
        }
        if (!is_array(CONFIG)) {
            return False;
        }
        if (empty($key) || empty(CONFIG[$key])) {
            return False;
        }
        if ($key) {
            return CONFIG[$key]["value"];
        }

        return CONFIG;
    }

/* ─────────────────────────── FUNCTION: saveConfig ───────────────────────── */
function saveConfig($config) {
    if (!is_array($config) || empty($config)) {
        return False;
    }

    // Backup the current config.php
    if (file_exists('config.php')) {
        copy('config.php', 'config.php.bak');
    }

    $configContent = "<?php\n\n";
    $configContent .= "/* ────────────────────────────────────────────────────────────────────────── */\n";
    $configContent .= "/*                                 Configuration                              */\n";
    $configContent .= "/* ────────────────────────────────────────────────────────────────────────── */\n\n";
    $configContent .= "\$config = [\n";

    foreach ($config as $key => $setting) {
        $configContent .= "    \"$key\" => [\n";
        $configContent .= "        \"name\" => \"" . addslashes($setting['name']) . "\",\n";
        $configContent .= "        \"description\" => \"" . addslashes($setting['description']) . "\",\n";
        
        $value = $setting['value'];
        if (is_array($value)) {
            $value = '["' . implode('", "', $value) . '"]';
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } else {
            $value = '"' . addslashes($value) . '"';
        }
        $configContent .= "        \"value\" => $value,\n";
        $configContent .= "    ],\n";
    }

    $configContent .= "];\n\n";
    $configContent .= "?>";

    file_put_contents('config.php', $configContent);
    return True;
}


/* ───────────────────────────── FUNCTION: download ─────────────────────────── */
function download($file) {
    $file     = urldecode($file);
    $filePath = CONFIG["audio_path"]["value"] . '/' . $file;
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
    $file       = urldecode($file);
    $filePath   = CONFIG["audio_path"] . '/' . $file;
    $deletedDir = 'deleted';
    if (!is_dir($deletedDir)) {
        mkdir($deletedDir, 0777, true);
    }
    if (!is_dir($deletedDir) || !is_writable($deletedDir)) {
        apiError("The directory <code>".CONFIG["audio_path"]."</code> is not writable.");
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
    $audioPath = CONFIG["audio_path"]["value"];
    if (!is_dir($audioPath) || !is_readable($audioPath)) {
        apiError("The audio path is not readable.");
    }
    $files = array_diff(scandir($audioPath), array('..', '.'));
    $songs = [];
    $i = 0;
    foreach ($files as $file) {
        $filePath = $audioPath . '/' . $file;
        if (is_file($filePath)) {
            $songs[] = [
                "id"       => $i,
                "name"     => $file,
                "filename" => urlencode($file),
                "duration" => getDuration($filePath),
                "size"     => filesize($filePath),
                "date"     => date("Y-m-d H:i:s", filemtime($filePath)),
                "download" => 
                    "<a href='javascript:void(0);' class='link-success downloadBtn' data-filename='".urlencode($file)."'>".icon("download")."</a>",
                "delete"   => 
                    "<a href='javascript:void(0);' class='link-danger deleteBtn' data-filename='".urlencode($file)."'>".icon("trash-can")."</a>",
            ];
            $i++;
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

    if (!is_array($file) || empty($file) || $file === []) {
        apiError("Invalid or empty file. ".print_r($file, true));
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        switch ($file["error"]) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                apiError("The uploaded file exceeds the maximum allowed size of ". ini_get("upload_max_filesize"). ".");
                break;
            case UPLOAD_ERR_PARTIAL:
                apiError("The uploaded file was only partially uploaded.");
                break;
            case UPLOAD_ERR_NO_FILE:
                apiError("No file was uploaded.");
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                apiError("Missing a temporary folder.");
                break;
            case UPLOAD_ERR_CANT_WRITE:
                apiError("Failed to write file to disk.");
                break;
            case UPLOAD_ERR_EXTENSION:
                apiError("A PHP extension stopped the file upload.");
                break;
            default:
                apiError("Unknown upload error.");
                break;
        }
    }

    if (empty($file["name"])) {
        apiError("The file name is empty.".print_r($file, true));
    }

    if (!is_array(getConfig("allowed_types"))) {
        apiError("The allowed types are not set or invalid.");
    }

    if (empty(getConfig("audio_path")) || !is_dir(getConfig("audio_path"))) {
        apiError("The audio path is not set.");
    }

    $targetDir  = rtrim(getConfig('audio_path'), DIRECTORY_SEPARATOR);
    $targetFile = $targetDir . "/" . htmlspecialchars(basename($file["name"]));

    if (file_exists($targetFile)) {
        apiError("Sorry, file <code>".$targetFile."</code> already exists.");
    }

    $fileType   = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    if (!in_array(strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)), getConfig("allowed_types"))) {
        apiError("Only ". implode(", ", getConfig("allowed_types")). " files are allowed.");
    }

    if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
        apiError("There was an error moving the temporary file <code>".$file["tmp_name"]."</code> to its destination <code>".$targetFile."</code>.");
    }

    return ["success" => "The file ". basename($targetFile). " has been uploaded. <a href='' class='btn btn-primary'>Refresh</a>", "file" => basename($targetFile)];
}



?>