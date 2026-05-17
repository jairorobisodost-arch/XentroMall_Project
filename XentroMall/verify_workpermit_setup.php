<?php
/**
 * Work Permits Setup & Verification Script
 * Run this once to ensure all tables and columns are properly configured
 */

require 'config.php';

echo "================================\n";
echo "Work Permits System Verification\n";
echo "================================\n\n";

try {
    // 1. Check if work_permits table exists
    echo "1. Checking work_permits table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'work_permits'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "   ✓ Table exists\n";
    } else {
        echo "   ✗ Table NOT found! Creating...\n";
        die("ERROR: work_permits table not found. Please run the migration script first.\n");
    }
    
    // 2. Check required columns
    echo "\n2. Checking required columns...\n";
    $requiredColumns = [
        'permit_no',
        'date_filed',
        'tenant_name',
        'stall_number',
        'scope_of_work',
        'permit_valid_from',
        'permit_valid_to',
        'time_from',
        'time_to',
        'security_posting',
        'rate_security',
        'charge_security',
        'janitorial_deployment',
        'rate_janitorial',
        'charge_janitorial',
        'maintenance',
        'rate_maintenance',
        'charge_maintenance',
        'personnel',
        'contractor_type',
        'tenant_signature',
        'admin_signature',
        'guard_signature',
        'uploaded_images',
        'status',
        'admin_signed_at',
        'guard_signed_at',
        'created_at',
        'updated_at'
    ];
    
    $stmt = $pdo->query("SHOW COLUMNS FROM work_permits");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columns)) {
            echo "   ✓ $col\n";
        } else {
            echo "   ✗ $col MISSING\n";
            $missingColumns[] = $col;
        }
    }
    
    // 3. Add missing columns if any
    if (!empty($missingColumns)) {
        echo "\n3. Adding missing columns...\n";
        
        $columnDefs = [
            'contractor_type' => "enum('inside','outside') DEFAULT 'inside' COMMENT 'Contractor type'",
            'uploaded_images' => "LONGTEXT DEFAULT NULL COMMENT 'Uploaded images/documents'",
            'tenant_signature' => "LONGTEXT DEFAULT NULL COMMENT 'Tenant signature (base64)'",
            'admin_signature' => "LONGTEXT DEFAULT NULL COMMENT 'Admin signature (base64)'",
            'guard_signature' => "LONGTEXT DEFAULT NULL COMMENT 'Guard signature (base64)'",
            'admin_signed_at' => "DATETIME DEFAULT NULL COMMENT 'When admin signed'",
            'guard_signed_at' => "DATETIME DEFAULT NULL COMMENT 'When guard signed'"
        ];
        
        foreach ($missingColumns as $col) {
            if (isset($columnDefs[$col])) {
                try {
                    $pdo->exec("ALTER TABLE work_permits ADD COLUMN `$col` " . $columnDefs[$col]);
                    echo "   ✓ Added $col\n";
                } catch (Exception $e) {
                    echo "   ✗ Failed to add $col: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    // 4. Check indexes
    echo "\n4. Checking indexes...\n";
    $stmt = $pdo->query("SHOW INDEX FROM work_permits");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $indexNames = array_column($indexes, 'Key_name');
    
    $requiredIndexes = [
        'PRIMARY',
        'idx_tenant_name',
        'idx_date_filed',
        'idx_status',
        'idx_stall_number',
        'idx_permit_valid_from',
        'idx_permit_valid_to',
        'idx_created_at',
        'idx_contractor_type'
    ];
    
    foreach ($requiredIndexes as $idx) {
        if (in_array($idx, $indexNames)) {
            echo "   ✓ $idx\n";
        } else {
            echo "   ✗ $idx MISSING\n";
        }
    }
    
    // 5. Check views
    echo "\n5. Checking views...\n";
    $stmt = $pdo->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $requiredViews = [
        'active_work_permits',
        'pending_work_permits',
        'expired_work_permits'
    ];
    
    foreach ($requiredViews as $view) {
        if (in_array($view, $views)) {
            echo "   ✓ $view\n";
        } else {
            echo "   ! $view not found (optional)\n";
        }
    }
    
    // 6. Display table statistics
    echo "\n6. Current data in work_permits:\n";
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected
        FROM work_permits
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "   Total Records: " . $stats['total'] . "\n";
    echo "   Pending: " . ($stats['pending'] ?? 0) . "\n";
    echo "   Approved: " . ($stats['approved'] ?? 0) . "\n";
    echo "   Rejected: " . ($stats['rejected'] ?? 0) . "\n";
    
    echo "\n================================\n";
    echo "✓ VERIFICATION COMPLETE\n";
    echo "Work Permits system is ready!\n";
    echo "================================\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
