<?php
    /* ───────────────────────────────── Composer ──────────────────────────────── */
    $composer = False;
    if (file_exists('vendor/autoload.php')) {
        $composer = True;
        require __DIR__ . '/vendor/autoload.php';
    }

    /* ────────────────────────────── Configuration ───────────────────────────── */
    if (file_exists('config.local.php')) {
        include_once('config.local.php');
    } elseif (file_exists('config.php')) {
        include_once('config.php');
    } else {
        die(json_encode("The configuration file is missing."));
    }

    /* ──────────────────────────────── Functions ─────────────────────────────── */
    include_once('functions.php');
?>