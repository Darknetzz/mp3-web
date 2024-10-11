<?php

    /* ───────────────────────────────── Composer ──────────────────────────────── */
    if (!file_exists('vendor/autoload.php')) {
        die("The Composer autoload file is missing.");
    }
    
    require __DIR__ . '/vendor/autoload.php';
    $env      = 'dev';
    $version  = Null;
    
    use Dotenv\Dotenv;
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $env     = getenv('ENV');
    $version = getenv('VERSION');

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
    define('ENV', $env);
    define('VERSION', $version);

    /* ──────────────────────────────── Functions ─────────────────────────────── */
    include_once('functions.php');
?>