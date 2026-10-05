<?php
session_set_cookie_params([
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$host = 'localhost';
$database = 'blog_site';
$configuredUsername = getenv('BLOG_DB_USER');
$configuredPassword = getenv('BLOG_DB_PASSWORD');

// XAMPP's default local MySQL account is root with no password. Configure
// BLOG_DB_USER and BLOG_DB_PASSWORD for any non-local or secured deployment.
$username = $configuredUsername === false || $configuredUsername === ''
    ? 'root'
    : $configuredUsername;
$password = $configuredPassword === false ? '' : $configuredPassword;

$dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed.');
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfToken(): string
{
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token)
        && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}