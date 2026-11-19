<?php
/**
 * config/database.php
 * Opens ONE shared MySQL connection using PDO (credentials come from .env).
 * All queries in this project use prepared statements.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST'),
        env('DB_PORT', '3306'),
        env('DB_NAME')
    );

    $pdo = new PDO($dsn, env('DB_USER'), env('DB_PASS', ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw errors instead of failing silently
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
    ]);

    // Make MySQL use the same time zone as PHP so dates and expiry times always agree.
    $pdo->exec("SET time_zone = '" . date('P') . "'");

    return $pdo;
}
