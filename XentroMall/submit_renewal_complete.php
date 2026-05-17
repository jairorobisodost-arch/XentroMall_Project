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
        
        // Get user email and tenant_id
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
        
        // Check if already submitted
        if ($renewal['payment_proof']) {
            $_SESSION['error'] = "You have already submitted payment and BIR documents for this renewal.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Handle payment proof upload
        $uploadDirPayment = 'uploads/renewal_payments/';
        if (!file_exists($uploadDirPayment)) {
            mkdir($uploadDirPayment, 0777, true);
        }
        
        $paymentProof = null;
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_payment_' . $userId . '_' . basename($_FILES['payment_proof']['name']);
            $targetPath = $uploadDirPayment . $fileName;
            
            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
                $paymentProof = $targetPath;
            } else {
                throw new Exception("Failed to upload payment proof.");
            }
        } else {
            throw new Exception("Please upload payment proof.");
        }
        
        // Handle BIR document upload
        $uploadDirBIR = 'uploads/extended_bir/';
        if (!file_exists($uploadDirBIR)) {
            mkdir($uploadDirBIR, 0777, true);
        }
        
        $birDocument = null;
        if (isset($_FILES['bir_document']) && $_FILES['bir_document']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_bir_' . $userId . '_' . basename($_FILES['bir_document']['name']);
            $targetPath = $uploadDirBIR . $fileName;
            
            if (move_uploaded_file($_FILES['bir_document']['tmp_name'], $targetPath)) {
                $birDocument = $targetPath;
            } else {
                throw new Exception("Failed to upload BIR document.");
            }
        } else {
            throw new Exception("Please upload Extended BIR document.");
        }
        
        // Update renewal with payment proof
        $stmtUpdateRenewal = $pdo->prepare("UPDATE contract_renewals SET payment_proof = ? WHERE id = ?");
        $stmtUpdateRenewal->execute([$paymentProof, $renewalId]);
        
        // Insert BIR submission
        $stmtInsertBIR = $pdo->prepare("INSERT INTO extended_bir_submissions 
            (renewal_id, user_id, tenant_id, bir_document, status) 
            VALUES (?, ?, ?, ?, 'pending')");
        $stmtInsertBIR->execute([$renewalId, $userId, $tenantId, $birDocument]);
        
        $pdo->commit();
        
        // Create notification for tenant
        $message = "Your renewal payment and Extended BIR documents have been submitted successfully. Waiting for admin verification.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $stmtNotif->execute([$userId, $message]);
        
        // Notify admin
        $stmtTenantName = $pdo->prepare("SELECT tradename FROM tenant_details WHERE user_id = ?");
        $stmtTenantName->execute([$userId]);
        $tenantName = $stmtTenantName->fetchColumn() ?: 'Tenant';
        
        $adminMessage = "Complete renewal submission from " . $tenantName . " - Payment proof and Extended BIR documents. Renewal ID: #" . $renewalId . ". Amount: ₱" . number_format($renewal['total_amount'], 2);
        $stmtAdminNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) 
                                          SELECT id, ?, 0 FROM users WHERE role = 'admin'");
        $stmtAdminNotif->execute([$adminMessage]);
        
        $_SESSION['success'] = "Payment proof and Extended BIR documents submitted successfully!";
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
    }
} else {
    header('Location: tenant_dashboard.php?page=renewal');
    exit;
}
?>
