<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Fetch tenant basic info
    $stmtUser = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Tenant not found']);
        exit;
    }
    
    // Fetch ALL stalls/applications of this tenant
    $stmtStalls = $pdo->prepare("
        SELECT 
            td.id, td.tradename, td.company_name, td.business_type, td.ownership,
            td.tin, td.business_address, td.contact_person, td.mobile, td.email,
            td.status, td.created_at, td.admin_feedback, td.documents,
            s.id as stall_id, s.stall_number, s.description, s.floor_area, s.monthly_rate, s.image_path
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        WHERE td.user_id = ?
        ORDER BY td.status DESC, td.created_at DESC
    ");
    $stmtStalls->execute([$userId]);
    $allStalls = $stmtStalls->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch payment history
    $stmtPayments = $pdo->prepare("
        SELECT * FROM payments 
        WHERE user_id = ? 
        ORDER BY payment_date DESC 
        LIMIT 10
    ");
    $stmtPayments->execute([$userId]);
    $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stalls' => $allStalls,
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
