<?php

    /* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
    // Lucide icon from the local sprite (assets/icons/lucide.svg, built by
    // assets/icons/build.py — add new names there). Sized by font-size (1em
    // box) and coloured by currentColor; $filled fills solid shapes (play,
    // heart, ...). lucideIcon() in js.php outputs the same markup.
    const ICON_SPRITE = 'assets/icons/lucide.svg?v=1.71.0';
    function icon(string $icon = "", float $size = 1.5, int $margin = 1, bool $filled = false): string {
        $name = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
        return '<svg class="lucide icon-' . $name . ' m-' . $margin . ($filled ? ' filled' : '') . '" style="font-size: ' . $size . 'em;" aria-hidden="true" focusable="false"><use href="' . ICON_SPRITE . '#' . $name . '"></use></svg>';
    }

    /* ─────────────────────────── FUNCTION: themeSetup ───────────────────────── */
    // Works out the <html> attributes, inline CSS and auto-theme script for the
    // configured theme, accent and background. All values are validated here.
    function themeSetup(): array {
        $lightThemes = ["light", "arctic"];
        $theme  = (string) getConfig("player_theme");
        $accent = (string) getConfig("accent_color");
        $isHex  = function ($v) { return is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v); };

        if ($theme === "auto") {
            $base = "dark"; // corrected before first paint by the inline script
        } elseif ($theme === "custom") {
            $base = getConfig("custom_base") === "light" ? "light" : "dark";
        } else {
            $base = in_array($theme, $lightThemes, true) ? "light" : "dark";
        }

        $attrs = [
            "data-bs-theme"  => $base,
            "data-app-theme" => preg_replace('/[^a-z]/', '', $theme),
            "data-accent"    => preg_replace('/[^a-z]/', '', $accent),
        ];
        $css = "";

        if ($theme === "custom") {
            $bg      = getConfig("custom_background");
            $surface = getConfig("custom_surface");
            $text    = getConfig("custom_text");
            if ($isHex($bg) && $isHex($surface) && $isHex($text)) {
                $css .= "html[data-app-theme=\"custom\"]{"
                      . "--tblr-body-bg:$bg;--tblr-bg-surface-secondary:$bg;"
                      . "--tblr-bg-surface:$surface;--tblr-bg-forms:$surface;"
                      . "--tblr-bg-surface-tertiary:color-mix(in oklab,$surface 92%,$text);"
                      . "--tblr-secondary-bg:color-mix(in oklab,$surface 90%,$text);"
                      . "--tblr-border-color:color-mix(in oklab,$surface 82%,$text);"
                      . "--tblr-body-color:$text;}";
            }
        }

        if ($accent === "custom") {
            $hex = getConfig("custom_accent");
            if ($isHex($hex)) {
                $rgb  = implode(",", array_map('hexdec', str_split(substr($hex, 1), 2)));
                $css .= "html[data-accent=\"custom\"]{--tblr-success:$hex;--tblr-success-rgb:$rgb;}";
            }
        }

        // Background image: http(s) URL or a relative path without anything that could break out of url("…").
        $image = trim((string) getConfig("background_image"));
        if ($image !== "" && !preg_match('/["\'()\\\\<>\s;]/', $image)
            && (preg_match('#^https?://#i', $image) || !preg_match('#^[a-z][a-z0-9+.-]*:#i', $image))) {
            $attrs["data-has-bg"] = "1";
            $dim  = max(0, min(1, (float) getConfig("background_dim")));
            $blur = max(0, min(20, (float) getConfig("background_blur")));
            $css .= "html[data-has-bg]{--app-bg-image:url(\"$image\");--app-bg-dim:$dim;--app-bg-blur:{$blur}px;}";
        }

        $html = "";
        foreach ($attrs as $name => $value) {
            $html .= " " . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        $script = "";
        if ($theme === "auto") {
            $script = "<script>(function(){var m=window.matchMedia('(prefers-color-scheme: dark)');"
                    . "function a(){document.documentElement.setAttribute('data-bs-theme',m.matches?'dark':'light');}"
                    . "a();if(m.addEventListener)m.addEventListener('change',a);})();</script>";
        }

        return ["attributes" => $html, "css" => $css, "script" => $script];
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
            // Handle case where rounding produces 60 seconds (e.g., 59.5 rounds to 60)
            if ($seconds == 60) {
                $minutes++;
                $seconds = 0;
            }
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
    // Writes the full (merged) configuration to the active config file.
    function saveConfig(array $config): void {
        $content  = "<?php\n\n";
        $content .= "/* ────────────────────────────────────────────────────────────────────────── */\n";
        $content .= "/*                                 Configuration                              */\n";
        $content .= "/* ────────────────────────────────────────────────────────────────────────── */\n";
        $content .= "# Written by the settings dialog. Defaults live in config.default.php.\n\n";
        $content .= "\$config = " . var_export($config, true) . ";\n";

        // Write in place (not temp + rename) so the file keeps its owner, mode and ACLs.
        if (@file_put_contents(CONFIG_FILE, $content, LOCK_EX) === false) {
            apiResponse("error", "Could not write <code>" . htmlspecialchars(CONFIG_FILE, ENT_QUOTES, 'UTF-8') . "</code>. Check that it is writable by the web server.");
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(CONFIG_FILE, true);
        }
        apiResponse("success", "The configuration has been saved.");
    }

    /* ────────────────────────── FUNCTION: cleanConfigValue ──────────────────── */
    // Validates and normalises a submitted value for a setting. Dies with an error if invalid.
    function cleanConfigValue(array $setting, $value) {
        $type  = $setting['type'] ?? 'string';
        $attrs = $setting['attributes'] ?? [];
        $name  = htmlspecialchars($setting['name'] ?? 'Value', ENT_QUOTES, 'UTF-8');

        switch ($type) {
            case 'bool':
                return ($value === true || $value === 'true' || $value === '1' || $value === 1 || $value === 'on');

            case 'range':
            case 'number':
                if (!is_numeric($value)) {
                    apiResponse("error", "$name must be a number.");
                }
                $value = ($type === 'number' && floor((float) $value) == (float) $value) ? (int) $value : (float) $value;
                if (isset($attrs['min']) && $value < (float) $attrs['min']) $value = (float) $attrs['min'];
                if (isset($attrs['max']) && $value > (float) $attrs['max']) $value = (float) $attrs['max'];
                return $value;

            case 'selection':
                $options = $setting['options'] ?? [];
                if (!is_array($options) || !array_key_exists((string) $value, $options)) {
                    apiResponse("error", "That is not a valid choice for $name.");
                }
                return (string) $value;

            case 'multiselect':
                $options = $setting['options'] ?? [];
                $value   = is_array($value) ? $value : [];
                return array_values(array_filter($value, function ($v) use ($options) {
                    return in_array($v, $options, true);
                }));

            case 'color':
                if (!is_string($value) || !preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                    apiResponse("error", "$name must be a color like #1db954.");
                }
                return strtolower($value);

            case 'array':
                $value = is_array($value) ? $value : [];
                $clean = [];
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '' && !in_array($item, $clean, true)) $clean[] = $item;
                }
                return $clean;

            default:
                return trim((string) $value);
        }
    }

    /* ─────────────────────────── FUNCTION: setConfig ────────────────────────── */
    function setConfig(string $key, $newValue): void {
        $config = getConfig();

        if ($key === '' || !isset($config[$key])) {
            apiResponse("error", "That setting does not exist.");
        }

        $newValue = cleanConfigValue($config[$key], $newValue);

        if ($newValue === $config[$key]['value']) {
            apiResponse("success", "No change.");
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
                        "<a href='javascript:void(0);' class='link-danger deleteBtn' data-filename='".htmlspecialchars(urlencode($file), ENT_QUOTES, 'UTF-8')."'>".icon("trash-2")."</a>",
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
            // Map file extensions to their common MIME types
            $mimeTypeMap = [
                'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg-3', 'audio/mpeg3'],
                'ogg' => ['audio/ogg', 'audio/vorbis', 'application/ogg'],
                'wav' => ['audio/wav', 'audio/wave', 'audio/x-wav']
            ];
            
            // Build allowed MIME types list based on configured allowed_types
            $allowedMimeTypes = [];
            foreach ($allowedTypes as $type) {
                $typeLower = strtolower($type);
                if (isset($mimeTypeMap[$typeLower])) {
                    $allowedMimeTypes = array_merge($allowedMimeTypes, $mimeTypeMap[$typeLower]);
                }
            }
            
            if (!in_array(strtolower($file["type"]), $allowedMimeTypes)) {
                $result = ["status" => "error", "response" => "Invalid file type. Only ". implode(", ", $allowedTypes). " files are allowed.", "data" => []];
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

?>