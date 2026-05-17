<?php
require 'config.php';

try {
    // Check if columns exist first
    $stmt = $pdo->query("SHOW COLUMNS FROM tenant_expenses LIKE 'water_bill_image'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE tenant_expenses ADD COLUMN water_bill_image VARCHAR(255) DEFAULT NULL");
        echo "Added water_bill_image column.\n";
    } else {
        echo "water_bill_image column already exists.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM tenant_expenses LIKE 'electric_bill_image'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE tenant_expenses ADD COLUMN electric_bill_image VARCHAR(255) DEFAULT NULL");
        echo "Added electric_bill_image column.\n";
    } else {
        echo "electric_bill_image column already exists.\n";
    }

    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?>
