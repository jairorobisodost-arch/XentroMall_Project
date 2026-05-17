<?php
session_start();
require 'config.php';

// Get the current user's payment and extend contract
$userId = $_SESSION['user_id'] ?? 60; // Use your user ID

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_contract'])) {
    try {
        echo "<h3>🔍 Debugging Contract Search...</h3>";
        
        // Step 1: Check tenant_details
        echo "<p><strong>Step 1:</strong> Finding tenant details for user ID: $userId</p>";
        $stmtTenantDetails = $pdo->prepare("SELECT * FROM tenant_details WHERE user_id = ?");
        $stmtTenantDetails->execute([$userId]);
        $tenantDetail = $stmtTenantDetails->fetch(PDO::FETCH_ASSOC);
        
        if ($tenantDetail) {
            echo "✅ Found tenant details:<br>";
            echo "- Tenant Detail ID: " . $tenantDetail['id'] . "<br>";
            echo "- Tradename: " . $tenantDetail['tradename'] . "<br>";
            echo "- Stall ID: " . $tenantDetail['stall_id'] . "<br>";
            echo "- Email: " . $tenantDetail['email'] . "<br><br>";
            
            // Step 2: Check if tenant exists in tenants table
            echo "<p><strong>Step 2:</strong> Finding tenant record for email: " . $tenantDetail['email'] . "</p>";
            $stmtTenant = $pdo->prepare("SELECT * FROM tenants WHERE email = ?");
            $stmtTenant->execute([$tenantDetail['email']]);
            $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
            
            if ($tenant) {
                echo "✅ Found tenant record:<br>";
                echo "- Tenant ID: " . $tenant['id'] . "<br>";
                echo "- Username: " . $tenant['username'] . "<br><br>";
                
                // Step 3: Check contract dates
                echo "<p><strong>Step 3:</strong> Finding contract dates for tenant ID: " . $tenant['id'] . "</p>";
                $stmtContract = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
                $stmtContract->execute([$tenant['id']]);
                $contract = $stmtContract->fetch(PDO::FETCH_ASSOC);
                
                if ($contract && $contract['lease_expiration_date']) {
                    echo "✅ Found contract:<br>";
                    echo "- Current Expiration: " . $contract['lease_expiration_date'] . "<br>";
                    echo "- Status: " . $contract['status'] . "<br><br>";
                    
                    // Step 4: Extend contract
                    $currentExpiration = $contract['lease_expiration_date'];
                    $newExpirationDate = date('Y-m-d', strtotime($currentExpiration . ' + 1 year'));
                    
                    echo "<p><strong>Step 4:</strong> Extending contract...</p>";
                    echo "- From: $currentExpiration<br>";
                    echo "- To: $newExpirationDate<br><br>";
                    
                    $stmtExtendContract = $pdo->prepare("
                        UPDATE tenant_lease_dates 
                        SET lease_expiration_date = ?, status = 'active' 
                        WHERE tenant_id = ?
                    ");
                    $result = $stmtExtendContract->execute([$newExpirationDate, $tenant['id']]);
                    
                    if ($result) {
                        echo "✅ <strong>Contract successfully extended!</strong><br>";
                        echo "Your contract now expires on: " . date('F j, Y', strtotime($newExpirationDate)) . "<br><br>";
                        
                        // Update renewal request status
                        $stmtUpdateRenewal = $pdo->prepare("
                            UPDATE unified_renewal_requests 
                            SET status = 'completed', payment_status = 'paid', completed_at = NOW() 
                            WHERE user_id = ? AND stall_id = ? AND status IN ('payment_pending', 'approved')
                        ");
                        $stmtUpdateRenewal->execute([$userId, $tenantDetail['stall_id']]);
                        
                        // Send success notification
                        $renewalMessage = "🎉 Your contract renewal has been completed! Your contract is now extended until " . date('F j, Y', strtotime($newExpirationDate));
                        $stmtRenewalNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                        $stmtRenewalNotif->execute([$userId, $renewalMessage]);
                        
                        echo "🎉 <strong>Renewal completed! Check your dashboard for updates.</strong><br>";
                        echo "<a href='tenant_dashboard.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>🏠 Go to Dashboard</a>";
                        
                    } else {
                        echo "❌ Failed to update contract<br>";
                    }
                    
                } else {
                    echo "❌ No contract found for tenant ID: " . $tenant['id'] . "<br>";
                    echo "Creating new contract...<br>";
                    
                    // Create new contract
                    $contractStart = date('Y-m-d');
                    $contractEnd = date('Y-m-d', strtotime('+1 year'));
                    
                    $stmtCreateContract = $pdo->prepare("
                        INSERT INTO tenant_lease_dates 
                        (tenant_id, lease_start_date, lease_expiration_date, status) 
                        VALUES (?, ?, ?, 'active')
                    ");
                    $stmtCreateContract->execute([$tenant['id'], $contractStart, $contractEnd]);
                    
                    echo "✅ Created new contract from $contractStart to $contractEnd<br>";
                }
                
            } else {
                echo "❌ No tenant record found for email: " . $tenantDetail['email'] . "<br>";
                echo "Creating tenant record...<br>";
                
                // Create tenant record
                $stmtCreateTenant = $pdo->prepare("
                    INSERT INTO tenants (username, email, password, role) 
                    SELECT username, email, password, 'tenant' FROM users WHERE id = ?
                ");
                $stmtCreateTenant->execute([$userId]);
                $newTenantId = $pdo->lastInsertId();
                
                echo "✅ Created tenant record with ID: $newTenantId<br>";
                
                // Create contract
                $contractStart = date('Y-m-d');
                $contractEnd = date('Y-m-d', strtotime('+1 year'));
                
                $stmtCreateContract = $pdo->prepare("
                    INSERT INTO tenant_lease_dates 
                    (tenant_id, lease_start_date, lease_expiration_date, status) 
                    VALUES (?, ?, ?, 'active')
                ");
                $stmtCreateContract->execute([$newTenantId, $contractStart, $contractEnd]);
                
                echo "✅ Created contract from $contractStart to $contractEnd<br>";
            }
            
        } else {
            echo "❌ No tenant details found for user ID: $userId<br>";
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
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #059669; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .debug { background: #f3f4f6; border: 1px solid #d1d5db; padding: 15px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 14px; }
    </style>
</head>
<body>
    <h1>🔧 Fix Contract Renewal</h1>
    
    <div class="warning">
        <strong>⚠️ Manual Fix Required</strong><br>
        Your renewal payment was approved but the contract wasn't automatically extended. 
        Click the button below to manually extend your contract by 1 year.
    </div>
    
    <div class="debug">
        <strong>🔍 Debug Mode:</strong><br>
        This tool will show you exactly what's happening step by step, so we can find and fix any issues with your contract.
    </div>
    
    <form method="POST">
        <button type="submit" name="fix_contract" class="btn">
            🔍 Debug & Extend My Contract
        </button>
    </form>
    
    <p><small>This will show detailed debugging information and extend your contract by 1 year.</small></p>
</body>
</html>
