<?php
require 'config.php';

try {
    echo "<h2>Updating work_permits table...</h2>";
    
    // Add new columns to work_permits table
    $alterQueries = [
        "ALTER TABLE work_permits ADD COLUMN stall_number VARCHAR(50) AFTER tenant_name",
        "ALTER TABLE work_permits ADD COLUMN permit_valid_from DATE AFTER scope_of_work",
        "ALTER TABLE work_permits ADD COLUMN permit_valid_to DATE AFTER permit_valid_from",
        "ALTER TABLE work_permits ADD COLUMN time_from TIME AFTER permit_valid_to",
        "ALTER TABLE work_permits ADD COLUMN time_to TIME AFTER time_from",
        "ALTER TABLE work_permits ADD COLUMN status VARCHAR(50) DEFAULT 'pending' AFTER personnel",
        "ALTER TABLE work_permits ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];
    
    foreach ($alterQueries as $query) {
        try {
            $pdo->exec($query);
            echo "<p style='color: green;'>✓ Successfully executed: " . htmlspecialchars(substr($query, 0, 50)) . "...</p>";
        } catch (PDOException $e) {
            // Check if column already exists
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<p style='color: orange;'>⚠ Column already exists: " . htmlspecialchars(substr($query, 0, 50)) . "...</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
    
    // Update permit_no to be VARCHAR if it's still INT
    try {
        $pdo->exec("ALTER TABLE work_permits MODIFY COLUMN permit_no VARCHAR(50) NOT NULL");
        echo "<p style='color: green;'>✓ Updated permit_no column to VARCHAR</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ permit_no column update: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<h3 style='color: green;'>Database update completed!</h3>";
    echo "<p><a href='admin_work_permits.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Work Permits Management</a></p>";
    echo "<p><a href='admin_dashboard.php' style='background: #059669; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>Back to Admin Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>