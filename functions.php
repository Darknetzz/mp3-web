<?php

    /* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
    function icon($icon = "", $size = 1.5, $margin = 1) {
        return '<i class="bi bi-' . $icon . ' m-' . $margin . '" style="font-size: ' . $size . 'em;"></i>';
    }

    /* ───────────────────────────── FUNCTION: alert ──────────────────────────── */
    function alert($title = Null, $message = "", $type = "info", $margin = 2, $padding = 2, $dismiss = true) {
        $class    = "alert alert-' . $type . '  fade show";
        $closeBtn = "";
        $title    = (!empty($title)) ? '<h4 class="alert-heading">' . $title . '</h4>' : "";
        if ($dismiss) {
            $class    .= " alert-dismissible";
            $closeBtn  = '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        }
        return '
        <div class="m-' . $margin . ' p-' . $padding . '">
            <div class="'.$class.'" role="alert">
                ' . $title . '
                ' . $message . '
                ' . $closeBtn . '
            </div>
        </div>';
    }

    /* ──────────────────────────── FUNCTION: spinner ─────────────────────────── */
    function spinner($type = "primary", $id = Null, $size = 3, $margin = 2) {
        return '
        <div id="'.$id.'" class="spinner-border text-'.$type.' m-' . $margin . '" role="status" style="width: ' . $size . 'rem; height: ' . $size . 'rem;">
            <span class="visually-hidden">Loading...</span>
        </div>';
    }

    /* ──────────────────────────── FUNCTION: mp3info ─────────────────────────── */
    function getDuration($file) {
        $duration = "0:00";
        // if (!COMPOSER || !class_exists('wapmorgan\Mp3Info\Mp3Info')) {
        //  return "0:00";
        // }
        if (!file_exists($file)) {
            $duration = "File <code>$file</code> not found.";
        }
        $mp3info  = new wapmorgan\Mp3Info\Mp3Info($file);
        $duration = $mp3info->duration;
        $minutes  = floor($duration / 60);
        $seconds  = $duration % 60;
        $duration = sprintf("%d:%02d", $minutes, $seconds);
        return $duration;
    }

    /* ────────────────────────── FUNCTION: apiResponse ───────────────────────── */
    function apiResponse($status = "error", $response = "An error occurred.", $data = []) {
        if (empty($status)) {
            $status = "error";
        }
        if (!is_array($data)) {
            $data = ["invalid" => "Data is not an array."];
        }
        $response = json_encode([
            "status"     => $status,
            "statuscode" => ($status == "error") ? 1 : 0,
            "response"   => $response,
            "data"       => $data
        ]);
        die($response);
    }

    /* ─────────────────────────── FUNCTION: getConfig ────────────────────────── */
    function getConfig($key = Null, $data = "value") {
        if (!defined('CONFIG')) {
            return apiResponse("error", "The configuration is not set.");
        }
        if (!is_array(CONFIG)) {
            return apiResponse("error", "The configuration is not an array.");
        }
        if (!empty($key) && !isset(CONFIG[$key])) {
            return apiResponse("error", "The key '$key' does not exist.");
        }
        if (!empty($key) && !isset(CONFIG[$key][$data])) {
            return apiResponse("error", "The data '$data' for key '$key' does not exist.");
        }
        if (!empty($key) && array_key_exists($key, CONFIG) && !empty($data)) {
            return CONFIG[$key][$data];
        }

        return CONFIG;
    }

    /* ─────────────────────────── FUNCTION: saveConfig ───────────────────────── */
    function saveConfig($config) {
        $tab = function($amt = 1) {
            $tabs = "";
            for ($i = 0; $i < $amt; $i++) {
                $tabs .= "    ";
            }
            return $tabs;
        };
        $configContent = "<?php\n\n";
        $configContent .= "/* ────────────────────────────────────────────────────────────────────────── */\n";
        $configContent .= "/*                                 Configuration                              */\n";
        $configContent .= "/* ────────────────────────────────────────────────────────────────────────── */\n\n";
        $configContent .= "\$config = [\n";
    
        foreach ($config as $key => $setting) {

            $name        = $setting['name'];
            $description = $setting['description'];
            $value       = $setting['value'];
            $type        = $setting['type'];
            $options     = (isset($setting['options'])) ? $setting['options'] : [];
            $attributes  = (isset($setting['attributes'])) ? $setting['attributes'] : [];

            $configContent .= $tab(1)."\"$key\" => [\n";
            $configContent .= $tab(2)."\"name\" => \"" . addslashes($name) . "\",\n";
            $configContent .= $tab(2)."\"description\" => \"" . addslashes($description) . "\",\n";
            $configContent .= $tab(2)."\"type\" => \"" . addslashes($type) . "\",\n";

            # NOTE: Options
            if (!empty($options) && is_array($options)) {
                $configContent .= $tab(2)."\"options\" => [\n";
                foreach ($options as $option => $name) {
                    $configContent .= $tab(3)."\"" . addslashes($option) . "\" => \"" . addslashes($name) . "\",\n";
                }
                $configContent .= $tab(2)."],\n";
            }

            # NOTE: Value
            $configContent .= $tab(2)."\"value\" => ";
            if ($type == "range") {
                $configContent .= $value;
            } elseif ($type == "selection") {
                $configContent .= '"' . addslashes($value) . '"';
            } elseif ($type === "array") {
                $configContent .= '["' . implode('", "', $value) . '"]';
            } elseif ($type === "bool") {
                $value = ($value === true || $value === "true") ? "true" : "false";
                $configContent .= $value;
            } else {
                $configContent .= "\"" . addslashes($value) . "\"";
            }
            # NOTE: End of value
            $configContent .= ",\n";

            # NOTE: Attributes
            if (!empty($attributes) && is_array($attributes)) {
                $configContent .= $tab(2)."\"attributes\" => [\n";
                foreach ($attributes as $attrKey => $attrValue) {
                    $configContent .= $tab(3)."\"$attrKey\" => \"$attrValue\",\n";
                }
                # NOTE: End of Attributes
                $configContent .= $tab(1)."],\n";
            }
            $configContent .= $tab(2)."],\n";
        }

        # NOTE: End of config file
        $configContent .= "];\n\n";
        $configContent .= "?>";
    
        file_put_contents(CONFIG_FILE, $configContent);
        return apiResponse("success", "The configuration has been saved.");
    }

    /* ─────────────────────────── FUNCTION: setConfig ────────────────────────── */
    function setConfig($key, $newValue) {
        $config = getConfig();
    
        if (!isset($config[$key])) {
            return apiResponse("error", "Key does not exist"); // Key does not exist
        }

        if ($newValue === $config[$key]['value']) {
            return apiResponse("error", "Value is the same"); // Value is the same
        }

        if (empty($key) || empty($newValue)) {
            return apiResponse("error", "Key or value is empty"); // Key or value is empty
        }
    
        $config[$key]['value'] = $newValue;
    
        return saveConfig($config);
    }


    /* ───────────────────────────── FUNCTION: download ─────────────────────────── */
    // function download($file) {
    //     $file     = urldecode($file);
    //     $filePath = getConfig("audio_path") . '/' . $file;
    //     if (empty($filePath) || !file_exists($filePath)) {
    //         apiResponse("error", "The file does not exist.");
    //     }
    //     return [
    //         "message" => "The file ". basename($filePath). " has been downloaded.",
    //         "file"    => basename($filePath),
    //         "path"    => $filePath
    //     ];
    // }

    /* ───────────────────────────── FUNCTION: remove ─────────────────────────── */
    function remove($file) {
        $file       = urldecode($file);
        $filePath   = getConfig("audio_path") . '/' . $file;
        $deletedDir = 'deleted';
        if (!is_dir($deletedDir)) {
            mkdir($deletedDir, 0777, true);
        }
        if (!is_dir($deletedDir) || !is_writable($deletedDir)) {
            return apiResponse("error", "The directory <code>".CONFIG["audio_path"]."</code> is not writable.");
        }
        if (!file_exists($filePath)) {
            return apiResponse("error", "The file <code>$filePath</code> does not exist.");
        }
        if (!is_file($filePath)) {
            return apiResponse("error", "The file <code>$filePath</code> is not a regular file.");
        }
        rename($filePath, $deletedDir . "/" . $file);
        return apiResponse("success", "The file ". basename($filePath). " has been removed.");
    }

    /* ─────────────────────────── FUNCTION: listSongs ────────────────────────── */
    function listSongs() {
        $audioPath = CONFIG["audio_path"]["value"];
        if (!is_dir($audioPath) || !is_readable($audioPath)) {
            return apiResponse("error", "The audio path is not readable.");
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
        return apiResponse("success", "OK", $songs);
    }

    /* ───────────────────────────── FUNCTION: upload ─────────────────────────── */
    function uploadFile(
        array $file = [
            "name"     => "",
            "type"     => "",
            "tmp_name" => "",
            "error"    => 4,
            "size"     => 0
        ]) {

        if (is_array($file['name']) && count($file['name']) > 1) {
            return apiResponse("error", "Multiple file upload supported, but should not be passed directly to <code>uploadFile</code> function.");
        }

        if (!is_array($file) || empty($file) || $file === []) {
            return apiResponse("error", "Invalid or empty file. ".print_r($file, true));
        }

        if (!array_key_exists("error", $file)) {
            return apiResponse("error", "The file array does not contain an error key.");
        }

        if ($file["error"] !== UPLOAD_ERR_OK) {
            switch ($file["error"]) {
                case UPLOAD_ERR_INI_SIZE:
                    return apiResponse("error", "The uploaded file exceeds the upload_max_filesize directive in php.ini.");
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    return apiResponse("error", "The uploaded file exceeds the maximum allowed size of ". ini_get("upload_max_filesize"). ".");
                    break;
                case UPLOAD_ERR_PARTIAL:
                    return apiResponse("error", "The uploaded file was only partially uploaded.");
                    break;
                case UPLOAD_ERR_NO_FILE:
                    return apiResponse("error", "No file was uploaded.");
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    return apiResponse("error", "Missing a temporary folder.");
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    return apiResponse("error", "Failed to write file to disk.");
                    break;
                case UPLOAD_ERR_EXTENSION:
                    return apiResponse("error", "A PHP extension stopped the file upload.");
                    break;
                default:
                    return apiResponse("error", "Unknown upload error (".print_r($file["error"], true).").");
                    break;
            }
        }

        if (empty($file["name"])) {
            return apiResponse("error", "The file name is empty.".print_r($file, true));
        }

        if (!is_array(getConfig("allowed_types"))) {
            return apiResponse("error", "The allowed types are not set or invalid.");
        }

        if (empty(getConfig("audio_path")) || !is_dir(getConfig("audio_path"))) {
            return apiResponse("error", "The audio path is not set.");
        }

        $targetDir  = rtrim(getConfig('audio_path'), DIRECTORY_SEPARATOR);
        $targetFile = $targetDir . "/" . htmlspecialchars(basename($file["name"]));

        if (file_exists($targetFile)) {
            return apiResponse("error", "Sorry, file <code>".$targetFile."</code> already exists.");
        }

        $fileType   = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        if (!in_array(strtolower(pathinfo($file["name"], PATHINFO_EXTENSION)), getConfig("allowed_types"))) {
            return apiResponse("error", "Only ". implode(", ", getConfig("allowed_types")). " files are allowed.");
        }

        if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
            return apiResponse("error", "There was an error moving the temporary file <code>".$file["tmp_name"]."</code> to its destination <code>".$targetFile."</code>.");
        }

        return apiResponse(
            status: "success", 
            response: "The file ". basename($targetFile). " has been uploaded. <a href='' class='btn btn-primary'>Refresh</a>", 
            data: [
                "file"      => basename($targetFile),
                "fileArray" => $file
            ]
        );
    }

    /* ───────────────────────── FUNCTION: createSession ──────────────────────── */
    function createSession($id = Null, $public = False) {
        global $_SESSION;
        if (!empty($_SESSION["session_code"])) {
            $sessionCode = $_SESSION["session_code"];
            return apiResponse("error", "Session <code>$sessionCode</code> is already active.");
        }
        if (empty($id)) {
            $id = bin2hex(random_bytes(4));
        }
        if (preg_match('/[^a-zA-Z0-9]/', $id) || strlen($id) !== 8) {
            return apiResponse("error", "The session code is invalid. It must be 8 characters long and alphanumeric.");
        }
        $sessionDir = 'session';
        if (!is_dir($sessionDir)) {
            $createDir = mkdir($sessionDir, 0777, true);
            if (!$createDir) {
                return apiResponse("error", "The session directory <code>$sessionDir</code> could not be created.");
            }
        }
        if (!is_dir($sessionDir) || !is_writable($sessionDir)) {
            return apiResponse("error", "The session directory <code>$sessionDir</code> is not writable.");
        }
        if (file_exists($sessionDir . '/' . $id . '.json')) {
            return apiResponse("error", "The session code <code>$id</code> already exists.");
        }

        $session = [
            "session_code" => $id,
            "created"      => date("Y-m-d H:i:s"),
            "public"       => $public
        ];

        $sessionFile = $sessionDir . '/' . $id . '.json';
        touch($sessionFile);
        chmod($sessionFile, 0777);
        file_put_contents($sessionFile, json_encode($session, JSON_PRETTY_PRINT));
        if (!file_exists($sessionFile) || !is_file($sessionFile)) {
            return apiResponse("error", "The session file <code>$sessionFile</code> could not be created.");
        }

        $_SESSION["session_code"] = $id;
        return apiResponse("success", "The session <code>$id</code> has been created.", ["session_code" => $id]);
    }

    /* ───────────────────────── FUNCTION: joinSession ─────────────────────────── */
    function joinSession($id = Null) {
        global $_SESSION;
        if (empty($id)) {
            return apiResponse("error", "The session code is empty.");
        }
        if (!file_exists('session/' . $id . '.json') || !is_file('session/' . $id . '.json')) {
            return apiResponse("error", "The session code is invalid.");
        }
        $_SESSION["session_code"] = $id;
        return apiResponse("success", "You have joined session <code>$id</code>.", ["session_code" => $id]);
    }



?>