<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Fetch all notifications for the user, including read ones
    $stmt = $pdo->prepare("
        SELECT id, message, 'notif' AS type, created_at, category 
        FROM notifications 
        WHERE user_id = ?
        UNION ALL
        SELECT CONCAT('ann_', id) AS id, CONCAT(title, ': ', description) AS message, 'announcement' AS type, created_at, category 
        FROM announcements 
        WHERE date >= CURDATE()
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($notifications);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
?>
