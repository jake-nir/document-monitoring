<?php
/**
 * Database connection (PDO) singleton.
 * All queries must use prepared statements.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------
// Database configuration (edit these for your environment)
// ---------------------------------------------------------------------
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'document_monitoring';
const DB_USER = 'root';
const DB_PASS = '';

/**
 * Returns a shared PDO connection.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        log_error('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('Database connection error. Please contact the system administrator.');
    }

    return $pdo;
}
