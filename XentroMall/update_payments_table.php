<?php
require 'config.php';

try {
    // Check if admin_remarks column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM payments LIKE 'admin_remarks'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Add admin_remarks column
        $pdo->exec("ALTER TABLE payments ADD COLUMN admin_remarks TEXT NULL AFTER status");
        echo "✅ Successfully added admin_remarks column to payments table.";
    } else {
        echo "ℹ️ admin_remarks column already exists in payments table.";
    }
} catch (Exception $e) {
    echo "❌ Error updating payments table: " . $e->getMessage();
}
?>
