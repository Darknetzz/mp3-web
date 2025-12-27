<?php

    /* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
    function icon(string $icon = "", float $size = 1.5, int $margin = 1): string {
        return '<i class="bi bi-' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' m-' . $margin . '" style="font-size: ' . $size . 'em;"></i>';
    }

    /* ───────────────────────────── FUNCTION: alert ──────────────────────────── */
    function alert(?string $title = null, string $message = "", string $type = "info", int $margin = 2, int $padding = 2, bool $dismiss = true): string {
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
    function spinner(string $type = "primary", ?string $id = null, int $size = 3, int $margin = 2): string {
        return '
        <div id="'.$id.'" class="spinner-border text-'.$type.' m-' . $margin . '" role="status" style="width: ' . $size . 'rem; height: ' . $size . 'rem;">
            <span class="visually-hidden">Loading...</span>
        </div>';
    }

    /* ──────────────────────────── FUNCTION: mp3info ─────────────────────────── */
    function getDuration(string $file): string {
        if (!file_exists($file)) {
            return "0:00";
        }
        
        try {
            $mp3info  = new wapmorgan\Mp3Info\Mp3Info($file);
            $duration = $mp3info->duration;
            $minutes  = (int)floor($duration / 60);
            $secondsFloat = round(fmod($duration, 60));
            $seconds  = (int)$secondsFloat;
            $duration = sprintf("%d:%02d", $minutes, $seconds);
            return $duration;
        } catch (Exception $e) {
            return "0:00";
        }
    }

    /* ────────────────────────── FUNCTION: apiResponse ───────────────────────── */
    function apiResponse(string $status = "error", string $response = "An error occurred.", array $data = []): void {
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
    function getConfig(?string $key = null, string $data = "value") {
        if (!defined('CONFIG')) {
            apiResponse("error", "The configuration is not set.");
        }
        if (!is_array(CONFIG)) {
            apiResponse("error", "The configuration is not an array.");
        }
        if (!empty($key) && !isset(CONFIG[$key])) {
            apiResponse("error", "The key '$key' does not exist.");
        }
        if (!empty($key) && !isset(CONFIG[$key][$data])) {
            apiResponse("error", "The data '$data' for key '$key' does not exist.");
        }
        if (!empty($key) && array_key_exists($key, CONFIG) && !empty($data)) {
            return CONFIG[$key][$data];
        }

        return CONFIG;
    }

    /* ─────────────────────────── FUNCTION: saveConfig ───────────────────────── */
    function saveConfig(array $config): void {
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
            } elseif ($type === "selection") {
                $configContent .= '"' . addslashes($value) . '"';
            } elseif ($type === "multiselect") {
                $configContent .= '<input type="checkbox" name="multiselect" value="1" '.($value ? 'checked' : '').'>';
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
        apiResponse("success", "The configuration has been saved.");
    }

    /* ─────────────────────────── FUNCTION: setConfig ────────────────────────── */
    function setConfig(string $key, $newValue): void {
        $config = getConfig();
    
        if (!isset($config[$key])) {
            apiResponse("error", "Key does not exist"); // Key does not exist
        }

        if ($newValue === $config[$key]['value']) {
            apiResponse("error", "Value is the same"); // Value is the same
        }

        if (empty($key) || empty($newValue)) {
            apiResponse("error", "Key or value is empty"); // Key or value is empty
        }
    
        $config[$key]['value'] = $newValue;
    
        saveConfig($config);
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
    function remove(string $file): void {
        $file = urldecode($file);
        // Prevent path traversal - only allow filename
        $file = basename($file);
        
        $audioPath = getConfig("audio_path");
        $filePath = realpath($audioPath . '/' . $file);
        $audioPathReal = realpath($audioPath);
        
        // Verify file is within audio path directory (prevent path traversal)
        if ($filePath === false || $audioPathReal === false || strpos($filePath, $audioPathReal) !== 0) {
            apiResponse("error", "Invalid file path.");
        }
        
        $deletedDir = 'deleted';
        if (!is_dir($deletedDir)) {
            mkdir($deletedDir, 0755, true);
        }
        if (!is_dir($deletedDir) || !is_writable($deletedDir)) {
            apiResponse("error", "The directory <code>" . htmlspecialchars($audioPath, ENT_QUOTES, 'UTF-8') . "</code> is not writable.");
        }
        if (!file_exists($filePath)) {
            apiResponse("error", "The file <code>" . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . "</code> does not exist.");
        }
        if (!is_file($filePath)) {
            apiResponse("error", "The file <code>" . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8') . "</code> is not a regular file.");
        }
        rename($filePath, $deletedDir . "/" . $file);
        apiResponse("success", "The file " . htmlspecialchars(basename($filePath), ENT_QUOTES, 'UTF-8') . " has been removed.");
    }

    /* ─────────────────────────── FUNCTION: listSongs ────────────────────────── */
    function listSongs(): void {
        $audioPath = getConfig("audio_path");
        if (!is_dir($audioPath) || !is_readable($audioPath)) {
            apiResponse("error", "The audio path is not readable.");
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
                        "<a href='javascript:void(0);' class='link-success downloadBtn' data-filename='".htmlspecialchars(urlencode($file), ENT_QUOTES, 'UTF-8')."'>".icon("download")."</a>",
                    "delete"   => 
                        "<a href='javascript:void(0);' class='link-danger deleteBtn' data-filename='".htmlspecialchars(urlencode($file), ENT_QUOTES, 'UTF-8')."'>".icon("trash-can")."</a>",
                ];
                $i++;
            }
        }
        apiResponse("success", "OK", $songs);
    }

    /* ───────────────────────────── FUNCTION: upload ─────────────────────────── */
    function uploadFile(array $file, bool $returnResult = false): array {

        if (is_array($file['name']) && count($file['name']) > 1) {
            $result = ["status" => "error", "response" => "Multiple file upload supported, but should not be passed directly to <code>uploadFile</code> function.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        if (!is_array($file) || empty($file) || $file === []) {
            $result = ["status" => "error", "response" => "Invalid or empty file. ".print_r($file, true), "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        if (!array_key_exists("error", $file)) {
            $result = ["status" => "error", "response" => "The file array does not contain an error key.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $errorMessage = "";
            switch ($file["error"]) {
                case UPLOAD_ERR_INI_SIZE:
                    $errorMessage = "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = "The uploaded file exceeds the maximum allowed size of ". ini_get("upload_max_filesize"). ".";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = "The uploaded file was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessage = "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage = "Missing a temporary folder.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $errorMessage = "Unknown upload error (".print_r($file["error"], true).").";
            }
            $result = ["status" => "error", "response" => $errorMessage, "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $errorMessage);
        }

        if (empty($file["name"])) {
            $result = ["status" => "error", "response" => "The file name is empty.".print_r($file, true), "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        if (!is_array(getConfig("allowed_types"))) {
            $result = ["status" => "error", "response" => "The allowed types are not set or invalid.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        if (empty(getConfig("audio_path")) || !is_dir(getConfig("audio_path"))) {
            $result = ["status" => "error", "response" => "The audio path is not set.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        $targetDir  = rtrim(getConfig('audio_path'), DIRECTORY_SEPARATOR);
        
        // Sanitize filename - prevent path traversal and dangerous characters
        $filename = basename($file["name"]);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate file extension
        $allowedTypes = getConfig("allowed_types");
        if (!in_array($fileExtension, array_map('strtolower', $allowedTypes))) {
            $result = ["status" => "error", "response" => "Only ". implode(", ", $allowedTypes). " files are allowed.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }
        
        // Validate MIME type if available
        if (!empty($file["type"])) {
            $allowedMimeTypes = [
                'audio/mpeg',
                'audio/mp3',
                'audio/x-mpeg-3',
                'audio/mpeg3'
            ];
            if (!in_array(strtolower($file["type"]), $allowedMimeTypes)) {
                $result = ["status" => "error", "response" => "Invalid file type. Only MP3 files are allowed.", "data" => []];
                if ($returnResult) return $result;
                apiResponse("error", $result["response"]);
            }
        }
        
        $targetFile = $targetDir . "/" . $filename;

        if (file_exists($targetFile)) {
            $result = ["status" => "error", "response" => "Sorry, file <code>".htmlspecialchars($targetFile, ENT_QUOTES, 'UTF-8')."</code> already exists.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        if (!move_uploaded_file($file["tmp_name"], $targetFile)) {
            $result = ["status" => "error", "response" => "There was an error moving the temporary file to its destination.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        $result = [
            "status" => "success",
            "response" => "The file ". basename($targetFile). " has been uploaded. <a href='' class='btn btn-primary'>Refresh</a>",
            "data" => [
                "file"      => basename($targetFile),
                "fileArray" => $file
            ]
        ];
        if ($returnResult) return $result;
        apiResponse("success", $result["response"], $result["data"]);
    }

    /* ───────────────────────── FUNCTION: createSession ──────────────────────── */
    function createSession(?string $id = null, bool $public = false): void {
        global $_SESSION;
        if (!empty($_SESSION["session_code"])) {
            $sessionCode = $_SESSION["session_code"];
            apiResponse("error", "Session <code>".htmlspecialchars($sessionCode, ENT_QUOTES, 'UTF-8')."</code> is already active.");
        }
        if (empty($id)) {
            $id = bin2hex(random_bytes(4));
        }
        if (preg_match('/[^a-zA-Z0-9]/', $id) || strlen($id) !== 8) {
            apiResponse("error", "The session code is invalid. It must be 8 characters long and alphanumeric.");
        }
        $sessionDir = 'session';
        if (!is_dir($sessionDir)) {
            $createDir = mkdir($sessionDir, 0755, true);
            if (!$createDir) {
                apiResponse("error", "The session directory <code>".htmlspecialchars($sessionDir, ENT_QUOTES, 'UTF-8')."</code> could not be created.");
            }
        }
        if (!is_dir($sessionDir) || !is_writable($sessionDir)) {
            apiResponse("error", "The session directory <code>".htmlspecialchars($sessionDir, ENT_QUOTES, 'UTF-8')."</code> is not writable.");
        }
        if (file_exists($sessionDir . '/' . $id . '.json')) {
            apiResponse("error", "The session code <code>".htmlspecialchars($id, ENT_QUOTES, 'UTF-8')."</code> already exists.");
        }

        $session = [
            "session_code" => $id,
            "created"      => date("Y-m-d H:i:s"),
            "public"       => $public
        ];

        $sessionFile = $sessionDir . '/' . $id . '.json';
        touch($sessionFile);
        chmod($sessionFile, 0644);
        file_put_contents($sessionFile, json_encode($session, JSON_PRETTY_PRINT));
        if (!file_exists($sessionFile) || !is_file($sessionFile)) {
            apiResponse("error", "The session file <code>".htmlspecialchars($sessionFile, ENT_QUOTES, 'UTF-8')."</code> could not be created.");
        }

        $_SESSION["session_code"] = $id;
        apiResponse("success", "The session <code>".htmlspecialchars($id, ENT_QUOTES, 'UTF-8')."</code> has been created.", ["session_code" => $id]);
    }

    /* ───────────────────────── FUNCTION: joinSession ─────────────────────────── */
    function joinSession(?string $id = null): void {
        global $_SESSION;
        if (empty($id)) {
            apiResponse("error", "The session code is empty.");
        }
        if (!file_exists('session/' . $id . '.json') || !is_file('session/' . $id . '.json')) {
            apiResponse("error", "The session code is invalid.");
        }
        $_SESSION["session_code"] = $id;
        apiResponse("success", "You have joined session <code>".htmlspecialchars($id, ENT_QUOTES, 'UTF-8')."</code>.", ["session_code" => $id]);
    }



?>