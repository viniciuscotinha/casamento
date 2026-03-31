<?php
declare(strict_types=1);

function current_admin(): ?array
{
    $admin = $_SESSION['admin_user'] ?? null;

    return is_array($admin) ? $admin : null;
}

function is_admin_logged_in(): bool
{
    return !empty(current_admin()['id']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        flash('error', 'Faca login para acessar a area administrativa.');
        redirect('/admin/login');
    }
}

function admin_logout(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

function admin_has_users(): bool
{
    return (int) db_value('SELECT COUNT(*) FROM admin_users') > 0;
}

function login_attempts_exceeded(string $login, string $ipAddress): bool
{
    $maxAttempts = (int) app_config('admin.max_login_attempts', 5);
    $windowMinutes = (int) app_config('admin.login_window_minutes', 15);

    $windowStart = (new DateTimeImmutable('-' . $windowMinutes . ' minutes'))->format('Y-m-d H:i:s');
    $count = (int) db_value(
        'SELECT COUNT(*) FROM admin_login_attempts
         WHERE login = :login
           AND ip_address = :ip_address
           AND was_success = 0
           AND attempted_at >= :window_start',
        [
            'login' => $login,
            'ip_address' => $ipAddress,
            'window_start' => $windowStart,
        ]
    );

    return $count >= $maxAttempts;
}

function register_login_attempt(string $login, string $ipAddress, bool $wasSuccess): void
{
    db_execute(
        'INSERT INTO admin_login_attempts (login, ip_address, user_agent, was_success)
         VALUES (:login, :ip_address, :user_agent, :was_success)',
        [
            'login' => $login,
            'ip_address' => $ipAddress,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'was_success' => $wasSuccess ? 1 : 0,
        ]
    );
}

function attempt_admin_login(string $login, string $password): array
{
    $login = trim($login);
    $password = trim($password);
    $ipAddress = client_ip();

    if ($login === '' || $password === '') {
        return [
            'ok' => false,
            'message' => 'Informe login e senha.',
        ];
    }

    if (login_attempts_exceeded($login, $ipAddress)) {
        return [
            'ok' => false,
            'message' => 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.',
        ];
    }

    $user = db_one(
        'SELECT id, nome, login, password_hash, is_active
         FROM admin_users
         WHERE login = :login
         LIMIT 1',
        ['login' => $login]
    );

    $validUser = $user !== null
        && (int) $user['is_active'] === 1
        && password_verify($password, (string) $user['password_hash']);

    register_login_attempt($login, $ipAddress, $validUser);

    if (!$validUser) {
        audit_log('admin', 'login_failed', 'login', null, null, null, ['login' => $login]);

        return [
            'ok' => false,
            'message' => 'Login ou senha invalidos.',
        ];
    }

    db_execute(
        'UPDATE admin_users
         SET last_login_at = NOW(), last_login_ip = :last_login_ip
         WHERE id = :id',
        [
            'last_login_ip' => $ipAddress,
            'id' => (int) $user['id'],
        ]
    );

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int) $user['id'],
        'nome' => (string) ($user['nome'] ?? ''),
        'login' => (string) $user['login'],
    ];

    audit_log('admin', 'login_success', 'login', (int) $user['id']);

    return [
        'ok' => true,
        'message' => 'Login realizado com sucesso.',
        'user' => $_SESSION['admin_user'],
    ];
}
