<?php
require 'config.php';

echo "<h2>🛠️ Database Schema Repair Tool</h2>";

try {
    // 1. Fix unified_renewal_requests
    echo "Processing <b>unified_renewal_requests</b>...<br>";
    
    // Check if ID is already primary key
    $stmt = $pdo->query("SHOW KEYS FROM unified_renewal_requests WHERE Key_name = 'PRIMARY'");
    $hasPrimary = $stmt->fetch();

    if (!$hasPrimary) {
        // First, ensure the ID column is NOT NULL
        $pdo->exec("ALTER TABLE unified_renewal_requests MODIFY id INT NOT NULL");
        
        // Add Primary Key
        $pdo->exec("ALTER TABLE unified_renewal_requests ADD PRIMARY KEY (id)");
        echo "✅ Added PRIMARY KEY to 'id'.<br>";
        
        // Add Auto Increment
        $pdo->exec("ALTER TABLE unified_renewal_requests MODIFY id INT NOT NULL AUTO_INCREMENT");
        echo "✅ Added AUTO_INCREMENT to 'id'.<br>";
    } else {
        echo "ℹ️ 'id' already has PRIMARY KEY. Checking for AUTO_INCREMENT...<br>";
        
        // Check for auto_increment
        $stmtDesc = $pdo->query("DESCRIBE unified_renewal_requests");
        while ($col = $stmtDesc->fetch()) {
            if ($col['Field'] === 'id' && strpos($col['Extra'], 'auto_increment') === false) {
                $pdo->exec("ALTER TABLE unified_renewal_requests MODIFY id INT NOT NULL AUTO_INCREMENT");
                echo "✅ Fixed missing AUTO_INCREMENT on 'id'.<br>";
            }
        }
    }

    echo "<h3>🎉 Schema Fix Complete!</h3>";
    echo "<p>Paki-refresh ang iyong <b>phpMyAdmin</b>. Lalabas na dapat ang mga **Edit** buttons sa table.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    
    // If it's a "Multiple primary key defined" error, just ignore
    if (strpos($e->getMessage(), 'Multiple primary key defined') !== false) {
        echo "<p style='color: green;'>✅ Table already has a unique key setup.</p>";
    }
}
?>
