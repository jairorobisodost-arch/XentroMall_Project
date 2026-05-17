<?php
require 'config.php';

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    die("Please login first");
}

try {
    // Get tenant ID from user
    $stmtTenant = $pdo->prepare("SELECT t.id FROM tenants t JOIN users u ON u.email = t.email WHERE u.id = ?");
    $stmtTenant->execute([$userId]);
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    if ($tenant) {
        $tenantId = $tenant['id'];
        
        // Reset contract to normal 1-year period from today
        $today = date('Y-m-d');
        $newStartDate = $today;
        $newEndDate = date('Y-m-d', strtotime('+1 year'));
        
        // Update contract
        $stmtUpdate = $pdo->prepare("UPDATE tenant_lease_dates 
                                   SET lease_start_date = ?, lease_expiration_date = ?, status = 'active' 
                                   WHERE tenant_id = ?");
        $stmtUpdate->execute([$newStartDate, $newEndDate, $tenantId]);
        
        echo "<h2>✅ Contract Reset to Normal!</h2>";
        echo "<div style='background: #dcfce7; border: 2px solid #16a34a; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3>📅 Reset Contract Details:</h3>";
        echo "<p><strong>Contract Start:</strong> " . date('F j, Y', strtotime($newStartDate)) . "</p>";
        echo "<p><strong>Contract Expiration:</strong> " . date('F j, Y', strtotime($newEndDate)) . "</p>";
        echo "<p><strong>Contract Duration:</strong> 12 months</p>";
        echo "<p><strong>Status:</strong> <span style='color: #16a34a; font-weight: bold;'>✅ ACTIVE</span></p>";
        echo "</div>";
        
        echo "<h3>🔄 What Changed:</h3>";
        echo "<ul>";
        echo "<li>❌ Red expiration warning removed</li>";
        echo "<li>⏰ Renewal countdown reset to 365 days</li>";
        echo "<li>🔄 Renewal button hidden (not available yet)</li>";
        echo "<li>📊 Contract progress reset to beginning</li>";
        echo "</ul>";
        
        echo "<p><a href='tenant_dashboard.php' style='background: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Go to Dashboard</a></p>";
        
    } else {
        echo "<h2>❌ Tenant not found</h2>";
        echo "<p>No tenant record found for your account.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
