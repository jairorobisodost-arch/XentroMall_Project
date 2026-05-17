<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment_reminder_helper.php';

/**
 * Test Script for Payment Reminders
 * 
 * This script allows manual testing of the payment reminder logic by bypassing
 * the date check and targeting a specific test user.
 */

echo "--- Payment Reminder Test Tool ---\n";

// 1. You can change this ID to match a real tenant ID in your database for testing
$testUserId = 124; // Updated from 116 to a valid ID (jairorobiso.dost@gmail.com)

try {
    // Verify user exists
    $stmt = $pdo->prepare("SELECT u.id, td.tradename, td.email, td.mobile FROM users u JOIN tenant_details td ON u.id = td.user_id WHERE u.id = ?");
    $stmt->execute([$testUserId]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Error: Test User ID $testUserId not found in database.\n");
    }

    echo "Found Test User: " . $user['tradename'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Mobile: " . $user['mobile'] . "\n";
    echo "-----------------------------------\n";

    $mockDueDate = "March 5, 2026";
    $mockAmount = 1500.00;

    echo "Attempting to send multi-channel reminder...\n";

    $results = sendPaymentReminder($testUserId, $mockDueDate, $mockAmount);

    echo "RESULTS:\n";
    echo "Email: " . ($results['email']['success'] ? "✅ SENT" : "❌ FAILED (" . $results['email']['message'] . ")") . "\n";
    echo "SMS:   " . ($results['sms']['success'] ? "✅ SENT" : "❌ FAILED (" . $results['sms']['message'] . ")") . "\n";

    echo "-----------------------------------\n";
    echo "Check the 'error_log' or SMTP debug output above for more details.\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
