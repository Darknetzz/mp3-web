<?php

    session_start();

    /* ───────────────────────────────── Composer ──────────────────────────────── */
    if (!file_exists('vendor/autoload.php')) {
        die("The Composer autoload file is missing.");
    }

    require __DIR__ . '/vendor/autoload.php';

    if (!file_exists('.env')) {
        die("The .env file is missing.");
    }

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $env     = $_ENV['ENV'];
    $version = $_ENV['VERSION'];

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
    define('ENV', $_ENV);
    define('CONFIG_FILE', $configFile);
    define('AUDIO_PATH', CONFIG["audio_path"]["value"]);

    /* ──────────────────────────────── Functions ─────────────────────────────── */
    include_once('functions.php');
?>