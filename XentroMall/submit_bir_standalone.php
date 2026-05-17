<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle file upload
        $uploadDir = 'uploads/extended_bir/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        if (!isset($_FILES['bir_document']) || $_FILES['bir_document']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid BIR document.']);
            exit;
        }

        $fileName = time() . '_standalone_' . $userId . '_' . basename($_FILES['bir_document']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['bir_document']['tmp_name'], $targetPath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save the uploaded file.']);
            exit;
        }

        // Get tenant_id from tenant_details
        $stmtTenant = $pdo->prepare("SELECT id, tradename FROM tenant_details WHERE user_id = ?");
        $stmtTenant->execute([$userId]);
        $tenant = $stmtTenant->fetch();
        
        if (!$tenant) {
            echo json_encode(['success' => false, 'message' => 'Tenant details not found.']);
            exit;
        }

        // Insert into extended_bir_submissions with NULL renewal_id
        $stmtInsert = $pdo->prepare("INSERT INTO extended_bir_submissions 
            (renewal_id, user_id, tenant_id, bir_document, status, submitted_at) 
            VALUES (NULL, ?, ?, ?, 'pending', NOW())");
        $stmtInsert->execute([$userId, $tenant['id'], $targetPath]);
        
        // Notify admin
        $adminMessage = "New Standalone BIR submission from " . $tenant['tradename'];
        $stmtAdminNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) 
                                          SELECT id, ?, 0, NOW() FROM users WHERE role = 'admin'");
        $stmtAdminNotif->execute([$adminMessage]);

        echo json_encode(['success' => true, 'message' => 'BIR document submitted successfully! Admin will review it shortly.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>
