<?php
/**
 * Mark all pending applications as viewed by admin.
 * Called via AJAX when the admin dismisses the "New Application" alert banner.
 */
session_start();
require 'config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Mark all unviewed pending applications as viewed
        $stmt = $pdo->prepare("
            UPDATE tenant_details 
            SET admin_viewed = 1 
            WHERE COALESCE(admin_viewed, 0) = 0
            AND ((status != 'approved' AND status != 'declined') OR status IS NULL)
        ");
        $stmt->execute();
        $affected = $stmt->rowCount();

        echo json_encode([
            'success' => true, 
            'message' => "Marked {$affected} application(s) as viewed."
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
