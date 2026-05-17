<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment_reminder_helper.php';

/**
 * Automated Payment Reminders Script
 * 
 * This script identifies tenants who are due for payment and sends reminders via Email and SMS.
 * It uses 'payment_due_day' and 'payment_reminder_days' from system_settings.
 */

try {
    error_log("--- Starting Automated Payment Reminders --- " . date('Y-m-d H:i:s'));

    // 1. Fetch system settings
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('payment_due_day', 'payment_reminder_days')");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $dueDay = (int)($settings['payment_due_day'] ?? 5);
    $reminderDays = (int)($settings['payment_reminder_days'] ?? 7);

    // 2. Calculate target dates
    // Reminders are typically for the UPCOMING due date.
    // Example: Today is Feb 26. Due Day is 5. Next due date is March 5.
    
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    // Find the next occurrence of $dueDay
    $nextDue = new DateTime();
    $nextDue->setTime(0, 0, 0);
    $nextDue->setDate($today->format('Y'), $today->format('m'), $dueDay);

    if ($nextDue <= $today) {
        // If due day was earlier this month, the next one is next month
        $nextDue->modify('+1 month');
    }

    // Calculate the trigger date (X days before due date)
    $triggerDate = clone $nextDue;
    $triggerDate->modify("-$reminderDays days");

    error_log("Logic Check: Next Due: " . $nextDue->format('Y-m-d') . ", Trigger Date: " . $triggerDate->format('Y-m-d'));

    if ($today->format('Y-m-d') === $triggerDate->format('Y-m-d')) {
        error_log("Trigger Date REACHED. Processing reminders...");

        // 3. Identify tenants with unpaid rent for this period
        // We check the 'monthly_payments' table for the specific month/year of the next due date
        $billingMonth = $nextDue->format('Y-m-01');

        $stmt = $pdo->prepare("
            SELECT u.id, u.username, td.tradename, td.email, td.mobile, s.monthly_rate
            FROM users u
            JOIN tenant_details td ON u.id = td.user_id
            JOIN stalls s ON td.stall_id = s.id
            LEFT JOIN monthly_payments mp ON u.id = mp.user_id AND mp.payment_month = ?
            WHERE u.role = 'tenant' 
            AND (mp.status IS NULL OR mp.status = 'declined' OR mp.status = 'overdue')
        ");
        $stmt->execute([$billingMonth]);
        $unpaidTenants = $stmt->fetchAll();

        error_log("Found " . count($unpaidTenants) . " unpaid tenants for period $billingMonth");

        $formattedDueDate = $nextDue->format('F j, Y');

        foreach ($unpaidTenants as $tenant) {
            error_log("Sending reminder to: " . $tenant['tradename'] . " (User ID: " . $tenant['id'] . ")");
            
            $reminderResults = sendPaymentReminder(
                $tenant['id'], 
                $formattedDueDate, 
                (float)$tenant['monthly_rate']
            );

            error_log("Results for " . $tenant['tradename'] . ": Email=" . ($reminderResults['email']['success'] ? 'OK' : 'FAIL') . ", SMS=" . ($reminderResults['sms']['success'] ? 'OK' : 'FAIL'));
        }

    } else {
        error_log("Trigger Date not reached. No reminders sent today.");
    }

    error_log("--- Automated Payment Reminders Completed ---");

} catch (Exception $e) {
    error_log("Automated Reminder Critical Error: " . $e->getMessage());
}
