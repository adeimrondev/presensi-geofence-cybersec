<?php
// Sembunyikan error mentah PHP dari layar browser (Keamanan Information Disclosure)
error_reporting(0);
ini_set('display_errors', 0);

// Setel zona waktu default ke Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

// config/database.php - Secure PDO Connection & Session Hardening
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_path', '/');
    session_start();
}

// Membaca file .env dari root folder
function loadEnvironment($file = null) {
    if ($file === null) {
        $file = dirname(__DIR__) . '/.env';
    }
    if (!file_exists($file)) return;
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $val) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($val);
        }
    }
}
loadEnvironment();

$db_host = $_ENV['DB_HOST'] ?? 'localhost';
$db_port = $_ENV['DB_PORT'] ?? '3306';
$db_name = $_ENV['DB_NAME'] ?? 'presensi_db';
$db_user = $_ENV['DB_USER'] ?? 'root';
$db_pass = $_ENV['DB_PASS'] ?? '';

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed. Please contact administrator.");
}