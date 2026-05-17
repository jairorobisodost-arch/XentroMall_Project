<?php
require 'config.php';

echo "<h3>Updating Database Structure...</h3>";

try {
    // Check if business_type column exists
    $stmt = $pdo->prepare('DESCRIBE unified_renewal_requests');
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('business_type', $columns)) {
        echo "✅ business_type column already exists<br>";
    } else {
        echo "➕ Adding business_type column... ";
        $pdo->exec('ALTER TABLE unified_renewal_requests ADD COLUMN business_type VARCHAR(50) DEFAULT "unknown" AFTER tin_number');
        echo "✅ business_type column added<br>";
    }
    
    // Also add secretary_certificate column if it doesn't exist
    if (!in_array('secretary_certificate', $columns)) {
        echo "➕ Adding secretary_certificate column... ";
        $pdo->exec('ALTER TABLE unified_renewal_requests ADD COLUMN secretary_certificate VARCHAR(255) NULL AFTER financial_statement');
        echo "✅ secretary_certificate column added<br>";
    } else {
        echo "✅ secretary_certificate column already exists<br>";
    }
    
    echo "<h3>✅ Database update completed successfully!</h3>";
    echo "<p><a href='unified_renewal_form.php'>Go to Renewal Form</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
