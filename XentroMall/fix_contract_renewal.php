<?php
session_start();
require 'config.php';

// Get the current user's payment and extend contract
$userId = $_SESSION['user_id'] ?? 60; // Use your user ID

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_contract'])) {
    try {
        // Get tenant details
        $stmtTenantDetails = $pdo->prepare("
            SELECT td.id as tenant_detail_id, td.stall_id, t.id as tenant_id, tld.lease_expiration_date 
            FROM tenant_details td
            LEFT JOIN tenants t ON t.email = td.email
            LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
            WHERE td.user_id = ?
        ");
        $stmtTenantDetails->execute([$userId]);
        $tenantRenewalInfo = $stmtTenantDetails->fetch(PDO::FETCH_ASSOC);
        
        if ($tenantRenewalInfo && $tenantRenewalInfo['lease_expiration_date']) {
            // Extend contract by 1 year from current expiration
            $currentExpiration = $tenantRenewalInfo['lease_expiration_date'];
            $newExpirationDate = date('Y-m-d', strtotime($currentExpiration . ' + 1 year'));
            
            echo "Current expiration: $currentExpiration<br>";
            echo "New expiration: $newExpirationDate<br>";
            
            // Update contract expiration date
            $stmtExtendContract = $pdo->prepare("
                UPDATE tenant_lease_dates 
                SET lease_expiration_date = ?, status = 'active' 
                WHERE tenant_id = ?
            ");
            $result = $stmtExtendContract->execute([$newExpirationDate, $tenantRenewalInfo['tenant_id']]);
            
            if ($result) {
                echo "✅ Contract successfully extended!<br>";
                echo "Your contract now expires on: " . date('F j, Y', strtotime($newExpirationDate));
                
                // Update renewal request status to completed
                $stmtUpdateRenewal = $pdo->prepare("
                    UPDATE unified_renewal_requests 
                    SET status = 'completed', payment_status = 'paid', completed_at = NOW() 
                    WHERE user_id = ? AND stall_id = ? AND status IN ('payment_pending', 'approved')
                ");
                $stmtUpdateRenewal->execute([$userId, $tenantRenewalInfo['stall_id']]);
                
                // Send success notification
                $renewalMessage = "🎉 Your contract renewal has been completed! Your contract is now extended until " . date('F j, Y', strtotime($newExpirationDate));
                $stmtRenewalNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                $stmtRenewalNotif->execute([$userId, $renewalMessage]);
                
                echo "<br><br>🎉 Renewal completed! Check your dashboard for updates.";
                
            } else {
                echo "❌ Failed to update contract";
            }
        } else {
            echo "❌ Could not find tenant contract info";
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Contract Renewal</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #059669; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔧 Fix Contract Renewal</h1>
    
    <div class="warning">
        <strong>⚠️ Manual Fix Required</strong><br>
        Your renewal payment was approved but the contract wasn't automatically extended. 
        Click the button below to manually extend your contract by 1 year.
    </div>
    
    <form method="POST">
        <button type="submit" name="fix_contract" class="btn">
            🔄 Extend My Contract by 1 Year
        </button>
    </form>
    
    <p><small>This will add 1 year to your current contract expiration date and mark your renewal as completed.</small></p>
</body>
</html>
