<?php
require 'config.php';

echo "<h2>Checking Contract Data for jairorobiso.dost@gmail.com</h2>";

// Get user email
$stmt = $pdo->prepare("SELECT email FROM users WHERE username = ? OR email = ?");
$stmt->execute(['jairorobiso.dost@gmail.com', 'jairorobiso.dost@gmail.com']);
$email = $stmt->fetchColumn();

echo "<p><strong>User Email:</strong> " . htmlspecialchars($email) . "</p>";

if ($email) {
    // Get tenant ID
    $stmt = $pdo->prepare("SELECT id as tenant_id FROM tenants WHERE email = ?");
    $stmt->execute([$email]);
    $tenant = $stmt->fetch();
    
    if ($tenant) {
        echo "<p><strong>Tenant ID:</strong> " . $tenant['tenant_id'] . "</p>";
        
        // Check lease dates
        $stmt = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
        $stmt->execute([$tenant['tenant_id']]);
        $lease = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lease) {
            echo "<h3>✅ Lease Data Found:</h3>";
            echo "<p><strong>Lease Start:</strong> " . htmlspecialchars($lease['lease_start_date']) . "</p>";
            echo "<p><strong>Lease Expiration:</strong> " . htmlspecialchars($lease['lease_expiration_date']) . "</p>";
            
            // Calculate days remaining
            $expirationDate = new DateTime($lease['lease_expiration_date']);
            $today = new DateTime();
            $interval = $today->diff($expirationDate);
            $daysRemaining = $interval->days;
            
            echo "<p><strong>Days Remaining:</strong> " . $daysRemaining . "</p>";
            
            if ($daysRemaining <= 30 && $daysRemaining > 0) {
                echo "<p style='color: red; font-weight: bold;'>🔥 WARNING SHOULD BE VISIBLE!</p>";
            } else {
                echo "<p style='color: green;'>✅ Warning not needed yet</p>";
            }
        } else {
            echo "<h3>❌ NO LEASE EXPIRATION DATE SET!</h3>";
            echo "<p>This is why the warning is not showing.</p>";
        }
    } else {
        echo "<p>❌ NO TENANT RECORD FOUND!</p>";
    }
} else {
    echo "<p>❌ NO USER FOUND!</p>";
}

// Check all lease dates in table
echo "<h3>All Lease Dates in Database:</h3>";
$stmt = $pdo->prepare("SELECT tld.*, t.email as tenant_email FROM tenant_lease_dates tld JOIN tenants t ON tld.tenant_id = t.id");
$stmt->execute();
$leases = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($leases as $lease) {
    echo "<p><strong>Email:</strong> " . htmlspecialchars($lease['tenant_email']) . " | ";
    echo "<strong>Expires:</strong> " . htmlspecialchars($lease['lease_expiration_date']) . "</p>";
}
?>
