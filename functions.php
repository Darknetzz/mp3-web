<?php

    /* ───────────────────────────── FUNCTION: icon ───────────────────────────── */
    function icon($icon = "", $size = 1.5, $margin = 1) {
        return '<i class="bi bi-' . $icon . ' m-' . $margin . '" style="font-size: ' . $size . 'em;"></i>';
    }

    /* ──────────────────────────── FUNCTION: mp3info ─────────────────────────── */
    function getDuration($file) {
        global $config;
        global $composer;
        if (!$composer || !class_exists('wapmorgan\Mp3Info\Mp3Info')) {
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

?>