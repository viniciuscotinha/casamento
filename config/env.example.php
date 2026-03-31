<?php

return [
    'app_name' => 'RSVP Casamento',
    'base_url' => 'https://seu-dominio.com.br',
    'app_key' => 'CHANGE_ME_APP_KEY',
    'timezone' => 'America/Sao_Paulo',
    'session_name' => 'casamento_admin',
    'public_gate_mode' => 'none',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'CHANGE_ME_DATABASE',
        'username' => 'CHANGE_ME_USER',
        'password' => 'CHANGE_ME_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'max_login_attempts' => 5,
        'login_window_minutes' => 15,
    ],
];
