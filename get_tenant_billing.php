<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$stallId = isset($_GET['stall_id']) ? (int)$_GET['stall_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : '';

if (!$userId || !$stallId || !$month) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Ensure month is correctly formatted as YYYY-MM-01 to match database records
if (preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = $month . '-01';
}

try {
    // 1. Fetch Water and Electric Bills from tenant_expenses
    $stmt = $pdo->prepare("SELECT water_bill, electric_bill FROM tenant_expenses WHERE user_id = ? AND stall_id = ? AND billing_month = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $stallId, $month]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);

    $water = $billing ? (float)$billing['water_bill'] : 0;
    $electric = $billing ? (float)$billing['electric_bill'] : 0;

    // 2. Calculate Arrears (Previous months' unpaid rent balance)
    // We look for all 'approved' or 'pending' payments that have a rent_balance > 0, 
    // OR we can calculate it by checking all previous months since lease start.
    // A simpler way: Sum of all rent_balance from the payments table for this user/stall excluding current month
    $stmtArrears = $pdo->prepare("
        SELECT SUM(rent_balance) as total_arrears 
        FROM payments 
        WHERE user_id = ? AND stall_id = ? 
        AND billing_month != ? 
        AND status IN ('approved', 'pending')
    ");
    // Note: billing_month in payments is often "January 2026" format, while $month is "2026-01-01"
    // Let's get the string format of the month for comparison
    $monthObj = new DateTime($month);
    $monthStr = $monthObj->format('F Y');
    
    $stmtArrears->execute([$userId, $stallId, $monthStr]);
    $arrears = (float)$stmtArrears->fetchColumn();

    // 3. Fetch Penalties (Late Fees)
    // For now, let's look for a 'late_payment_fee' in admin_settings or calculate it
    // If there's an existing 'penalties' table, fetch from there. Since we didn't find one, 
    // we might check if any payment for previous months was late.
    // Let's check for a general penalty setting
    $stmtPenalty = $pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'late_payment_fee' LIMIT 1");
    $penaltyRate = (float)$stmtPenalty->fetchColumn();
    
    // Simple logic: if there are arrears, apply the penalty once? Or per month?
    // User request: "isama mo lahat". Let's provide a field for it.
    $penalty = ($arrears > 0) ? $penaltyRate : 0;

    // 4. Other Charges (Maintenance, Security, Janitorial from Work Permits)
    $stmtWorkPermits = $pdo->prepare("
        SELECT SUM(rate_security + rate_janitorial + rate_maintenance) as other_total
        FROM work_permits 
        WHERE (tenant_name = (SELECT tradename FROM tenant_details WHERE id = ? LIMIT 1) 
               OR stall_number = (SELECT stall_number FROM stalls WHERE id = ? LIMIT 1))
        AND status = 'approved'
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmtWorkPermits->execute([(int)$_GET['user_id'], $stallId, $monthObj->format('Y-m')]);
    $otherCharges = (float)$stmtWorkPermits->fetchColumn();

    echo json_encode([
        'success' => true,
        'water_bill' => $water,
        'electric_bill' => $electric,
        'arrears' => $arrears,
        'penalty' => $penalty,
        'other_charges' => $otherCharges
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
