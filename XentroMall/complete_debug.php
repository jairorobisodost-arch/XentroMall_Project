<?php
session_start();
require 'config.php';

echo "<h2>🔍 Complete Database Debug</h2>";

// Check all relevant tables for user ID 7
$userId = 7;

echo "<h3>1. Checking users table for user ID: $userId</h3>";
$stmtUsers = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUsers->execute([$userId]);
$user = $stmtUsers->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "✅ Found user:<br>";
    echo "- ID: " . $user['id'] . "<br>";
    echo "- Username: " . $user['username'] . "<br>";
    echo "- Email: " . $user['email'] . "<br>";
    echo "- Role: " . $user['role'] . "<br><br>";
} else {
    echo "❌ No user found with ID: $userId<br><br>";
}

echo "<h3>2. Checking tenant_details table for user ID: $userId</h3>";
$stmtTenantDetails = $pdo->prepare("SELECT * FROM tenant_details WHERE user_id = ?");
$stmtTenantDetails->execute([$userId]);
$tenantDetails = $stmtTenantDetails->fetchAll(PDO::FETCH_ASSOC);

if ($tenantDetails) {
    echo "✅ Found " . count($tenantDetails) . " tenant detail records:<br>";
    foreach ($tenantDetails as $detail) {
        echo "- ID: " . $detail['id'] . "<br>";
        echo "- Tradename: " . $detail['tradename'] . "<br>";
        echo "- Stall ID: " . $detail['stall_id'] . "<br>";
        echo "- Email: " . $detail['email'] . "<br>";
        echo "- Status: " . $detail['status'] . "<br><br>";
    }
} else {
    echo "❌ No tenant details found for user ID: $userId<br><br>";
}

echo "<h3>3. Checking tenants table</h3>";
if ($user) {
    $stmtTenants = $pdo->prepare("SELECT * FROM tenants WHERE email = ?");
    $stmtTenants->execute([$user['email']]);
    $tenants = $stmtTenants->fetchAll(PDO::FETCH_ASSOC);
    
    if ($tenants) {
        echo "✅ Found " . count($tenants) . " tenant records:<br>";
        foreach ($tenants as $tenant) {
            echo "- ID: " . $tenant['id'] . "<br>";
            echo "- Username: " . $tenant['username'] . "<br>";
            echo "- Email: " . $tenant['email'] . "<br><br>";
        }
    } else {
        echo "❌ No tenant records found for email: " . $user['email'] . "<br><br>";
    }
}

echo "<h3>4. Checking payments table for user ID: $userId</h3>";
$stmtPayments = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 5");
$stmtPayments->execute([$userId]);
$payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);

if ($payments) {
    echo "✅ Found " . count($payments) . " payment records:<br>";
    foreach ($payments as $payment) {
        echo "- Payment ID: " . $payment['id'] . "<br>";
        echo "- Amount: ₱" . number_format($payment['amount'], 2) . "<br>";
        echo "- Status: " . $payment['status'] . "<br>";
        echo "- Payment Type: " . ($payment['payment_type'] ?? 'N/A') . "<br>";
        echo "- Date: " . $payment['payment_date'] . "<br><br>";
    }
} else {
    echo "❌ No payments found for user ID: $userId<br><br>";
}

echo "<h3>5. Checking unified_renewal_requests table for user ID: $userId</h3>";
$stmtRenewals = $pdo->prepare("SELECT * FROM unified_renewal_requests WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 5");
$stmtRenewals->execute([$userId]);
$renewals = $stmtRenewals->fetchAll(PDO::FETCH_ASSOC);

if ($renewals) {
    echo "✅ Found " . count($renewals) . " renewal requests:<br>";
    foreach ($renewals as $renewal) {
        echo "- Request ID: " . $renewal['id'] . "<br>";
        echo "- Status: " . $renewal['status'] . "<br>";
        echo "- Payment Status: " . ($renewal['payment_status'] ?? 'N/A') . "<br>";
        echo "- Request Type: " . $renewal['request_type'] . "<br>";
        echo "- Amount: ₱" . number_format($renewal['total_amount'], 2) . "<br>";
        echo "- Submitted: " . $renewal['submitted_at'] . "<br><br>";
    }
} else {
    echo "❌ No renewal requests found for user ID: $userId<br><br>";
}

echo "<h3>6. Checking tenant_lease_dates table</h3>";
$stmtLeaseDates = $pdo->prepare("SELECT * FROM tenant_lease_dates ORDER BY lease_expiration_date DESC LIMIT 5");
$stmtLeaseDates->execute([]);
$leaseDates = $stmtLeaseDates->fetchAll(PDO::FETCH_ASSOC);

if ($leaseDates) {
    echo "✅ Found " . count($leaseDates) . " lease records:<br>";
    foreach ($leaseDates as $lease) {
        echo "- Tenant ID: " . $lease['tenant_id'] . "<br>";
        echo "- Lease Start: " . $lease['lease_start_date'] . "<br>";
        echo "- Lease Expiration: " . $lease['lease_expiration_date'] . "<br>";
        echo "- Status: " . $lease['status'] . "<br><br>";
    }
} else {
    echo "❌ No lease dates found<br><br>";
}

// Fix button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_missing_records'])) {
    echo "<h3>🔧 Creating Missing Records...</h3>";
    
    if ($user && empty($tenantDetails)) {
        echo "<p>Creating tenant_details record...</p>";
        
        // Get a stall for this user
        $stmtStall = $pdo->prepare("SELECT id, stall_number, monthly_rate FROM stalls WHERE status = 'available' LIMIT 1");
        $stmtStall->execute([]);
        $stall = $stmtStall->fetch(PDO::FETCH_ASSOC);
        
        if ($stall) {
            // Create tenant_details
            $stmtCreateDetail = $pdo->prepare("
                INSERT INTO tenant_details (user_id, tradename, stall_id, email, contact_person, mobile, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'approved')
            ");
            $stmtCreateDetail->execute([
                $userId,
                $user['username'] . ' Business',
                $stall['id'],
                $user['email'],
                $user['username'],
                '09123456789'
            ]);
            
            $tenantDetailId = $pdo->lastInsertId();
            echo "✅ Created tenant_details record with ID: $tenantDetailId<br>";
            
            // Update stall status
            $stmtUpdateStall = $pdo->prepare("UPDATE stalls SET status = 'not_available' WHERE id = ?");
            $stmtUpdateStall->execute([$stall['id']]);
            
            // Create tenant record
            $stmtCreateTenant = $pdo->prepare("
                INSERT INTO tenants (username, email, password, role) 
                VALUES (?, ?, ?, 'tenant')
            ");
            $stmtCreateTenant->execute([$user['username'], $user['email'], $user['password']]);
            $tenantId = $pdo->lastInsertId();
            
            echo "✅ Created tenant record with ID: $tenantId<br>";
            
            // Create lease dates
            $leaseStart = date('Y-m-d');
            $leaseEnd = date('Y-m-d', strtotime('+1 year'));
            
            $stmtCreateLease = $pdo->prepare("
                INSERT INTO tenant_lease_dates (tenant_id, lease_start_date, lease_expiration_date, status) 
                VALUES (?, ?, ?, 'active')
            ");
            $stmtCreateLease->execute([$tenantId, $leaseStart, $leaseEnd]);
            
            echo "✅ Created lease dates: $leaseStart to $leaseEnd<br>";
            echo "<br><strong>🎉 All records created successfully!</strong><br>";
            echo "<a href='tenant_dashboard.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>🏠 Go to Dashboard</a>";
            
        } else {
            echo "❌ No available stalls found<br>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complete Database Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
        .btn { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px 5px; }
        .btn:hover { background: #059669; }
        .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .debug { background: #f3f4f6; border: 1px solid #d1d5db; padding: 15px; border-radius: 5px; margin: 20px 0; font-family: monospace; font-size: 14px; }
        h3 { color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
    </style>
</head>
<body>
    <h1>🔍 Complete Database Debug</h1>
    
    <div class="warning">
        <strong>⚠️ Issue Found:</strong><br>
        User ID 7 has no tenant_details record, which is why the renewal system can't find your contract.
    </div>
    
    <?php if (isset($user) && empty($tenantDetails)): ?>
    <form method="POST">
        <button type="submit" name="create_missing_records" class="btn">
            🔧 Create Missing Tenant Records
        </button>
    </form>
    <?php endif; ?>
    
    <form method="GET" action="">
        <button type="submit" name="refresh" class="btn">
            🔄 Refresh Debug Info
        </button>
    </form>
    
    <p><small>This tool will create all the missing records needed for your renewal to work properly.</small></p>
</body>
</html>
