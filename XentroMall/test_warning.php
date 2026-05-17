<?php
require 'config.php';

// Update jairorobiso.dost@gmail.com's lease expiration to 30 days from now for testing
$tenantId = 9;
$newExpirationDate = date('Y-m-d', strtotime('+30 days')); // 30 days from now

$stmt = $pdo->prepare("UPDATE tenant_lease_dates SET lease_expiration_date = ? WHERE tenant_id = ?");
$stmt->execute([$newExpirationDate, $tenantId]);

echo "<h2>✅ Contract Updated for Testing!</h2>";
echo "<p><strong>Tenant ID:</strong> " . $tenantId . "</p>";
echo "<p><strong>New Expiration Date:</strong> " . $newExpirationDate . "</p>";
echo "<p><strong>Days from now:</strong> 30 days</p>";

echo "<h3>🔥 NOW THE WARNING SHOULD BE VISIBLE!</h3>";
echo "<p>Go to your tenant dashboard: <a href='tenant_dashboard.php' target='_blank'>Click Here</a></p>";
echo "<p>The red warning should now appear at the top of your dashboard!</p>";

// Show updated data
$stmt = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$lease = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Updated Lease Data:</h3>";
echo "<p><strong>Lease Start:</strong> " . htmlspecialchars($lease['lease_start_date']) . "</p>";
echo "<p><strong>Lease Expiration:</strong> " . htmlspecialchars($lease['lease_expiration_date']) . "</p>";

// Calculate days remaining
$expirationDate = new DateTime($lease['lease_expiration_date']);
$today = new DateTime();
$interval = $today->diff($expirationDate);
$daysRemaining = $interval->days;

echo "<p><strong>Days Remaining:</strong> " . $daysRemaining . "</p>";

if ($daysRemaining <= 30 && $daysRemaining > 0) {
    echo "<p style='color: red; font-weight: bold; font-size: 18px;'>🔥 WARNING WILL NOW SHOW ON DASHBOARD!</p>";
} else {
    echo "<p style='color: green;'>Warning not needed yet</p>";
}
?>
