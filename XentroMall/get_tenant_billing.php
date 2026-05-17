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
    $stmt = $pdo->prepare("SELECT water_bill, electric_bill FROM tenant_expenses WHERE user_id = ? AND stall_id = ? AND billing_month = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $stallId, $month]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($billing) {
        echo json_encode([
            'success' => true,
            'water_bill' => $billing['water_bill'],
            'electric_bill' => $billing['electric_bill']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'water_bill' => 0,
            'electric_bill' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
