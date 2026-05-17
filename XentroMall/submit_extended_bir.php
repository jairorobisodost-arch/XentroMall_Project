<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $renewalId = $_POST['renewal_id'] ?? null;
        
        if (!$renewalId) {
            $_SESSION['error'] = "Invalid renewal request.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        // Get tenant_id
        $stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userEmail = $stmtUser->fetchColumn();
        
        $stmtTenant = $pdo->prepare("SELECT id FROM tenants WHERE email = ?");
        $stmtTenant->execute([$userEmail]);
        $tenantRecord = $stmtTenant->fetch();
        $tenantId = $tenantRecord['id'] ?? null;
        
        if (!$tenantId) {
            $_SESSION['error'] = "Tenant information not found.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        // Verify renewal exists and is approved
        $stmtRenewal = $pdo->prepare("SELECT * FROM contract_renewals WHERE id = ? AND user_id = ? AND status = 'approved'");
        $stmtRenewal->execute([$renewalId, $userId]);
        $renewal = $stmtRenewal->fetch();
        
        if (!$renewal) {
            $_SESSION['error'] = "Renewal request not found or not yet approved.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        // Check if already submitted BIR
        $stmtCheck = $pdo->prepare("SELECT id FROM extended_bir_submissions WHERE renewal_id = ? AND user_id = ?");
        $stmtCheck->execute([$renewalId, $userId]);
        if ($stmtCheck->fetch()) {
            $_SESSION['error'] = "You have already submitted Extended BIR documents for this renewal.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        // Handle file upload
        $uploadDir = 'uploads/extended_bir/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $birDocument = null;
        if (isset($_FILES['bir_document']) && $_FILES['bir_document']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . $userId . '_' . basename($_FILES['bir_document']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['bir_document']['tmp_name'], $targetPath)) {
                $birDocument = $targetPath;
            } else {
                $_SESSION['error'] = "Failed to upload BIR document.";
                header('Location: tenant_dashboard.php?page=renewal');
                exit;
            }
        } else {
            $_SESSION['error'] = "Please upload Extended BIR document.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        // Insert BIR submission
        $stmtInsert = $pdo->prepare("INSERT INTO extended_bir_submissions 
            (renewal_id, user_id, tenant_id, bir_document, status) 
            VALUES (?, ?, ?, ?, 'pending')");
        $stmtInsert->execute([$renewalId, $userId, $tenantId, $birDocument]);
        
        // Create notification for tenant
        $message = "Your Extended BIR documents have been submitted successfully and are awaiting admin review.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $stmtNotif->execute([$userId, $message]);
        
        // Notify admin
        $stmtTenantName = $pdo->prepare("SELECT tradename FROM tenant_details WHERE user_id = ?");
        $stmtTenantName->execute([$userId]);
        $tenantName = $stmtTenantName->fetchColumn() ?: 'Tenant';
        
        $adminMessage = "New Extended BIR submission from " . $tenantName . " for renewal request #" . $renewalId;
        $stmtAdminNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) 
                                          SELECT id, ?, 0 FROM users WHERE role = 'admin'");
        $stmtAdminNotif->execute([$adminMessage]);
        
        $_SESSION['success'] = "Extended BIR documents submitted successfully!";
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting BIR documents: " . $e->getMessage();
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
    }
} else {
    header('Location: tenant_dashboard.php?page=renewal');
    exit;
}
?>
