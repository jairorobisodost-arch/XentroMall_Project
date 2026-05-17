<?php
require 'config.php';

echo "<h2>Updating Work Permits Table Schema</h2>";

try {
    // Add contractor_type column if it doesn't exist
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM work_permits LIKE 'contractor_type'");
    $checkStmt->execute();
    $columnExists = $checkStmt->fetch();
    
    if (!$columnExists) {
        $sql = "ALTER TABLE work_permits ADD COLUMN contractor_type ENUM('inside', 'outside') DEFAULT 'inside' AFTER scope_of_work";
        $pdo->exec($sql);
        echo "<p>✅ Added contractor_type column</p>";
    } else {
        echo "<p>✅ contractor_type column already exists</p>";
    }
    
    // Add is_gate_pass column if it doesn't exist
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM work_permits LIKE 'is_gate_pass'");
    $checkStmt->execute();
    $columnExists = $checkStmt->fetch();
    
    if (!$columnExists) {
        $sql = "ALTER TABLE work_permits ADD COLUMN is_gate_pass BOOLEAN DEFAULT FALSE AFTER contractor_type";
        $pdo->exec($sql);
        echo "<p>✅ Added is_gate_pass column</p>";
    } else {
        echo "<p>✅ is_gate_pass column already exists</p>";
    }
    
    // Add contractor_name column if it doesn't exist
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM work_permits LIKE 'contractor_name'");
    $checkStmt->execute();
    $columnExists = $checkStmt->fetch();
    
    if (!$columnExists) {
        $sql = "ALTER TABLE work_permits ADD COLUMN contractor_name VARCHAR(255) NULL AFTER is_gate_pass";
        $pdo->exec($sql);
        echo "<p>✅ Added contractor_name column</p>";
    } else {
        echo "<p>✅ contractor_name column already exists</p>";
    }
    
    // Add contractor_rate column if it doesn't exist
    $checkStmt = $pdo->prepare("SHOW COLUMNS FROM work_permits LIKE 'contractor_rate'");
    $checkStmt->execute();
    $columnExists = $checkStmt->fetch();
    
    if (!$columnExists) {
        $sql = "ALTER TABLE work_permits ADD COLUMN contractor_rate DECIMAL(10,2) NULL AFTER contractor_name";
        $pdo->exec($sql);
        echo "<p>✅ Added contractor_rate column</p>";
    } else {
        echo "<p>✅ contractor_rate column already exists</p>";
    }
    
    // Show updated table structure
    echo "<h3>Updated work_permits table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE work_permits");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
