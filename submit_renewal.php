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

    }
    catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: tenant_dashboard.php');
        exit;
    }
} else {
    header('Location: tenant_dashboard.php');
    exit;
}
?>
