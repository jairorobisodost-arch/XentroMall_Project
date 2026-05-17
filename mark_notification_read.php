<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationId = $_POST['id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if ($notificationId && $userId) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        http_response_code(200);
        echo "Notification marked as read.";
    } else {
        http_response_code(400);
        echo "Invalid request.";
    }
} else {
    http_response_code(405);
    echo "Method not allowed.";
}
?>
