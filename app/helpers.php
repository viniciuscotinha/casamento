<?php
declare(strict_types=1);

function app_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];

    if ($key === null || $key === '') {
        return $config;
    }

    $value = $config;

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function app_is_configured(): bool
{
    $db = (array) app_config('db', []);
    $required = ['host', 'database', 'username', 'password'];

    foreach ($required as $field) {
        $value = trim((string) ($db[$field] ?? ''));

        if ($value === '' || str_contains($value, 'CHANGE_ME')) {
            return false;
        }
    }

    return true;
}

function url(string $path = '/'): string
{
    $baseUrl = rtrim((string) app_config('base_url', ''), '/');

    if ($path === '') {
        return $baseUrl !== '' ? $baseUrl : '/';
    }

    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }

    return $baseUrl !== '' ? $baseUrl . $path : $path;
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('~^https?://~i', $path) ? $path : url($path)));
    exit;
}

function e(null|string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0');

    if (str_contains($ip, ',')) {
        $parts = explode(',', $ip);
        $ip = trim($parts[0]);
    }

    return substr($ip, 0, 45);
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function consume_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return is_array($flashes) ? $flashes : [];
}

function remember_old_input(array $data): void
{
    $_SESSION['old_input'] = $data;
}

function old_input(string $key, string $default = ''): string
{
    $value = $_SESSION['old_input'][$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
}

function clear_old_input(): void
{
    unset($_SESSION['old_input']);
}

function send_noindex_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    header('Referrer-Policy: strict-origin-when-cross-origin', true);
    header('X-Frame-Options: SAMEORIGIN', true);
}

function generate_public_token(int $length = 20): string
{
    $length = max(16, $length);
    $token = '';

    while (strlen($token) < $length) {
        $token .= rtrim(strtr(base64_encode(random_bytes($length)), '+/', '-_'), '=');
    }

    return substr($token, 0, $length);
}

function hash_public_token(string $token): string
{
    return hash('sha256', $token);
}

function token_preview(string $token): string
{
    if (strlen($token) <= 8) {
        return $token;
    }

    return substr($token, 0, 4) . '...' . substr($token, -4);
}

function family_public_url(string $identifier): string
{
    return url('/c/' . rawurlencode($identifier));
}

function normalize_public_slug(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($asciiValue) && $asciiValue !== '') {
            $value = $asciiValue;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    $value = preg_replace('/-+/', '-', $value) ?? '';
    $value = trim($value, '-_');

    return substr($value, 0, 24);
}

function public_slug_is_valid(string $slug): bool
{
    $length = strlen($slug);

    if ($length < 3 || $length > 24) {
        return false;
    }

    return preg_match('/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/', $slug) === 1;
}

function generate_public_slug(int $length = 6): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $length = max(4, min($length, 12));
    $slug = '';
    $maxIndex = strlen($alphabet) - 1;

    for ($index = 0; $index < $length; $index++) {
        $slug .= $alphabet[random_int(0, $maxIndex)];
    }

    return $slug;
}

function extract_public_token(string $input): ?string
{
    $input = trim($input);

    if ($input === '') {
        return null;
    }

    if (preg_match('~^https?://~i', $input) === 1) {
        $path = (string) parse_url($input, PHP_URL_PATH);

        if ($path !== '' && preg_match('~/c/([A-Za-z0-9_-]{10,128})/?$~', $path, $matches) === 1) {
            return $matches[1];
        }
    }

    return preg_match('/^[A-Za-z0-9_-]{10,128}$/', $input) === 1 ? $input : null;
}

function token_cipher_secrets(): array
{
    $configuredKey = trim((string) app_config('app_key', ''));
    $secrets = [];

    if ($configuredKey !== '' && !str_contains($configuredKey, 'CHANGE_ME')) {
        $secrets[] = hash('sha256', 'app-key|' . $configuredKey, true);
    }

    $secrets[] = hash(
        'sha256',
        implode('|', [
            (string) app_config('base_url', ''),
            (string) app_config('session_name', 'casamento_admin'),
            (string) app_config('db.database', ''),
            (string) app_config('db.username', ''),
            (string) app_config('db.password', ''),
        ]),
        true
    );

    $uniqueSecrets = [];

    foreach ($secrets as $secret) {
        $uniqueSecrets[base64_encode($secret)] = $secret;
    }

    return array_values($uniqueSecrets);
}

function encrypt_public_token(string $token): ?string
{
    if ($token === '' || !function_exists('openssl_encrypt')) {
        return null;
    }

    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);

    if (!is_int($ivLength) || $ivLength < 1) {
        return null;
    }

    $secrets = token_cipher_secrets();
    $secret = $secrets[0] ?? null;

    if (!is_string($secret) || $secret === '') {
        return null;
    }

    $iv = random_bytes($ivLength);
    $encrypted = openssl_encrypt($token, $cipher, $secret, OPENSSL_RAW_DATA, $iv);

    if (!is_string($encrypted) || $encrypted === '') {
        return null;
    }

    return base64_encode($iv . $encrypted);
}

function decrypt_public_token(?string $encryptedToken): ?string
{
    $encryptedToken = trim((string) $encryptedToken);

    if ($encryptedToken === '' || !function_exists('openssl_decrypt')) {
        return null;
    }

    $decoded = base64_decode($encryptedToken, true);

    if (!is_string($decoded) || $decoded === '') {
        return null;
    }

    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);

    if (!is_int($ivLength) || strlen($decoded) <= $ivLength) {
        return null;
    }

    $iv = substr($decoded, 0, $ivLength);
    $ciphertext = substr($decoded, $ivLength);

    foreach (token_cipher_secrets() as $secret) {
        $token = openssl_decrypt($ciphertext, $cipher, $secret, OPENSSL_RAW_DATA, $iv);

        if (!is_string($token) || $token === '') {
            continue;
        }

        if (preg_match('/^[A-Za-z0-9_-]{10,128}$/', $token) === 1) {
            return $token;
        }
    }

    return null;
}

function family_public_identifier(array $familyRow): ?string
{
    $publicSlug = normalize_public_slug((string) ($familyRow['public_slug'] ?? ''));

    if ($publicSlug !== '' && public_slug_is_valid($publicSlug)) {
        return $publicSlug;
    }

    return decrypt_public_token((string) ($familyRow['token_encrypted'] ?? ''));
}

function family_link_from_row(array $familyRow): ?string
{
    $identifier = family_public_identifier($familyRow);

    return $identifier !== null ? family_public_url($identifier) : null;
}

function normalized_full_name(array $row): string
{
    return trim((string) ($row['nome'] ?? '') . ' ' . (string) ($row['sobrenome'] ?? ''));
}

function format_datetime(?string $value, string $fallback = '-'): string
{
    if ($value === null || trim($value) === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $fallback;
    }

    return date('d/m/Y H:i', $timestamp);
}

function public_gate_mode(): string
{
    $mode = (string) app_config('public_gate_mode', 'none');

    return in_array($mode, ['none', 'phone_last4', 'surname'], true) ? $mode : 'none';
}

function family_is_verified(int $familyId): bool
{
    return !empty($_SESSION['verified_families'][$familyId]);
}

function mark_family_verified(int $familyId): void
{
    $_SESSION['verified_families'][$familyId] = time();
}

function audit_log(
    string $origin,
    string $action,
    string $entity,
    ?int $entityId = null,
    ?int $familyId = null,
    ?int $guestId = null,
    ?array $payload = null
): void {
    try {
        db_execute(
            'INSERT INTO auditoria (
                admin_user_id, origem, acao, entidade, entidade_id, familia_id, convidado_id,
                ip_address, user_agent, payload_text
            ) VALUES (
                :admin_user_id, :origem, :acao, :entidade, :entidade_id, :familia_id, :convidado_id,
                :ip_address, :user_agent, :payload_text
            )',
            [
                'admin_user_id' => current_admin()['id'] ?? null,
                'origem' => $origin,
                'acao' => $action,
                'entidade' => $entity,
                'entidade_id' => $entityId,
                'familia_id' => $familyId,
                'convidado_id' => $guestId,
                'ip_address' => client_ip(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'payload_text' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    } catch (Throwable) {
        return;
    }
}
