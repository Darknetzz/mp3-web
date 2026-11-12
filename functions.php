<?php

    /* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
    // Lucide icon from the local sprite (assets/icons/lucide.svg, built by
    // assets/icons/build.py — add new names there). Sized by font-size (1em
    // box) and coloured by currentColor; $filled fills solid shapes (play,
    // heart, ...). lucideIcon() in js.php outputs the same markup.
    define('ICON_SPRITE', 'assets/icons/lucide.svg?v=' . @filemtime(__DIR__ . '/assets/icons/lucide.svg'));
    function icon(string $icon = "", float $size = 1.5, int $margin = 1, bool $filled = false): string {
        $name = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
        return '<svg class="lucide icon-' . $name . ' m-' . $margin . ($filled ? ' filled' : '') . '" style="font-size: ' . $size . 'em;" aria-hidden="true" focusable="false"><use href="' . ICON_SPRITE . '#' . $name . '"></use></svg>';
    }

    /* ─────────────────────────── FUNCTION: themeSetup ───────────────────────── */
    // Works out the <html> attributes, inline CSS and auto-theme script for the
    // configured theme, accent and background. All values are validated here.
    // $values (key => value) overrides the loaded config, e.g. right after a save.
    function themeSetup(?array $values = null): array {
        $getConfig = function (string $key) use ($values) {
            return ($values !== null && array_key_exists($key, $values)) ? $values[$key] : getConfig($key);
        };
        $lightThemes = ["light", "arctic"];
        $theme  = (string) $getConfig("player_theme");
        $accent = (string) $getConfig("accent_color");
        $isHex  = function ($v) { return is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v); };

        if ($theme === "auto") {
            $base = "dark"; // corrected before first paint by the inline script
        } elseif ($theme === "custom") {
            $base = $getConfig("custom_base") === "light" ? "light" : "dark";
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
            $bg      = $getConfig("custom_background");
            $surface = $getConfig("custom_surface");
            $text    = $getConfig("custom_text");
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
            $hex = $getConfig("custom_accent");
            if ($isHex($hex)) {
                $rgb  = implode(",", array_map('hexdec', str_split(substr($hex, 1), 2)));
                $css .= "html[data-accent=\"custom\"]{--tblr-success:$hex;--tblr-success-rgb:$rgb;}";
            }
        }

        // Background image: http(s) URL or a relative path without anything that could break out of url("…").
        $image = trim((string) $getConfig("background_image"));
        if ($image !== "" && !preg_match('/["\'()\\\\<>\s;]/', $image)
            && (preg_match('#^https?://#i', $image) || !preg_match('#^[a-z][a-z0-9+.-]*:#i', $image))) {
            $attrs["data-has-bg"] = "1";
            $dim  = max(0, min(1, (float) $getConfig("background_dim")));
            $blur = max(0, min(20, (float) $getConfig("background_blur")));
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

        return ["attributes" => $html, "attrs" => $attrs, "css" => $css, "script" => $script];
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
            // Only Exception is caught: an Error from Mp3Info (e.g. DivisionByZeroError on
            // some MPEG-2 files) still propagates and stops the page.
            return "0:00";
        }
    }

    /* ────────────────────────── FUNCTION: songTagText ──────────────────────── */
    // An ID3 tag value as clean UTF-8 text ('' for anything that isn't text).
    function songTagText($v): string {
        if (!is_string($v) && !is_numeric($v)) return '';
        $v = (string) $v;
        if (!mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        }
        // Strip NULs/control chars left over from fixed-width id3v1 fields
        return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '');
    }

    /* ────────────────────────── FUNCTION: getSongMeta ───────────────────────── */
    // ID3 title/artist/album/year plus duration, read in one pass. Results are
    // cached in cache/meta/ (git-ignored) until the file changes. Missing tags are ''.
    // Edits made in the app (songedit.php) are applied on top unless $raw.
    function getSongMeta(string $file, bool $raw = false): array {
        $meta = ['title' => '', 'artist' => '', 'album' => '', 'year' => '', 'duration' => '0:00'];
        if (!is_file($file)) {
            return $meta;
        }
        $mtime    = filemtime($file);
        $cacheDir = __DIR__ . '/cache/meta';
        $cached   = $cacheDir . '/' . sha1(realpath($file) ?: $file) . '.json';
        if (is_file($cached) && filemtime($cached) >= $mtime) {
            $data = json_decode((string) @file_get_contents($cached), true);
            if (is_array($data)) {
                $meta = array_merge($meta, array_intersect_key($data, $meta));
                return $raw ? $meta : songApplyOverride($file, $meta);
            }
        }

        $clean = 'songTagText';
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp3') {
            try {
                $info = new wapmorgan\Mp3Info\Mp3Info($file, true);
                $tags = (array) $info->tags;
                $meta['title']  = $clean($tags['song'] ?? '');
                $meta['artist'] = $clean($tags['artist'] ?? '');
                $meta['album']  = $clean($tags['album'] ?? '');
                $meta['year']   = $clean($tags['year'] ?? '');
                $d = (int) round((float) $info->duration);
                $meta['duration'] = sprintf("%d:%02d", intdiv($d, 60), $d % 60);
            } catch (Throwable $e) {
                $meta['duration'] = getDuration($file);
            }
        } else {
            $meta['duration'] = getDuration($file);
        }

        if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
            @file_put_contents($cached, json_encode($meta, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $raw ? $meta : songApplyOverride($file, $meta);
    }

    /* ───────────────────────── FUNCTION: getSongDetails ─────────────────────── */
    // Everything the song info card shows: getSongMeta() plus genre, track,
    // comment and the audio stream (bitrate, sample rate, channels). Cached in
    // cache/meta/ under its own key, so the song table's lighter read stays cheap.
    function getSongDetails(string $file, bool $raw = false): array {
        $details = getSongMeta($file, true) + [
            'genre' => '', 'track' => '', 'comment' => '', 'seconds' => 0,
            'bitrate' => 0, 'sampleRate' => 0, 'channels' => '', 'vbr' => false,
            'format' => strtoupper(pathinfo($file, PATHINFO_EXTENSION)),
            'size' => 0, 'modified' => 0,
        ];
        if (!is_file($file)) {
            return $details;
        }
        $details['size']     = filesize($file);
        $details['modified'] = filemtime($file);
        $cacheDir = __DIR__ . '/cache/meta';
        $cached   = $cacheDir . '/' . sha1('details:' . (realpath($file) ?: $file)) . '.json';
        if (is_file($cached) && filemtime($cached) >= $details['modified']) {
            $data = json_decode((string) @file_get_contents($cached), true);
            if (is_array($data)) {
                $details = array_merge($details, array_intersect_key($data, $details));
                return $raw ? $details : songApplyOverride($file, $details + ['edited' => []]);
            }
        }

        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'mp3') {
            try {
                $info = new wapmorgan\Mp3Info\Mp3Info($file, true);
                $tags = (array) $info->tags;
                $details['genre']   = songGenreName(songTagText($tags['genre'] ?? ''));
                $details['track']   = songTagText($tags['track'] ?? '');
                $details['comment'] = songTagText($tags['comment'] ?? '');
                $details['seconds'] = (int) round((float) $info->duration);
                $details['bitrate'] = (int) round((int) $info->bitRate / 1000);
                $details['sampleRate'] = (int) $info->sampleRate;
                $details['vbr']     = (bool) $info->isVbr;
                $channels = [
                    wapmorgan\Mp3Info\Mp3Info::STEREO       => 'Stereo',
                    wapmorgan\Mp3Info\Mp3Info::JOINT_STEREO => 'Joint stereo',
                    wapmorgan\Mp3Info\Mp3Info::DUAL_MONO    => 'Dual mono',
                    wapmorgan\Mp3Info\Mp3Info::MONO         => 'Mono',
                ];
                $details['channels'] = $channels[$info->channel] ?? '';
            } catch (Throwable $e) {
                // Keep what getSongMeta() found
            }
        }

        if (is_dir($cacheDir) || @mkdir($cacheDir, 0775, true)) {
            @file_put_contents($cached, json_encode($details, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
        return $raw ? $details : songApplyOverride($file, $details + ['edited' => []]);
    }

    /* ───────────────────────── FUNCTION: songGenreName ─────────────────────── */
    // ID3 genres are often stored as a number ("17", "(17)" or "(17)Rock").
    // Turn the common ID3v1 numbers into names; leave anything else as written.
    function songGenreName(string $genre): string {
        static $names = ['Blues', 'Classic Rock', 'Country', 'Dance', 'Disco', 'Funk', 'Grunge', 'Hip-Hop',
            'Jazz', 'Metal', 'New Age', 'Oldies', 'Other', 'Pop', 'R&B', 'Rap', 'Reggae', 'Rock', 'Techno',
            'Industrial', 'Alternative', 'Ska', 'Death Metal', 'Pranks', 'Soundtrack', 'Euro-Techno', 'Ambient',
            'Trip-Hop', 'Vocal', 'Jazz+Funk', 'Fusion', 'Trance', 'Classical', 'Instrumental', 'Acid', 'House',
            'Game', 'Sound Clip', 'Gospel', 'Noise', 'Alternative Rock', 'Bass', 'Soul', 'Punk', 'Space',
            'Meditative', 'Instrumental Pop', 'Instrumental Rock', 'Ethnic', 'Gothic', 'Darkwave',
            'Techno-Industrial', 'Electronic', 'Pop-Folk', 'Eurodance', 'Dream', 'Southern Rock', 'Comedy',
            'Cult', 'Gangsta', 'Top 40', 'Christian Rap', 'Pop/Funk', 'Jungle', 'Native American', 'Cabaret',
            'New Wave', 'Psychedelic', 'Rave', 'Showtunes', 'Trailer', 'Lo-Fi', 'Tribal', 'Acid Punk',
            'Acid Jazz', 'Polka', 'Retro', 'Musical', 'Rock & Roll', 'Hard Rock'];
        if (preg_match('/^\(?(\d{1,3})\)?(.*)$/', $genre, $m)) {
            $rest = trim($m[2]);
            if ($rest !== '') return $rest;
            return $names[(int) $m[1]] ?? $genre;
        }
        if ($genre === 'RX') return 'Remix';
        if ($genre === 'CR') return 'Cover';
        return $genre;
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
    function writeConfigFile(array $config): bool {
        $content  = "<?php\n\n";
        $content .= "/* ────────────────────────────────────────────────────────────────────────── */\n";
        $content .= "/*                                 Configuration                              */\n";
        $content .= "/* ────────────────────────────────────────────────────────────────────────── */\n";
        $content .= "# Written by the settings dialog. Defaults live in config.default.php.\n\n";
        $content .= "\$config = " . var_export($config, true) . ";\n";

        // Write in place (not temp + rename) so the file keeps its owner, mode and ACLs.
        if (@file_put_contents(CONFIG_FILE, $content, LOCK_EX) === false) {
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(CONFIG_FILE, true);
        }
        return true;
    }

    function saveConfig(array $config, array $data = []): void {
        if (!writeConfigFile($config)) {
            apiResponse("error", "Could not write <code>" . htmlspecialchars(CONFIG_FILE, ENT_QUOTES, 'UTF-8') . "</code>. Check that it is writable by the web server.");
        }
        apiResponse("success", "The configuration has been saved.", $data);
    }

    /** Settings are per user unless marked "scope" => "global". */
    function configIsUserScope(array $setting): bool {
        return ($setting['scope'] ?? 'user') !== 'global';
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
                // Options are a list of values, or a value => label map
                $options = $setting['options'] ?? [];
                $allowed = array_is_list($options) ? $options : array_map('strval', array_keys($options));
                $value   = is_array($value) ? $value : [];
                return array_values(array_filter($value, function ($v) use ($allowed) {
                    return in_array($v, $allowed, true);
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
                // Only audio types: the library and stream.php serve these files
                if (!empty($setting['audio_only'])) {
                    $clean = array_values(array_intersect(array_map('strtolower', $clean), AUDIO_WHITELIST));
                    if (!$clean) apiResponse("error", "$name must include at least one of: " . implode(", ", AUDIO_WHITELIST) . ".");
                }
                return $clean;

            case 'folders':
                $value = is_array($value) ? $value : [];
                $clean = [];
                $reals = [];
                foreach ($value as $f) {
                    $path = trim((string) (is_array($f) ? ($f['path'] ?? '') : $f));
                    if ($path === '') continue;
                    $real = musicFolderReal($path);
                    $shown = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
                    if ($real === null) apiResponse("error", "The folder <code>$shown</code> does not exist.");
                    if (!is_readable($real)) apiResponse("error", "The folder <code>$shown</code> is not readable by the web server.");
                    if (isset($reals[$real])) continue;
                    $reals[$real] = true;
                    $clean[] = ['path' => $path, 'recursive' => !is_array($f) || !array_key_exists('recursive', $f) || cleanConfigValue(['type' => 'bool'], $f['recursive'])];
                }
                if (!$clean) apiResponse("error", "Add at least one music folder.");
                if (count($clean) > 20) apiResponse("error", "At most 20 music folders.");
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
        // Per-user settings go to the signed-in user's account; the rest (and
        // everything while nobody has an account) to the config file.
        $perUser = configIsUserScope($config[$key]) && currentUserId() > 0;
        if (!$perUser) {
            requireAdmin();
        }
        if ($key === 'auth_required' && envBool('AUTH_REQUIRED') !== null) {
            apiResponse("error", "Login is set by the AUTH_REQUIRED environment variable.");
        }

        $newValue = cleanConfigValue($config[$key], $newValue);

        if ($newValue === $config[$key]['value']) {
            apiResponse("success", "No change.");
        }
        if ($key === 'auth_required' && $newValue === true && userCount() === 0) {
            apiResponse("error", "Create an admin account first (Users, below).");
        }

        $config[$key]['value'] = $newValue;
        // The page applies theme changes live from this, without a reload.
        $theme = themeSetup(array_map(function ($s) { return is_array($s) ? ($s['value'] ?? null) : null; }, $config));
        $payload = ["value" => $newValue, "theme" => ["attrs" => $theme["attrs"], "css" => $theme["css"]]];

        if ($perUser) {
            $mine = userJson(currentUser(), 'settings');
            $mine[$key] = $newValue;
            userUpdate(currentUserId(), ['settings' => json_encode($mine, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
            apiResponse("success", "Saved for your account.", $payload);
        }

        $global = GLOBAL_CONFIG;
        $global[$key]['value'] = $newValue;
        saveConfig($global, $payload);
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
        // A song key (older pages sent it URL-encoded)
        $filePath = songPath($file) ?? songPath(urldecode($file));
        if ($filePath === null || !is_file($filePath)) {
            apiResponse("error", "That song isn't in the library.");
        }

        $deletedDir = 'deleted';
        if (!is_dir($deletedDir)) {
            @mkdir($deletedDir, 0755, true);
        }
        if (!is_dir($deletedDir) || !is_writable($deletedDir)) {
            apiResponse("error", "The directory <code>" . htmlspecialchars($deletedDir, ENT_QUOTES, 'UTF-8') . "</code> is not writable.");
        }
        // Songs from different folders can share a name
        $name   = basename($filePath);
        $target = $deletedDir . "/" . $name;
        for ($n = 2; file_exists($target); $n++) {
            $target = $deletedDir . "/" . pathinfo($name, PATHINFO_FILENAME) . " ($n)." . pathinfo($name, PATHINFO_EXTENSION);
        }
        if (!@rename($filePath, $target)) {
            apiResponse("error", "The file could not be moved. Check that its folder is writable by the web server.");
        }
        apiResponse("success", "The file " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . " has been removed.");
    }

    /* ─────────────────────────── FUNCTION: listSongs ────────────────────────── */
    function listSongs(): void {
        $songs = [];
        $i = 0;
        foreach (musicLibrary() as $key => $filePath) {
            $enc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            $songs[] = [
                "id"       => $i++,
                "name"     => $key,
                "filename" => $key,
                "duration" => getDuration($filePath),
                "size"     => filesize($filePath),
                "date"     => date("Y-m-d H:i:s", filemtime($filePath)),
                "download" => "<a href='javascript:void(0);' class='link-success downloadBtn' data-filename='$enc'>".icon("download")."</a>",
                "delete"   => "<a href='javascript:void(0);' class='link-danger deleteBtn' data-filename='$enc'>".icon("trash-2")."</a>",
            ];
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

        // Uploads go to the first music folder
        $folder = uploadFolder();
        if ($folder === null || !is_writable($folder['real'])) {
            $result = ["status" => "error", "response" => $folder === null
                ? "None of the music folders exist. Check them in Settings → Library."
                : "The music folder <code>" . htmlspecialchars($folder['path'], ENT_QUOTES, 'UTF-8') . "</code> is not writable by the web server.", "data" => []];
            if ($returnResult) return $result;
            apiResponse("error", $result["response"]);
        }

        $targetDir  = $folder['real'];
        
        // Sanitize filename - prevent path traversal and dangerous characters
        $filename = basename($file["name"]);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate file extension
        $allowedTypes = audioTypes();
        if (!in_array($fileExtension, $allowedTypes, true)) {
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
                'oga' => ['audio/ogg', 'application/ogg'],
                'opus' => ['audio/opus', 'audio/ogg'],
                'wav' => ['audio/wav', 'audio/wave', 'audio/x-wav'],
                'flac' => ['audio/flac', 'audio/x-flac'],
                'm4a' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a'],
                'aac' => ['audio/aac', 'audio/x-aac', 'audio/aacp'],
                'webm' => ['audio/webm', 'video/webm'],
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
            $result = ["status" => "error", "response" => "Sorry, file <code>".htmlspecialchars($filename, ENT_QUOTES, 'UTF-8')."</code> already exists.", "data" => []];
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

?><?php
    /* ───────────────────────── FUNCTION: userMenusHtml ──────────────────────── */
    // Header buttons for signed-in users: the Activity panel and the user menu.
    function userMenusHtml(array $me): string {
        $esc  = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
        $name = userDisplayName($me);
        $admin = $me['role'] === 'admin';
        return '
      <div class="dropdown activityMenu">
        <button type="button" class="btn btn-sm btn-pill btn-outline-primary activityBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="What others are playing" aria-label="What others are playing">
          '.icon("radio").'
          <span class="badge bg-green text-green-fg activityBadge ms-1" style="display:none;"></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end activityPanel">
          <div class="activityHead">Listening now</div>
          <div class="activityList"><div class="activityEmpty text-secondary">Loading…</div></div>
          <div class="activityFoot text-secondary small"></div>
        </div>
      </div>
      <div class="dropdown userMenu">
        <button type="button" class="btn btn-sm btn-pill btn-ghost-secondary userMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Signed in as '.$esc($me['username']).'">
          <span class="userAvatar" aria-hidden="true">'.$esc(mb_strtoupper(mb_substr($name, 0, 1))).'</span>
          <span class="d-none d-lg-inline userMenuName">'.$esc($name).'</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <span class="dropdown-header">'.$esc($me['username']).($admin ? ' · admin' : '').'</span>
          <a href="#settings-account" class="dropdown-item openSettingsSection" data-section="settings-account">'.icon("user-cog", 1, 0).' Account</a>'.($admin ? '
          <a href="#settings-users" class="dropdown-item openSettingsSection" data-section="settings-users">'.icon("users", 1, 0).' Users</a>' : '').'
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item logoutBtn">'.icon("log-out", 1, 0).' Sign out</a>
        </div>
      </div>';
    }
?>
