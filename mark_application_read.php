<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$appId = intval($_POST['id'] ?? 0);

if (!$appId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}

try {
    // Add admin_viewed column if it doesn't exist yet
    $pdo->exec("ALTER TABLE tenant_details ADD COLUMN IF NOT EXISTS admin_viewed TINYINT(1) NOT NULL DEFAULT 0");

    $stmt = $pdo->prepare("UPDATE tenant_details SET admin_viewed = 1 WHERE id = ?");
    $stmt->execute([$appId]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("mark_application_read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
