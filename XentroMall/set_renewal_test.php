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
        
        // Set contract to expire in 15 days from today (close to renewal)
        $today = date('Y-m-d');
        $newStartDate = date('Y-m-d', strtotime('-11 months')); // Started 11 months ago
        $newEndDate = date('Y-m-d', strtotime('+15 days')); // Expires in 15 days
        
        // Update contract
        $stmtUpdate = $pdo->prepare("UPDATE tenant_lease_dates 
                                   SET lease_start_date = ?, lease_expiration_date = ?, status = 'active' 
                                   WHERE tenant_id = ?");
        $stmtUpdate->execute([$newStartDate, $newEndDate, $tenantId]);
        
        echo "<h2>✅ Contract Set for Renewal Testing!</h2>";
        echo "<div style='background: #fef3c7; border: 2px solid #f59e0b; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3>📅 Contract Details:</h3>";
        echo "<p><strong>Contract Start:</strong> " . date('F j, Y', strtotime($newStartDate)) . "</p>";
        echo "<p><strong>Contract Expiration:</strong> " . date('F j, Y', strtotime($newEndDate)) . "</p>";
        echo "<p><strong>Days Until Expiration:</strong> 15 days</p>";
        echo "<p><strong>Status:</strong> <span style='color: #dc2626; font-weight: bold;'>⚠️ RENEWAL NEEDED SOON</span></p>";
        echo "</div>";
        
        echo "<h3>🎯 What You Should See:</h3>";
        echo "<ul>";
        echo "<li>🔴 Red expiration warning at the top of your dashboard</li>";
        echo "<li>⏰ Countdown showing '15 days remaining'</li>";
        echo "<li>🔄 'Start Renewal Application' button should be visible</li>";
        echo "<li>📊 Contract progress bar showing near completion</li>";
        echo "</ul>";
        
        echo "<div style='background: #dcfce7; border: 2px solid #16a34a; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h3>🧪 Test the Renewal Flow:</h3>";
        echo "<ol>";
        echo "<li>Go to your <a href='tenant_dashboard.php' style='color: #16a34a; font-weight: bold;'>Dashboard</a></li>";
        echo "<li>Look for the red expiration warning</li>";
        echo "<li>Click 'Start Renewal Application'</li>";
        echo "<li>Test the renewal process</li>";
        echo "</ol>";
        echo "</div>";
        
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
