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

    # .env is optional: real environment variables (Docker) win over it.
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();
    }
    foreach (['ENV' => 'prod', 'VERSION' => 'dev', 'URL' => 'https://github.com/Darknetzz/mp3-web'] as $envKey => $envDefault) {
        $envValue = getenv($envKey);
        if (!isset($_ENV[$envKey]) || $_ENV[$envKey] === '') {
            $_ENV[$envKey] = ($envValue !== false && $envValue !== '') ? $envValue : $envDefault;
        }
    }

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
        # First run without a config file: settings are written to config.local.php.
        $configFile = 'config.local.php';
    }
    $config = [];
    if (file_exists($configFile)) include($configFile);
    $activeConfig = is_array($config) ? $config : [];
    # Before music_folders there was one folder (audio_path), scanned flat.
    if (isset($activeConfig['audio_path']['value']) && !isset($activeConfig['music_folders'])) {
        $activeConfig['music_folders'] = ['value' => [['path' => (string) $activeConfig['audio_path']['value'], 'recursive' => true]]];
    }
    unset($activeConfig['audio_path']);

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

    # The configuration from the config file; CONFIG adds the user's own settings.
    define('GLOBAL_CONFIG', $config);
    define('ENV', $_ENV);
    define('CONFIG_FILE', $configFile);

    /* ──────────────────────────────── Accounts ──────────────────────────────── */
    include_once('functions.php');
    include_once('auth.php');
    include_once('library.php');
    include_once('sessions.php');
    include_once('playlists.php');
    include_once('activity.php');
    include_once('lyrics.php');
    include_once('metadata.php');
    include_once('songedit.php');
    include_once('metajob.php');
    authBootstrapFromEnv();

    $currentUser = currentUser();
    if ($currentUser && (int) $currentUser['id'] > 0) {
        foreach (userJson($currentUser, 'settings') as $settingKey => $settingValue) {
            if (isset($config[$settingKey]) && configIsUserScope($config[$settingKey])) {
                $config[$settingKey]['value'] = $settingValue;
            }
        }
    }
    define('CONFIG', $config);
?>