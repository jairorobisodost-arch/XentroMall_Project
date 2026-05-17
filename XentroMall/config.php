<?php
/**
 * PRODUCTION Database Configuration for InfinityFree
 * 
 * INSTRUCTIONS:
 * 1. Get your MySQL password from InfinityFree dashboard
 * 2. Update line 12 with your actual password
 * 3. Rename this file to config.php when uploading to server
 * 4. Keep original config.php as config.local.php for local development
 */

// InfinityFree Database Credentials
$host = 'localhost';
$db = 'xentromall';
$user = 'root';
$pass = ''; // MySQL Password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}
catch (\PDOException $e) {
    // In production, log error instead of displaying it
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}

// Also create mysqli connection for files that use it (like stall_page.php)
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("MySQLi Connection Error: " . $conn->connect_error);
    die("Database connection failed. Please contact administrator.");
}

$conn->set_charset($charset);

// Add BASE_URL detection for subdirectory as well
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    // If running from subdirectory, dirname will reflect it
    $dir = str_replace('\\', '/', dirname($script_name));
    $base_dir = ($dir === '/') ? '/' : $dir . '/';
    define('BASE_URL', $protocol . "://" . $host . $base_dir);
}
?>
