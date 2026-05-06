<?php
/**
 * Database Configuration File
 * Library Management System (LMS)
 */

// Database connection settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lms_schema');
define('DB_CHARSET', 'utf8mb4');

// Create database connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Password hashing and verification functions
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword(string $password, string $hash): bool {
    // First try bcrypt verification
    if (password_verify($password, $hash)) {
        return true;
    }
    // Fall back to plain text comparison for legacy passwords
    return ($password === $hash);
}
