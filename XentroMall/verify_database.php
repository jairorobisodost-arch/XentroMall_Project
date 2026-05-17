<?php
session_start();
require 'config.php';

echo "<h2>Database Column Verification</h2>";
echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>";

try {
    // Get all columns in work_permits table
    $result = $pdo->query("SHOW COLUMNS FROM work_permits");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in work_permits table:\n";
    echo str_repeat("=", 50) . "\n";
    
    foreach ($columns as $col) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    // Check specifically for rejection_remarks
    $found = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'rejection_remarks') {
            $found = true;
            echo "\n✅ rejection_remarks column EXISTS!\n";
            break;
        }
    }
    
    if (!$found) {
        echo "\n❌ rejection_remarks column NOT FOUND!\n";
        echo "\nAttempting to add it now...\n";
        
        // Add the column
        $pdo->exec("ALTER TABLE work_permits ADD COLUMN rejection_remarks VARCHAR(1000) NULL DEFAULT NULL");
        echo "✅ Column added successfully!\n";
        
        // Verify
        $result = $pdo->query("SHOW COLUMNS FROM work_permits WHERE Field='rejection_remarks'");
        $check = $result->fetch();
        if ($check) {
            echo "✅ Verification: Column now exists!\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='admin_work_permits.php' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>← Back to Work Permits</a></p>";
?>
