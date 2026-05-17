<?php
require 'config.php';

try {
    // Check if bill_image column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM tenant_expenses LIKE 'bill_image'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE tenant_expenses ADD COLUMN bill_image VARCHAR(255) DEFAULT NULL AFTER electric_bill_image");
        echo "Added bill_image column.\n";
    } else {
        echo "bill_image column already exists.\n";
    }

    // Migrate existing data: copy water_bill_image to bill_image where bill_image is null
    $pdo->exec("UPDATE tenant_expenses SET bill_image = COALESCE(water_bill_image, electric_bill_image) WHERE bill_image IS NULL AND (water_bill_image IS NOT NULL OR electric_bill_image IS NOT NULL)");
    echo "Migrated existing bill images.\n";

    echo "Database migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
}
?>
