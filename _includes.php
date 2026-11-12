<?php

    session_start();

    /* ───────────────────────────── CSRF Protection ──────────────────────────── */
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    /* ───────────────────────────────── Composer ─────────────────────────────── */
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
    # config.default.php defines every setting (name, type, options, default value).
    # config.local.php (or config.php) only overrides values; metadata always comes
    # from the defaults, so new settings and options appear without a migration.
    $config = [];
    include('config.default.php');
    $defaultConfig = $config;

    if (file_exists('config.local.php')) {
        $configFile = 'config.local.php';
    } elseif (file_exists('config.php')) {
        $configFile = 'config.php';
    } else {
        die(json_encode("The configuration file is missing."));
    }
    $config = [];
    include($configFile);
    $activeConfig = is_array($config) ? $config : [];

    $config = [];
    foreach ($defaultConfig as $key => $setting) {
        if (isset($activeConfig[$key]) && is_array($activeConfig[$key]) && array_key_exists('value', $activeConfig[$key])) {
            $setting['value'] = $activeConfig[$key]['value'];
        }
        $config[$key] = $setting;
    }
    foreach ($activeConfig as $key => $setting) {
        if (!isset($config[$key])) {
            $config[$key] = $setting;
        }
    }

    # Save the configuration to a constant
    define('CONFIG', $config);
    define('ENV', $_ENV);
    define('CONFIG_FILE', $configFile);
    define('AUDIO_PATH', CONFIG["audio_path"]["value"]);

    /* ──────────────────────────────── Functions ─────────────────────────────── */
    include_once('functions.php');
    include_once('sessions.php');
    include_once('playlists.php');
    include_once('lyrics.php');
    include_once('metadata.php');
    include_once('songedit.php');
    include_once('metajob.php');
?>