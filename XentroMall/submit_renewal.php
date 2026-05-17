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
        // Get tenant details for existing account
        $stmtDetails = $pdo->prepare("SELECT tradename, stall_id FROM tenant_details WHERE user_id = ? AND status = 'approved'");
        $stmtDetails->execute([$userId]);
        $tenantDetails = $stmtDetails->fetch();
        
        if (!$tenantDetails) {
            $_SESSION['error'] = "No approved tenant record found.";
            header('Location: tenant_dashboard.php');
            exit;
        }
        
        $tradename = $tenantDetails['tradename'];
        $stallId = $tenantDetails['stall_id'];
        
        // Get stall details for monthly rate
        $stmtStall = $pdo->prepare("SELECT monthly_rate FROM stalls WHERE id = ?");
        $stmtStall->execute([$stallId]);
        $stallDetails = $stmtStall->fetch();
        $monthlyRate = $stallDetails['monthly_rate'] ?? 0;
        
        // Handle Extended BIR upload
        $extendedBirPath = null;
        if (isset($_FILES['extended_bir']) && $_FILES['extended_bir']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/renewal_documents/' . $userId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['extended_bir']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['extended_bir']['tmp_name'], $targetPath)) {
                $extendedBirPath = $targetPath;
            }
        }
        
        // Calculate renewal amount (monthly only, not 3 months advance)
        $renewalAmount = $monthlyRate; // Monthly payment only for renewal
        
        // Insert renewal request with monthly payment logic
        $stmt = $pdo->prepare("
            INSERT INTO renewal_requests (
                user_id, tradename, stall_id, monthly_rate, renewal_amount, 
                extended_bir_document, payment_type, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'monthly', 'pending', NOW())
        ");
        $stmt->execute([
            $userId,
            $tradename,
            $stallId,
            $monthlyRate,
            $renewalAmount,
            $extendedBirPath
        ]);
        
        $_SESSION['success'] = "Renewal request submitted successfully! Monthly payment: ₱" . number_format($renewalAmount, 2);
        header('Location: tenant_dashboard.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: tenant_dashboard.php');
        exit;
    }
} else {
    header('Location: tenant_dashboard.php');
    exit;
}
?>
        
        if (!$contract) {
            $_SESSION['error'] = "No active contract found.";
            header('Location: tenant_dashboard.php');
            exit;
        }
        
        // Check if already has pending renewal
        $stmtCheck = $pdo->prepare("SELECT id FROM contract_renewals WHERE tenant_id = ? AND status = 'pending'");
        $stmtCheck->execute([$tenantId]);
        if ($stmtCheck->fetch()) {
            $_SESSION['error'] = "You already have a pending renewal request.";
            header('Location: tenant_dashboard.php');
            exit;
        }
        
        // Calculate late fee if expired
        $expirationDate = new DateTime($contract['lease_expiration_date']);
        $today = new DateTime();
        $daysAfterExpiration = 0;
        $lateFee = 0;
        
        if ($today > $expirationDate) {
            $interval = $today->diff($expirationDate);
            $daysAfterExpiration = $interval->days;
            
            // Get late fee from settings
            $stmtFee = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'late_renewal_fee'");
            $stmtFee->execute();
            $lateFee = $stmtFee->fetchColumn() ?: 500;
        }
        
        // Get monthly rate from stall
        $stmtStall = $pdo->prepare("SELECT s.monthly_rate FROM stalls s 
                                     INNER JOIN tenant_details td ON td.stall_id = s.id 
                                     WHERE td.user_id = ?");
        $stmtStall->execute([$userId]);
        $stall = $stmtStall->fetch();
        $monthlyRate = $stall['monthly_rate'] ?? 0;
        
        // Total amount = (monthly rate * 12 months) + late fee
        $totalAmount = ($monthlyRate * 12) + $lateFee;
        
        // NO PAYMENT PROOF UPLOAD - Just request
        // Payment will be submitted AFTER admin approval
        
        // Insert renewal request (without payment proof)
        $stmtInsert = $pdo->prepare("INSERT INTO contract_renewals 
            (tenant_id, user_id, old_contract_end, payment_proof, late_renewal_fee, total_amount, status) 
            VALUES (?, ?, ?, NULL, ?, ?, 'pending')");
        $stmtInsert->execute([
            $tenantId,
            $userId,
            $contract['lease_expiration_date'],
            $lateFee,
            $totalAmount
        ]);
        
        // Create notification for tenant
        $message = "Your contract renewal request has been submitted and is awaiting admin approval. Total amount: ₱" . number_format($totalAmount, 2);
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $stmtNotif->execute([$userId, $message]);
        
        // Notify admin about new renewal request
        $adminMessage = "New contract renewal request from " . $tradename . ". Amount: ₱" . number_format($totalAmount, 2);
        $stmtAdminNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) 
                                          SELECT id, ?, 0 FROM users WHERE role = 'admin'");
        $stmtAdminNotif->execute([$adminMessage]);
        
        $_SESSION['success'] = "Renewal request submitted successfully!";
        header('Location: tenant_dashboard.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error submitting renewal request: " . $e->getMessage();
        header('Location: tenant_dashboard.php');
        exit;
    }
} else {
    header('Location: tenant_dashboard.php');
    exit;
}
?>
