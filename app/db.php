<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!app_is_configured()) {
        throw new RuntimeException('Preencha o arquivo config/env.php com os dados reais do banco.');
    }

    $host = (string) app_config('db.host');
    $port = (int) app_config('db.port', 3306);
    $database = (string) app_config('db.database');
    $username = (string) app_config('db.username');
    $password = (string) app_config('db.password');
    $charset = (string) app_config('db.charset', 'utf8mb4');

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        throw new RuntimeException(
            'Nao foi possivel conectar ao MySQL. Revise config/env.php e confirme se o schema foi importado.',
            0,
            $exception
        );
    }

    return $pdo;
}

function db_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return is_array($row) ? $row : null;
}

function db_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function db_value(string $sql, array $params = []): mixed
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $value = $statement->fetchColumn();

    return $value === false ? null : $value;
}

function db_execute(string $sql, array $params = []): bool
{
    $statement = db()->prepare($sql);

    return $statement->execute($params);
}

function db_transaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $result = $callback($pdo);
        $pdo->commit();

        return $result;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function db_column_exists(string $table, string $column): bool
{
    static $cache = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $cacheKey = $table . '.' . $column;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $exists = db_value(
        'SELECT 1
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column
         LIMIT 1',
        [
            'schema' => (string) app_config('db.database'),
            'table' => $table,
            'column' => $column,
        ]
    ) !== null;

    $cache[$cacheKey] = $exists;

    return $exists;
}

function db_table_exists(string $table): bool
{
    static $cache = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $exists = db_value(
        'SELECT 1
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table
         LIMIT 1',
        [
            'schema' => (string) app_config('db.database'),
            'table' => $table,
        ]
    ) !== null;

    $cache[$table] = $exists;

    return $exists;
}
