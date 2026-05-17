<?php
session_start();
require 'config.php';

echo "<h2>🔧 Fix Renewal Payment Status</h2>";

// Find the renewal request for "Bench Company"
echo "<h3>🔍 Finding renewal request for Bench Company...</h3>";

$stmtFindRenewal = $pdo->prepare("
    SELECT * FROM unified_renewal_requests 
    WHERE tradename LIKE '%Bench%' AND status = 'approved'
    ORDER BY submitted_at DESC
");
$stmtFindRenewal->execute();
$renewal = $stmtFindRenewal->fetch(PDO::FETCH_ASSOC);

if ($renewal) {
    echo "✅ Found renewal request:<br>";
    echo "- Request ID: " . $renewal['id'] . "<br>";
    echo "- Tradename: " . $renewal['tradename'] . "<br>";
    echo "- Status: " . $renewal['status'] . "<br>";
    echo "- Payment Status: " . ($renewal['payment_status'] ?? 'NULL') . "<br>";
    echo "- User ID: " . $renewal['user_id'] . "<br>";
    echo "- Stall ID: " . $renewal['stall_id'] . "<br>";
    echo "- Amount: ₱" . number_format($renewal['total_amount'], 2) . "<br>";
    echo "- Submitted: " . $renewal['submitted_at'] . "<br><br>";
    
    // Check if there's a payment for this renewal
    echo "<h3>💳 Checking payment for this renewal...</h3>";
    $stmtPayment = $pdo->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? AND stall_id = ? AND payment_type LIKE '%renewal%'
        ORDER BY payment_date DESC
    ");
    $stmtPayment->execute([$renewal['user_id'], $renewal['stall_id']]);
    $payment = $stmtPayment->fetch(PDO::FETCH_ASSOC);
    
    if ($payment) {
        echo "✅ Found payment:<br>";
        echo "- Payment ID: " . $payment['id'] . "<br>";
        echo "- Amount: ₱" . number_format($payment['amount'], 2) . "<br>";
        echo "- Status: " . $payment['status'] . "<br>";
        echo "- Payment Type: " . $payment['payment_type'] . "<br>";
        echo "- Payment Date: " . $payment['payment_date'] . "<br><br>";
        
        if ($payment['status'] === 'approved') {
            echo "<h3>🎉 Payment is approved! Fixing renewal status...</h3>";
            
            // Update renewal request to completed
            $stmtUpdateRenewal = $pdo->prepare("
                UPDATE unified_renewal_requests 
                SET status = 'completed' 
                WHERE id = ?
            ");
            $result1 = $stmtUpdateRenewal->execute([$renewal['id']]);
            
            if ($result1) {
                echo "✅ Updated renewal request to 'completed'<br>";
            }
            
            // Find and extend the contract
            echo "<h3>📅 Extending contract...</h3>";
            
            // Find tenant details
            $stmtTenantDetails = $pdo->prepare("
                SELECT td.*, t.id as tenant_id FROM tenant_details td
                LEFT JOIN tenants t ON t.email = td.email
                WHERE td.user_id = ?
            ");
            $stmtTenantDetails->execute([$renewal['user_id']]);
            $tenantInfo = $stmtTenantDetails->fetch(PDO::FETCH_ASSOC);
            
            if ($tenantInfo && $tenantInfo['tenant_id']) {
                // Get current contract
                $stmtContract = $pdo->prepare("
                    SELECT * FROM tenant_lease_dates WHERE tenant_id = ?
                ");
                $stmtContract->execute([$tenantInfo['tenant_id']]);
                $contract = $stmtContract->fetch(PDO::FETCH_ASSOC);
                
                if ($contract) {
                    $currentExpiration = $contract['lease_expiration_date'];
                    $newExpirationDate = date('Y-m-d', strtotime($currentExpiration . ' + 1 year'));
                    
                    echo "Current expiration: $currentExpiration<br>";
                    echo "New expiration: $newExpirationDate<br><br>";
                    
                    // Update contract
                    $stmtExtendContract = $pdo->prepare("
                        UPDATE tenant_lease_dates 
                        SET lease_expiration_date = ?, status = 'active' 
                        WHERE tenant_id = ?
                    ");
                    $result2 = $stmtExtendContract->execute([$newExpirationDate, $tenantInfo['tenant_id']]);
                    
                    if ($result2) {
                        echo "✅ Contract extended successfully!<br>";
                        echo "Your contract now expires on: " . date('F j, Y', strtotime($newExpirationDate)) . "<br><br>";
                        
                        // Send success notification
                        $renewalMessage = "🎉 Your contract renewal has been completed! Your contract is now extended until " . date('F j, Y', strtotime($newExpirationDate));
                        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                        $stmtNotif->execute([$renewal['user_id'], $renewalMessage]);
                        
                        echo "✅ Success notification sent<br>";
                        echo "<br><strong>🎉 Renewal completed successfully!</strong><br>";
                        echo "<a href='tenant_dashboard.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>🏠 Go to Dashboard</a>";
                        
                    } else {
                        echo "❌ Failed to extend contract<br>";
                    }
                } else {
                    echo "❌ No contract found for tenant ID: " . $tenantInfo['tenant_id'] . "<br>";
                }
            } else {
                echo "❌ Could not find tenant info for user ID: " . $renewal['user_id'] . "<br>";
            }
            
        } else {
            echo "❌ Payment status is: " . $payment['status'] . " (should be 'approved')<br>";
            echo "The payment needs to be approved first by the admin.<br>";
        }
        
    } else {
        echo "❌ No payment found for this renewal<br>";
    }
    
} else {
    echo "❌ No renewal request found for Bench Company<br>";
    
    // Search all renewal requests
    echo "<h3>🔍 All renewal requests:</h3>";
    $stmtAllRenewals = $pdo->prepare("
        SELECT * FROM unified_renewal_requests 
        ORDER BY submitted_at DESC LIMIT 10
    ");
    $stmtAllRenewals->execute([]);
    $allRenewals = $stmtAllRenewals->fetchAll(PDO::FETCH_ASSOC);
    
    if ($allRenewals) {
        foreach ($allRenewals as $r) {
            echo "- ID: " . $r['id'] . ", Tradename: " . $r['tradename'] . ", Status: " . $r['status'] . ", Payment: " . ($r['payment_status'] ?? 'NULL') . "<br>";
        }
    } else {
        echo "No renewal requests found at all.<br>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fix Renewal Payment Status</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #059669; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d1fae5; border: 1px solid #10b981; padding: 15px; border-radius: 5px; margin: 20px 0; }
        h3 { color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🔧 Fix Renewal Payment Status</h1>
    
    <div class="warning">
        <strong>⚠️ Issue:</strong><br>
        Your renewal shows "Payment Required" even though you already paid. This tool will find and fix the status.
    </div>
    
    <form method="GET" action="">
        <button type="submit" name="refresh" class="btn">
            🔄 Refresh & Fix Status
        </button>
    </form>
    
    <p><small>This tool will automatically detect your Bench Company renewal and fix the payment status.</small></p>
</body>
</html>
