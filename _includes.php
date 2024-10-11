<?php

    /* ──────────────────────────────────── Reload ─────────────────────────────── */
    if (isset($_GET['reload'])) {
    ?>
        <script>
            localStorage.clear();
            var url = new URL(window.location.href);
            url.search = '';
            window.history.replaceState({}, document.title, url.toString());
            setTimeout(function() {
            location.reload();
            }, 2000);
        </script>
    <?php 
        exit("Reloading page...");
    }

    /* ───────────────────────────────── Composer ──────────────────────────────── */
    $composer = False;
    if (file_exists('vendor/autoload.php')) {
        $composer = True;
        require __DIR__ . '/vendor/autoload.php';
    }

    /* ────────────────────────────── Configuration ───────────────────────────── */
    if (file_exists('config.local.php')) {
        $configFile = 'config.local.php';
        include_once($configFile);
    } elseif (file_exists('config.php')) {
        $configFile = 'config.php';
        include_once($configFile);
    } else {
        die(json_encode("The configuration file is missing."));
    }

    # Save the configuration to a constant
    define('CONFIG', $config);
    define('CONFIG_FILE', $configFile);
    define('AUDIO_PATH', CONFIG["audio_path"]["value"]);
    define('COMPOSER', $composer);
    define('ENV', (!empty(CONFIG["env"]["value"]) ? CONFIG["env"]["value"] : 'dev'));
    define('VERSION', (!empty(file_get_contents('VERSION')) ? file_get_contents('VERSION') : ENV));

    /* ──────────────────────────────── Functions ─────────────────────────────── */
    include_once('functions.php');
?>