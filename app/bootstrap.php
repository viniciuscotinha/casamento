<?php
declare(strict_types=1);

$configPath = dirname(__DIR__) . '/config/env.php';
$fallbackConfigPath = dirname(__DIR__) . '/config/env.example.php';

$appConfig = file_exists($configPath)
    ? require $configPath
    : require $fallbackConfigPath;

if (!is_array($appConfig)) {
    throw new RuntimeException('Arquivo de configuracao invalido.');
}

$GLOBALS['app_config'] = $appConfig;

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'America/Sao_Paulo'));

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

session_name((string) ($appConfig['session_name'] ?? 'casamento_session'));
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/manual.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/admin_ui.php';
