<?php
require 'config.php'; // Assuming this file contains the PDO $pdo connection

try {
    // Lease expiration reminder: tenants with lease expiring in next 30 days
    $stmt = $pdo->prepare("
        SELECT id, user_id FROM leases 
        WHERE lease_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $expiringLeases = $stmt->fetchAll();

    $notificationStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");

    foreach ($expiringLeases as $lease) {
        $message = "Your lease is expiring soon. Please contact the admin to renew.";
        $notificationStmt->execute([$lease['user_id'], $message]);
    }

    // Unpaid tenant reminder: tenants with unpaid balances
    $stmt = $pdo->prepare("
        SELECT user_id FROM payments 
        WHERE status = 'unpaid'
        GROUP BY user_id
    ");
    $stmt->execute();
    $unpaidTenants = $stmt->fetchAll();

    foreach ($unpaidTenants as $tenant) {
        $message = "You have unpaid rent. Please settle your balance as soon as possible.";
        $notificationStmt->execute([$tenant['user_id'], $message]);
    }

    echo "Reminders sent successfully.";
} catch (Exception $e) {
    echo "Error sending reminders: " . $e->getMessage();
}
?>
