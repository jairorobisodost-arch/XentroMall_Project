<?php
session_start();
require 'config.php';
require_once 'contract_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
$currentPage = $_GET['page'] ?? '';

// Handle AJAX search request
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search' && isset($_GET['q'])) {
    $searchQuery = trim($_GET['q']);
    $results = [];
    
    if (!empty($searchQuery)) {
        // Search stalls
        try {
            $stmtStalls = $pdo->prepare("
                SELECT s.*, td.tradename, td.status as tenant_status 
                FROM stalls s
                LEFT JOIN tenant_details td ON s.id = td.stall_id AND td.user_id = ?
                WHERE (s.stall_number LIKE ? OR s.description LIKE ?) AND td.user_id = ?
                ORDER BY s.stall_number
                LIMIT 5
            ");
            $stmtStalls->execute([$userId, "%$searchQuery%", "%$searchQuery%", $userId]);
            $stalls = $stmtStalls->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($stalls as $stall) {
                $results['stalls'][] = [
                    'type' => 'stall',
                    'id' => $stall['id'],
                    'title' => $stall['stall_number'],
                    'description' => $stall['description'],
                    'tradename' => $stall['tradename'],
                    'status' => $stall['tenant_status'],
                    'monthly_rate' => $stall['monthly_rate'],
                    'floor_area' => $stall['floor_area']
                ];
            }
        } catch (PDOException $e) {
            // Ignore errors
        }
        
        // Search payments
        try {
            $stmtPayments = $pdo->prepare("
                SELECT p.*, s.stall_number 
                FROM payments p
                LEFT JOIN stalls s ON p.stall_id = s.id
                WHERE p.user_id = ? AND (
                    p.billing_month LIKE ? OR 
                    p.payment_method LIKE ? OR
                    s.stall_number LIKE ?
                )
                ORDER BY p.payment_date DESC
                LIMIT 5
            ");
            $stmtPayments->execute([$userId, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]);
            $payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($payments as $payment) {
                $results['payments'][] = [
                    'type' => 'payment',
                    'id' => $payment['id'],
                    'amount' => $payment['amount'],
                    'billing_month' => $payment['billing_month'],
                    'payment_date' => $payment['payment_date'],
                    'status' => $payment['status'],
                    'stall_number' => $payment['stall_number'],
                    'payment_method' => $payment['payment_method']
                ];
            }
        } catch (PDOException $e) {
            // Ignore errors
        }
        
        // Search contracts
        try {
            $stmtContracts = $pdo->prepare("
                SELECT tc.*, td.tradename, s.stall_number
                FROM tenant_contracts tc
                INNER JOIN tenant_details td ON td.id = tc.tenant_detail_id
                LEFT JOIN stalls s ON td.stall_id = s.id
                WHERE td.user_id = ? AND (
                    tc.contract_status LIKE ? OR
                    tc.version LIKE ? OR
                    s.stall_number LIKE ? OR
                    td.tradename LIKE ?
                )
                ORDER BY tc.created_at DESC
                LIMIT 5
            ");
            $stmtContracts->execute([$userId, "%$searchQuery%", "%$searchQuery%", "%$searchQuery%", "%$searchQuery%"]);
            $contracts = $stmtContracts->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($contracts as $contract) {
                $results['contracts'][] = [
                    'type' => 'contract',
                    'id' => $contract['id'],
                    'contract_status' => $contract['contract_status'],
                    'version' => $contract['version'],
                    'created_at' => $contract['created_at'],
                    'tradename' => $contract['tradename'],
                    'stall_number' => $contract['stall_number']
                ];
            }
        } catch (PDOException $e) {
            // Ignore errors
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Fetch tenant application status, stall_id, and start date
$stmtTenant = $pdo->prepare("SELECT td.status, td.stall_id, p.payment_date as start_date
                             FROM tenant_details td 
                             LEFT JOIN payments p ON td.user_id = p.user_id AND p.status = 'approved'
                             WHERE td.user_id = ? 
                             ORDER BY p.payment_date ASC 
                             LIMIT 1");
$stmtTenant->execute([$userId]);
$tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);

// Handle case when no tenant data is found
if (!$tenantData) {
    $tenantData = ['status' => 'N/A', 'stall_id' => null, 'start_date' => null];
}

$applicationStatus = $tenantData['status'] ?? 'N/A';
$stallId = $tenantData['stall_id'] ?? null;
$startDate = $tenantData['start_date'] ? date('F j, Y', strtotime($tenantData['start_date'])) : 'Not started yet';

// CHECK IF TENANT HAS APPROVED PAYMENT (Permission System)
$stmtApprovedPayment = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'approved'");
$stmtApprovedPayment->execute([$userId]);
$hasApprovedPayment = (int)$stmtApprovedPayment->fetchColumn() > 0;

// If new tenant (no payment) trying to access non-payment pages, redirect
if (!$hasApprovedPayment && !in_array($currentPage, ['', 'payment'])) {
    // Only allow Payment page access
    if ($currentPage !== '' && $currentPage !== 'payment') {
        $_SESSION['warning_message'] = 'Please complete your payment first to access other features.';
        header('Location: tenant_payment.php');
        exit;
    }
}

// Fetch stall details if stall_id exists
$stallDetails = null;
if ($stallId) {
    $stmtStall = $pdo->prepare("SELECT * FROM stalls WHERE id = ?");
    $stmtStall->execute([$stallId]);
    $stallDetails = $stmtStall->fetch(PDO::FETCH_ASSOC);
}

// Fetch tenant latest payment status
$stmtPaymentStatus = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? AND status IN ('approved', 'declined') ORDER BY payment_date DESC LIMIT 1");
$stmtPaymentStatus->execute([$userId]);
$paymentStatus = $stmtPaymentStatus->fetchColumn();

// If no approved/declined payment found, check for pending status
if (!$paymentStatus) {
    $stmtPending = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 1");
    $stmtPending->execute([$userId]);
    $paymentStatus = $stmtPending->fetchColumn() ?? 'N/A';
}

ensureTenantContractsTable($pdo);
$tenantContracts = [];
try {
    $stmtTenantContracts = $pdo->prepare("
        SELECT tc.id, tc.contract_status, tc.created_at, tc.version, tc.contract_path,
               td.tradename, s.stall_number, s.description as stall_description, s.monthly_rate
        FROM tenant_contracts tc
        INNER JOIN tenant_details td ON td.id = tc.tenant_detail_id
        LEFT JOIN stalls s ON td.stall_id = s.id
        WHERE td.user_id = ?
        ORDER BY tc.created_at DESC
    ");
    $stmtTenantContracts->execute([$userId]);
    $tenantContracts = $stmtTenantContracts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tenantContracts = [];
}

// Fetch contract information
$contractData = null;
$contractStatus = 'no_contract';
$daysRemaining = 0;
$canRenew = false;
$lateFee = 0;

// Fetch all tenant stalls for individual renewal checking
$tenantStalls = [];
try {
    $stmtTenantStalls = $pdo->prepare("
        SELECT td.id as tenant_detail_id, td.tradename, td.status as tenant_status, td.bir_expiry_date,
               s.id as stall_id, s.stall_number, s.description, s.monthly_rate, s.floor_area, s.image_path,
               tc.lease_start_date, tc.lease_expiration_date, tc.contract_status
        FROM tenant_details td
        LEFT JOIN stalls s ON td.stall_id = s.id
        LEFT JOIN tenant_lease_dates tld ON td.id = tld.tenant_detail_id
        LEFT JOIN tenant_contracts tc ON td.id = tc.tenant_detail_id
        WHERE td.user_id = ? AND td.status = 'approved'
        ORDER BY s.stall_number
    ");
    $stmtTenantStalls->execute([$userId]);
    $tenantStalls = $stmtTenantStalls->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tenantStalls = [];
}

// Fetch current renewal applications for each stall
$stallRenewalApplications = [];
foreach ($tenantStalls as $stall) {
    $stallId = $stall['stall_id'];
    
    // Check unified renewal requests
    $stmtUnified = $pdo->prepare("
        SELECT id, status, submitted_at, total_amount, request_type
        FROM unified_renewal_requests 
        WHERE user_id = ? AND stall_id = ? 
        ORDER BY submitted_at DESC LIMIT 1
    ");
    $stmtUnified->execute([$userId, $stallId]);
    $unifiedRenewal = $stmtUnified->fetch(PDO::FETCH_ASSOC);
    
    // Check old contract renewals
    $stmtContract = $pdo->prepare("
        SELECT id, status, submitted_at, total_amount
        FROM contract_renewals 
        WHERE user_id = ? AND stall_id = ? 
        ORDER BY submitted_at DESC LIMIT 1
    ");
    $stmtContract->execute([$userId, $stallId]);
    $contractRenewal = $stmtContract->fetch(PDO::FETCH_ASSOC);
    
    // Use unified renewal if available, otherwise use contract renewal
    $stallRenewalApplications[$stallId] = $unifiedRenewal ?: $contractRenewal;
}

// Check each stall for expiration and renewal eligibility
$stallRenewalStatuses = [];
$expiringSoonStalls = [];
$urgentRenewalStalls = [];
$expiredStalls = [];

foreach ($tenantStalls as $stall) {
    $stallId = $stall['stall_id'];
    $expirationDate = $stall['lease_expiration_date'];
    $currentRenewal = $stallRenewalApplications[$stallId] ?? null;
    
    // Check if there's an active renewal process
    if ($currentRenewal) {
        $renewalStatus = $currentRenewal['status'];
        
        if ($renewalStatus === 'pending') {
            // Pending admin approval
            $stallRenewalStatuses[$stallId] = [
                'status' => 'renewal_pending',
                'days_remaining' => 0,
                'can_renew' => false,
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate,
                'renewal_id' => $currentRenewal['id'],
                'renewal_type' => $currentRenewal['request_type'] ?? 'renewal'
            ];
        } elseif ($renewalStatus === 'approved' || $renewalStatus === 'payment_pending') {
            // Approved, waiting for payment
            $stallRenewalStatuses[$stallId] = [
                'status' => 'payment_pending',
                'days_remaining' => 0,
                'can_renew' => false,
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate,
                'renewal_id' => $currentRenewal['id'],
                'renewal_type' => $currentRenewal['request_type'] ?? 'renewal',
                'total_amount' => $currentRenewal['total_amount'] ?? 0
            ];
        } elseif ($renewalStatus === 'completed' || $paymentStatus === 'paid') {
            // Renewal completed - check if new expiration date is set
            if ($expirationDate) {
                $expDate = new DateTime($expirationDate);
                $today = new DateTime();
                $interval = $today->diff($expDate);
                $daysRemaining = $interval->days;
                
                if ($today > $expDate) {
                    // New contract already expired
                    $daysExpired = -$daysRemaining;
                    $stallRenewalStatuses[$stallId] = [
                        'status' => 'expired',
                        'days_remaining' => -$daysExpired,
                        'can_renew' => ($daysExpired <= 30),
                        'stall_number' => $stall['stall_number'],
                        'tradename' => $stall['tradename'],
                        'expiration_date' => $expirationDate
                    ];
                    $expiredStalls[] = $stallId;
                } elseif ($daysRemaining <= 7) {
                    // Urgent renewal needed for next cycle
                    $stallRenewalStatuses[$stallId] = [
                        'status' => 'urgent',
                        'days_remaining' => $daysRemaining,
                        'can_renew' => true,
                        'stall_number' => $stall['stall_number'],
                        'tradename' => $stall['tradename'],
                        'expiration_date' => $expirationDate
                    ];
                    $urgentRenewalStalls[] = $stallId;
                } elseif ($daysRemaining <= 30) {
                    // Expiring soon for next cycle
                    $stallRenewalStatuses[$stallId] = [
                        'status' => 'expiring_soon',
                        'days_remaining' => $daysRemaining,
                        'can_renew' => true,
                        'stall_number' => $stall['stall_number'],
                        'tradename' => $stall['tradename'],
                        'expiration_date' => $expirationDate
                    ];
                    $expiringSoonStalls[] = $stallId;
                } elseif ($daysRemaining <= 90) {
                    // Can renew for next cycle
                    $stallRenewalStatuses[$stallId] = [
                        'status' => 'renewal_available',
                        'days_remaining' => $daysRemaining,
                        'can_renew' => true,
                        'stall_number' => $stall['stall_number'],
                        'tradename' => $stall['tradename'],
                        'expiration_date' => $expirationDate
                    ];
                } else {
                    // Active, not yet ready for renewal
                    $stallRenewalStatuses[$stallId] = [
                        'status' => 'active',
                        'days_remaining' => $daysRemaining,
                        'can_renew' => false,
                        'stall_number' => $stall['stall_number'],
                        'tradename' => $stall['tradename'],
                        'expiration_date' => $expirationDate
                    ];
                }
            }
        }
        continue; // Skip expiration check if there's active renewal
    }
    
    // Normal expiration check if no active renewal
    if ($expirationDate) {
        $expDate = new DateTime($expirationDate);
        $today = new DateTime();
        $interval = $today->diff($expDate);
        $daysRemaining = $interval->days;
        
        // Determine renewal status for this specific stall
        if ($today > $expDate) {
            // Expired
            $daysExpired = -$daysRemaining;
            $stallRenewalStatuses[$stallId] = [
                'status' => 'expired',
                'days_remaining' => -$daysExpired,
                'can_renew' => ($daysExpired <= 30),
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate
            ];
            $expiredStalls[] = $stallId;
        } elseif ($daysRemaining <= 7) {
            // Urgent renewal needed
            $stallRenewalStatuses[$stallId] = [
                'status' => 'urgent',
                'days_remaining' => $daysRemaining,
                'can_renew' => true,
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate
            ];
            $urgentRenewalStalls[] = $stallId;
        } elseif ($daysRemaining <= 30) {
            // Expiring soon
            $stallRenewalStatuses[$stallId] = [
                'status' => 'expiring_soon',
                'days_remaining' => $daysRemaining,
                'can_renew' => true,
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate
            ];
            $expiringSoonStalls[] = $stallId;
        } elseif ($daysRemaining <= 90) {
            // Can renew
            $stallRenewalStatuses[$stallId] = [
                'status' => 'renewal_available',
                'days_remaining' => $daysRemaining,
                'can_renew' => true,
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate
            ];
        } else {
            // Active, not yet ready for renewal
            $stallRenewalStatuses[$stallId] = [
                'status' => 'active',
                'days_remaining' => $daysRemaining,
                'can_renew' => false,
                'stall_number' => $stall['stall_number'],
                'tradename' => $stall['tradename'],
                'expiration_date' => $expirationDate
            ];
        }
    }
}

// Get tenant_id from tenants table using user's email
$stmtUserEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUserEmail->execute([$userId]);
$userEmail = $stmtUserEmail->fetchColumn();

$stmtTenantId = $pdo->prepare("SELECT id as tenant_id FROM tenants WHERE email = ?");
$stmtTenantId->execute([$userEmail]);
$tenantIdData = $stmtTenantId->fetch();

if ($tenantIdData && $tenantIdData['tenant_id']) {
    $stmtContract = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
    $stmtContract->execute([$tenantIdData['tenant_id']]);
    $contractData = $stmtContract->fetch(PDO::FETCH_ASSOC);
    
    if ($contractData) {
        $expirationDate = new DateTime($contractData['lease_expiration_date']);
        $today = new DateTime();
        $interval = $today->diff($expirationDate);
        $daysRemaining = $interval->days;
        
        // Determine contract status
        if ($today > $expirationDate) {
            // Expired
            $daysRemaining = -$daysRemaining;
            if (abs($daysRemaining) <= 30) {
                $contractStatus = 'grace_period';
                $canRenew = true;
                // Get late fee from settings
                $stmtFee = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'late_renewal_fee'");
                $stmtFee->execute();
                $lateFee = $stmtFee->fetchColumn() ?: 500;
            } else {
                $contractStatus = 'terminated';
                $canRenew = false;
            }
        } elseif ($daysRemaining <= 30) {
            $contractStatus = 'expiring_soon';
            $canRenew = true;
        } elseif ($daysRemaining <= 7) {
            $contractStatus = 'urgent';
            $canRenew = true;
        } else {
            $contractStatus = 'active';
            $canRenew = ($daysRemaining <= 90); // Can renew 90 days before expiration
        }
    }
}

// Count approved stalls for additional stall button
$stmtApprovedCount = $pdo->prepare("SELECT COUNT(*) FROM tenant_details WHERE user_id = ? AND status = 'approved'");
$stmtApprovedCount->execute([$userId]);
$approvedStallsCount = $stmtApprovedCount->fetchColumn();

// Check for pending renewal request
$pendingRenewal = null;
$approvedRenewal = null;
if ($tenantIdData && $tenantIdData['tenant_id']) {
    // Check for pending renewal in both systems
    $stmtPendingRenewal = $pdo->prepare("
        SELECT id, status, submitted_at, total_amount, late_renewal_fee, 'contract' as source FROM contract_renewals WHERE tenant_id = ? AND status = 'pending'
        UNION ALL
        SELECT id, status, submitted_at, total_amount, late_renewal_fee, 'unified' as source FROM unified_renewal_requests WHERE user_id = ? AND status = 'pending'
        ORDER BY submitted_at DESC LIMIT 1
    ");
    $stmtPendingRenewal->execute([$tenantIdData['tenant_id'], $userId]);
    $pendingRenewal = $stmtPendingRenewal->fetch(PDO::FETCH_ASSOC);
    
    // Check for approved renewal (waiting for payment)
    $stmtApprovedRenewal = $pdo->prepare("
        SELECT id, status, submitted_at, total_amount, late_renewal_fee, 'contract' as source FROM contract_renewals WHERE tenant_id = ? AND status = 'approved' AND payment_proof IS NULL
        UNION ALL
        SELECT id, status, submitted_at, total_amount, late_renewal_fee, 'unified' as source FROM unified_renewal_requests WHERE user_id = ? AND status IN ('approved', 'payment_pending') AND payment_proof IS NULL
        ORDER BY submitted_at DESC LIMIT 1
    ");
    $stmtApprovedRenewal->execute([$tenantIdData['tenant_id'], $userId]);
    $approvedRenewal = $stmtApprovedRenewal->fetch(PDO::FETCH_ASSOC);

    // Check for verifying renewal (payment submitted, waiting for admin approval)
    $stmtVerifyingRenewal = $pdo->prepare("
        SELECT id, status, submitted_at, total_amount, late_renewal_fee, 'contract' as source FROM contract_renewals WHERE tenant_id = ? AND status IN ('approved', 'payment_pending') AND payment_proof IS NOT NULL
        UNION ALL
        SELECT id, status, submitted_at, total_amount, late_renewal_fee, 'unified' as source FROM unified_renewal_requests WHERE user_id = ? AND status IN ('approved', 'payment_pending', 'completed') AND payment_proof IS NOT NULL AND status != 'completed'
        ORDER BY submitted_at DESC LIMIT 1
    ");
    $stmtVerifyingRenewal->execute([$tenantIdData['tenant_id'], $userId]);
    $verifyingRenewal = $stmtVerifyingRenewal->fetch(PDO::FETCH_ASSOC);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal'])) {
    $tenantId = $_SESSION['user_id'] ?? '';
    $renewalDate = $_POST['renewal_date'] ?? '';
    
    if ($tenantId && $renewalDate) {
        try {
            $pdo->beginTransaction();

            // Insert renewal request
            $stmt = $pdo->prepare("INSERT INTO renewal_requests (id, renewal_date) VALUES (?, ?)");
            $stmt->execute([$tenantId, $renewalDate]);

            // Handle Extended BIR upload if provided
            $extendedBirPath = null;
            if (isset($_FILES['extended_bir']) && $_FILES['extended_bir']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/applications/' . $tenantId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $tmpName = $_FILES['extended_bir']['tmp_name'];
                $fileName = basename($_FILES['extended_bir']['name']);
                $targetFile = $uploadDir . uniqid() . '_extended_bir_' . $fileName;
                
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $extendedBirPath = $targetFile;
                    
                    // Insert or update extended BIR submission
                    try {
                        $stmt = $pdo->prepare("INSERT INTO application_submissions (user_id, extended_bir_registration) VALUES (?, ?) ON DUPLICATE KEY UPDATE extended_bir_registration = VALUES(extended_bir_registration)");
                        $stmt->execute([$tenantId, $extendedBirPath]);
                    } catch (PDOException $e) {
                        // If table doesn't exist or has different structure, try alternative approach
                        error_log("Extended BIR submission error: " . $e->getMessage());
                    }
                }
            }

            $pdo->commit();

            if ($extendedBirPath) {
                $_SESSION['renewal_success_message'] = "Renewal request submitted successfully with Extended BIR document.";
            } else {
                $_SESSION['renewal_success_message'] = "Renewal request submitted successfully.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['renewal_error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['renewal_error_message'] = "Please provide all required fields.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: tenant_dashboard.php?page=renewal");
    exit;
}

// Handle Extended BIR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_extended_bir'])) {
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? '';
    $businessName = $_POST['business_name'] ?? '';
    $tinNumber = $_POST['tin_number'] ?? '';
    $businessAddress = $_POST['business_address'] ?? '';
    $contactPerson = $_POST['contact_person'] ?? '';
    $contactNumber = $_POST['contact_number'] ?? '';
    $submissionType = $_POST['submission_type'] ?? 'application';
    
    // Get user email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn() ?? '';
    
    $uploadDir = 'uploads/extended_bir/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($_FILES['extended_bir_document']) && $_FILES['extended_bir_document']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['extended_bir_document']['tmp_name'];
        $fileName = basename($_FILES['extended_bir_document']['name']);
        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
        
        if (move_uploaded_file($tmpName, $targetFile)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO extended_bir (user_id, username, email, business_name, tin_number, business_address, contact_person, contact_number, document_path, submission_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $username, $userEmail, $businessName, $tinNumber, $businessAddress, $contactPerson, $contactNumber, $targetFile, $submissionType]);
                $_SESSION['bir_success_message'] = "Extended BIR Registration submitted successfully.";
            } catch (PDOException $e) {
                error_log("Database insert error (extended_bir): " . $e->getMessage());
                $_SESSION['bir_error_message'] = "There was an error submitting your extended BIR registration. Please try again later.";
            }
        } else {
            $_SESSION['bir_error_message'] = "Failed to upload extended BIR document.";
        }
    } else {
        $_SESSION['bir_error_message'] = "No extended BIR document uploaded or upload error.";
    }
    
    header("Location: tenant_dashboard.php?page=extended_bir");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Tenant Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root {
      --primary: #059669;
      --primary-dark: #047857;
      --primary-light: #10b981;
      --secondary: #0ea5e9;
      --accent: #f59e0b;
      --surface: #ffffff;
      --background: #f8fafc;
      --danger: #ef4444;
      --danger-dark: #dc2626;
      --warning: #f59e0b;
      --info: #3b82f6;
      --border-radius-sm: 8px;
      --border-radius-md: 12px;
      --border-radius-lg: 16px;
      --border-radius-xl: 20px;
      --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    body { 
      font-family: "Inter", sans-serif;
      background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
      margin: 0;
      padding: 0;
    }
    
    .scrollbar-thin::-webkit-scrollbar { height: 6px; width: 6px; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); border-radius: 8px; }
    .scrollbar-thin::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
    
    .gradient-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); }
    .gradient-card { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); }
    .hover-lift { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    
    .blue-gradient { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
    .green-gradient { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    
    /* Enhanced sidebar styling */
    #sidebar {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #sidebar .nav-item {
      position: relative;
      overflow: hidden;
      font-weight: 500;
      letter-spacing: 0.3px;
      transition: all 0.3s ease;
    }
    
    #sidebar .nav-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s;
    }
    
    #sidebar .nav-item:hover::before {
      left: 100%;
    }
    
    /* Enhanced header styling */
    header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    /* Enhanced card styling */
    .card {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .card:hover {
      box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }
    
    /* Enhanced button styling */
    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }
    
    /* Enhanced form elements */
    .form-input {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px 16px;
      transition: all 0.3s ease;
      background: #ffffff;
    }
    
    .form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
    }
    
    /* Enhanced modal styling */
    .modal {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    
    /* Enhanced table styling */
    .table-modern {
      border-collapse: separate;
      border-spacing: 0;
    }
    
    .table-modern thead th {
      background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
      color: white;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      padding: 1rem;
      border: none;
    }
    
    .table-modern thead th:first-child {
      border-top-left-radius: 12px;
    }
    
    .table-modern thead th:last-child {
      border-top-right-radius: 12px;
    }
    
    .table-modern tbody tr {
      background: white;
      transition: all 0.2s ease;
    }
    
    .table-modern tbody tr:hover {
      background: #f0fdf4;
      transform: scale(1.01);
    }
    
    .table-modern tbody td {
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
    }
    
    /* Status badges */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      padding: 0.375rem 0.875rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.025em;
    }
    
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved, .status-active { background: #d1fae5; color: #065f46; }
    .status-rejected, .status-declined { background: #fee2e2; color: #991b1b; }
    .status-expired { background: #f3f4f6; color: #374151; }
    .status-urgent { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
    .status-expiring { background: #fff7ed; color: #c2410c; }
    .status-info { background: #dbeafe; color: #1e40af; }
    
    /* Secondary button styling */
    .btn-secondary {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
      padding: 0.75rem 1.5rem;
      border-radius: var(--border-radius-md);
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .btn-secondary:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-2px);
    }
    
    /* Danger button styling */
    .btn-danger {
      background: linear-gradient(135deg, var(--danger) 0%, var(--danger-dark) 100%);
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: var(--border-radius-md);
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-sm);
      cursor: pointer;
    }
    
    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    
    /* Small button variant */
    .btn-sm {
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
    }
    
    /* Dashboard card - standardized card component */
    .dashboard-card {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius-lg);
      box-shadow: var(--shadow-md);
      transition: all 0.3s ease;
      padding: 1.5rem;
    }
    
    .dashboard-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-2px);
    }
    
    /* Section header styling */
    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e2e8f0;
    }
    
    .section-title {
      font-size: 1.25rem;
      font-weight: 700;
      color: #1f2937;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .section-title i {
      color: var(--primary);
    }
    
    /* Action link styling */
    .action-link {
      color: var(--primary);
      font-weight: 600;
      font-size: 0.875rem;
      text-decoration: none;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
    }
    
    .action-link:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }
    
    /* Quick action card styling */
    .quick-action {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: var(--border-radius-md);
      transition: all 0.3s ease;
      cursor: pointer;
    }
    
    .quick-action:hover {
      background: #f0fdf4;
      border-color: var(--primary);
      transform: translateX(4px);
    }
    
    .quick-action-icon {
      width: 48px;
      height: 48px;
      border-radius: var(--border-radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }
    
    /* Info card styling */
    .info-card {
      background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 100%);
      border: 1px solid #a7f3d0;
      border-radius: var(--border-radius-md);
      padding: 1rem;
    }
    
    /* Animations */
    .animate-fade-in {
      animation: fadeIn 0.5s ease-in-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 flex flex-col text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out fixed inset-y-0 left-0 z-40 lg:static lg:inset-auto">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-white/20">
        <div class="bg-white/20 p-2 rounded-2xl backdrop-blur-sm">
          <img alt="Logo" class="w-10 h-10 object-contain rounded-lg" src="img/logo.jpg" />
        </div>
        <span class="font-bold text-2xl tracking-tight">
          XentroMall
          <span class="ml-2 inline-block w-2.5 h-2.5 bg-green-400 rounded-full border-2 border-white" title="Online"></span>
        </span>
      </div>
      <nav class="flex flex-col flex-grow px-4 py-6 space-y-1 overflow-y-auto scrollbar-thin">
        <p class="text-white/80 text-sm font-semibold mb-2 px-2">Tenant Menu</p>
        
        <!-- Dashboard - Always accessible -->
        <a href="tenant_dashboard.php" class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === '') ? 'bg-white/20 text-white' : 'hover:bg-white/10'; ?>">
          <i class="fas fa-home w-5"></i>
          Dashboard
        </a>
        
        <!-- Area Management - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? '?page=space' : 'javascript:void(0)'; ?>" 
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === 'space') ? 'bg-white/20 text-white' : ($hasApprovedPayment ? 'hover:bg-white/10' : 'opacity-50 cursor-not-allowed'); ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-store w-5"></i>
          Area Management
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-orange-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
        
        <!-- Work Permit - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? 'work_permit_form.php' : 'javascript:void(0)'; ?>"
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo $hasApprovedPayment ? 'hover:bg-white/10' : 'opacity-50 cursor-not-allowed'; ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-hard-hat w-5"></i>
          Work Permit
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-orange-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
        
        <!-- Renewal - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? '?page=renewal' : 'javascript:void(0)'; ?>"
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === 'renewal') ? 'bg-white/20 text-white' : ($hasApprovedPayment ? 'hover:bg-white/10' : 'opacity-50 cursor-not-allowed'); ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-sync-alt w-5"></i>
          Renewal
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-orange-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
        
        <!-- Payment - Always accessible (PRIORITY) -->
        <a href="tenant_payment.php" class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo strpos($_SERVER['PHP_SELF'], 'tenant_payment.php') !== false ? 'bg-white/20 text-white' : 'hover:bg-white/10'; ?> <?php echo !$hasApprovedPayment ? 'font-bold bg-orange-600/30' : ''; ?>">
          <i class="fas fa-credit-card w-5"></i>
          Payment
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-orange-500 px-2 py-1 rounded font-semibold">PENDING</span>
          <?php endif; ?>
        </a>
        
        <!-- View Status - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? '?page=status' : 'javascript:void(0)'; ?>"
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="nav-item flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === 'status') ? 'bg-white/20 text-white' : ($hasApprovedPayment ? 'hover:bg-white/10' : 'opacity-50 cursor-not-allowed'); ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-info-circle w-5"></i>
          View Status
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-orange-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
      </nav>
    </aside>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden"></div>

    <!-- Main content -->
    <div class="flex flex-col flex-grow">
      <!-- Topbar -->
      <header class="flex items-center justify-between px-6 py-4 bg-emerald-700 text-white shadow">
        <button id="sidebarToggle" aria-label="Toggle menu" aria-expanded="false" class="text-white text-xl lg:hidden" type="button">
          <i class="fas fa-bars"></i>
        </button>
        <form id="searchForm" aria-label="Site search" class="hidden md:flex items-center bg-emerald-600 rounded-full px-4 py-2 w-full max-w-lg" role="search">
          <input id="searchInput" name="search" aria-label="Search input" class="bg-transparent placeholder:text-emerald-200 text-white text-sm focus:outline-none flex-grow" placeholder="Search stalls, payments, contracts..." type="search"/>
          <button aria-label="Search" class="text-emerald-200 hover:text-white ml-2" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </form>
        <div class="flex items-center gap-5 relative">
          <button id="notificationButton" aria-label="Notifications" class="relative text-white hover:text-emerald-100" type="button">
            <i class="far fa-bell text-lg"></i>
            <?php
            $userId = $_SESSION['user_id'] ?? 0;
            
            // Add stall renewal notifications to the query
            $renewalNotifications = [];
            foreach ($stallRenewalStatuses as $stallId => $status) {
                if ($status['status'] === 'urgent') {
                    $renewalNotifications[] = [
                        'id' => 'renewal_urgent_' . $stallId,
                        'message' => "URGENT: Stall {$status['stall_number']} expires in {$status['days_remaining']} days! Click to renew now.",
                        'type' => 'renewal',
                        'created_at' => date('Y-m-d H:i:s'),
                        'category' => 'Urgent Renewal'
                    ];
                } elseif ($status['status'] === 'expiring_soon') {
                    $renewalNotifications[] = [
                        'id' => 'renewal_soon_' . $stallId,
                        'message' => "Stall {$status['stall_number']} expires in {$status['days_remaining']} days. Renewal now available.",
                        'type' => 'renewal',
                        'created_at' => date('Y-m-d H:i:s'),
                        'category' => 'Renewal Reminder'
                    ];
                } elseif ($status['status'] === 'expired') {
                    $renewalNotifications[] = [
                        'id' => 'renewal_expired_' . $stallId,
                        'message' => "EXPIRED: Stall {$status['stall_number']} expired {$status['days_remaining']} days ago. Renew immediately!",
                        'type' => 'renewal',
                        'created_at' => date('Y-m-d H:i:s'),
                        'category' => 'Expired Stall'
                    ];
                }
            }
            
            $stmtNotifications = $pdo->prepare(
                "SELECT id, message, 'notif' AS type, created_at, NULL AS category FROM notifications WHERE user_id = ? AND is_read = 0
                 UNION ALL
                 SELECT CONCAT('ann_', id) AS id, CONCAT(title, ': ', description) AS message, 'announcement' AS type, created_at, category FROM announcements WHERE date >= CURDATE()
                 ORDER BY created_at DESC"
            );
            $stmtNotifications->execute([$userId]);
            $dbNotifications = $stmtNotifications->fetchAll();
            
            // Combine database notifications with renewal notifications
            $allNotifications = array_merge($renewalNotifications, $dbNotifications);
            
            // Sort by created_at descending
            usort($allNotifications, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            $notifications = $allNotifications;
            $unreadCount = count($notifications);
            if ($unreadCount > 0) {
                echo '<span id="notificationBadge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-orange-500 rounded-full text-[11px] leading-[18px] text-center border-2 border-emerald-700">' . $unreadCount . '</span>';
            }
            ?>
          </button>

          <!-- Notification Dropdown -->
          <div id="notificationDropdown" class="hidden absolute right-0 top-10 w-96 bg-white rounded-xl shadow-xl py-2 z-40 border border-gray-100 max-h-[480px] overflow-y-auto scrollbar-thin">
            <div class="sticky top-0 bg-white px-4 py-3 border-b border-gray-100 flex items-center justify-between">
              <h3 class="text-base font-semibold text-gray-900">Notifications</h3>
              <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full"><?php echo $unreadCount; ?> New</span>
            </div>
            <?php if ($unreadCount > 0): ?>
              <div class="divide-y divide-gray-100">
                <?php foreach ($notifications as $notification): ?>
                  <?php if ($notification['type'] === 'renewal'): ?>
                    <?php 
                    // Extract stall ID from notification ID
                    $stallId = str_replace(['renewal_urgent_', 'renewal_soon_', 'renewal_expired_'], '', $notification['id']);
                    $stallInfo = $stallRenewalStatuses[$stallId] ?? null;
                    ?>
                    <div class="notification-item px-4 py-3 hover:bg-orange-50 transition cursor-pointer border-l-4 border-orange-500" 
                         onclick="redirectToRenewal('<?php echo $stallId; ?>', '<?php echo htmlspecialchars($stallInfo['stall_number'] ?? ''); ?>')">
                      <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center">
                          <i class="fas fa-exclamation-triangle text-sm"></i>
                        </div>
                        <div class="flex-1">
                          <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-orange-900"><?php echo htmlspecialchars($notification['category'] ?? 'Renewal'); ?></p>
                            <span class="text-[11px] text-orange-500"><?php echo date('h:i A', strtotime($notification['created_at'] ?? 'now')); ?></span>
                          </div>
                          <p class="text-sm text-orange-700 mt-1 line-clamp-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                          <div class="mt-2">
                            <button class="text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700">
                              <i class="fas fa-redo mr-1"></i>Renew Now
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="notification-item px-4 py-3 hover:bg-gray-50 transition cursor-pointer" 
                         data-id="<?php echo htmlspecialchars($notification['id']); ?>" 
                         data-type="<?php echo htmlspecialchars($notification['type']); ?>"
                         onclick="showNotificationModal(
                           '<?php echo addslashes(htmlspecialchars($notification['category'] ?? 'Notification')); ?>',
                           '<?php echo addslashes(htmlspecialchars($notification['message'])); ?>',
                           '<?php echo date('M d, Y h:i A', strtotime($notification['created_at'] ?? 'now')); ?>'
                         )">
                      <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                          <i class="fas fa-bell text-sm"></i>
                        </div>
                        <div class="flex-1">
                          <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['category'] ?? 'Notification'); ?></p>
                            <span class="text-[11px] text-gray-500"><?php echo date('h:i A', strtotime($notification['created_at'] ?? 'now')); ?></span>
                          </div>
                          <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
                <button onclick="showAllNotifications()" class="w-full py-2 text-sm font-medium text-emerald-700 hover:text-emerald-800">View all notifications</button>
              </div>
            <?php else: ?>
              <div class="px-4 py-8 text-center">
                <div class="mx-auto w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-2">
                  <i class="far fa-bell text-xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 text-sm">No new notifications</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Notification Modal -->
          <div id="notificationModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
              <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Notification</h3>
                <button onclick="closeModal('notificationModal')" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times text-xl"></i>
                </button>
              </div>
              <div class="p-6 space-y-2 max-h-[70vh] overflow-y-auto scrollbar-thin">
                <span id="modalCategory" class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"></span>
                <p id="modalDate" class="text-sm text-gray-500"></p>
                <p id="modalMessage" class="text-gray-700 whitespace-pre-line"></p>
              </div>
              <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 text-right">
                <button onclick="closeModal('notificationModal')" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Close</button>
              </div>
            </div>
          </div>

          <!-- All Notifications Modal -->
          <div id="allNotificationsModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
              <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">All Notifications</h3>
                <button onclick="closeModal('allNotificationsModal')" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times text-xl"></i>
                </button>
              </div>
              <div class="overflow-y-auto flex-1 divide-y divide-gray-100 scrollbar-thin">
                <?php foreach ($notifications as $notification): ?>
                  <div class="p-4 hover:bg-gray-50 cursor-pointer" 
                       onclick="showNotificationModal(
                         '<?php echo addslashes(htmlspecialchars($notification['category'] ?? 'Notification')); ?>',
                         '<?php echo addslashes(htmlspecialchars($notification['message'])); ?>',
                         '<?php echo date('M d, Y h:i A', strtotime($notification['created_at'] ?? 'now')); ?>'
                       )">
                    <div class="flex items-start gap-3">
                      <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-bell text-sm"></i>
                      </div>
                      <div class="flex-1">
                        <div class="flex items-center justify-between">
                          <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['category'] ?? 'Notification'); ?></p>
                          <span class="text-[11px] text-gray-500"><?php echo date('M d, h:i A', strtotime($notification['created_at'] ?? 'now')); ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 text-right">
                <button onclick="closeModal('allNotificationsModal')" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Close</button>
              </div>
            </div>
          </div>

          <script>
            function redirectToRenewal(stallId, stallNumber) {
              // Close notification dropdown
              document.getElementById('notificationDropdown').classList.add('hidden');
              
              // Show confirmation dialog
              if (confirm(`Renew Stall ${stallNumber}? You will be redirected to the renewal page.`)) {
                // Redirect to renewal page with stall parameter
                window.location.href = `unified_renewal_form.php?stall_id=${stallId}&auto_select=true`;
              }
            }
            
            function showNotificationModal(category, message, date) {
              document.getElementById('modalCategory').textContent = category;
              document.getElementById('modalMessage').textContent = message;
              document.getElementById('modalDate').textContent = date;
              document.getElementById('notificationModal').classList.remove('hidden');
              document.body.style.overflow = 'hidden';
            }
            function showAllNotifications() {
              document.getElementById('allNotificationsModal').classList.remove('hidden');
              document.body.style.overflow = 'hidden';
            }
            function closeModal(modalId) {
              document.getElementById(modalId).classList.add('hidden');
              document.body.style.overflow = 'auto';
            }
            window.addEventListener('click', (event) => {
              if (event.target.id === 'notificationModal' || event.target.id === 'allNotificationsModal') {
                closeModal(event.target.id);
              }
            });
            document.addEventListener('keydown', (event) => {
              if (event.key === 'Escape') {
                closeModal('notificationModal');
                closeModal('allNotificationsModal');
              }
            });
          </script>

          <div class="relative" id="userMenuButton" tabindex="0" aria-haspopup="true" aria-expanded="false">
            <div class="flex items-center gap-2 cursor-pointer group">
              <div class="relative">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-medium shadow">
                  <?php echo strtoupper(substr(htmlspecialchars($_SESSION['username'] ?? 'U'), 0, 1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-emerald-700"></span>
              </div>
              <div class="text-left hidden sm:block">
                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
                <p class="text-xs text-emerald-200">Tenant</p>
              </div>
              <i class="fas fa-chevron-down text-white text-xs transition-transform duration-200 group-hover:rotate-180"></i>
            </div>
            <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl py-2 z-30 border border-gray-100">
              <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm text-gray-500">Signed in as</p>
                <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
              </div>
              <a href="tenant_settings.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-cog w-5 text-gray-400 mr-3"></i>
                <span>Settings</span>
              </a>
              <div class="border-t border-gray-100 my-1"></div>
              <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>
          <script>
            const userMenuButton = document.getElementById('userMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            userMenuButton.addEventListener('click', () => {
              userDropdown.classList.toggle('hidden');
              const expanded = userMenuButton.getAttribute('aria-expanded') === 'true';
              userMenuButton.setAttribute('aria-expanded', (!expanded).toString());
            });

            const notificationButton = document.getElementById('notificationButton');
            const notificationDropdown = document.getElementById('notificationDropdown');
            notificationButton.addEventListener('click', (e) => {
              e.stopPropagation();
              notificationDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', (event) => {
              if (!userMenuButton.contains(event.target)) {
                userDropdown.classList.add('hidden');
                userMenuButton.setAttribute('aria-expanded', 'false');
              }
              if (!notificationButton.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
              }
            });

            // Mark notification as read on click
            document.querySelectorAll('.notification-item').forEach(item => {
              item.addEventListener('click', () => {
                const notificationId = item.getAttribute('data-id');
                const notificationType = item.getAttribute('data-type');
                if (notificationType === 'notif') {
                  fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(notificationId)
                  }).then(response => {
                    if (response.ok) {
                      item.remove();
                      const badge = document.getElementById('notificationBadge');
                      if (badge) badge.remove();
                      if (document.querySelectorAll('.notification-item').length === 0) {
                        notificationDropdown.innerHTML = '<div class="px-4 py-6 text-center text-gray-500">No new notifications</div>';
                      }
                    }
                  });
                } else {
                  item.remove();
                  const badge = document.getElementById('notificationBadge');
                  if (badge && document.querySelectorAll('.notification-item').length === 0) {
                    notificationDropdown.innerHTML = '<div class="px-4 py-6 text-center text-gray-500">No new notifications</div>';
                    badge.remove();
                  }
                }
              });
            });
          </script>
        </div>
      </header>

      <!-- Search Results -->
      <div id="searchResults" class="hidden px-6 py-4 bg-emerald-50 border-b border-emerald-200">
        <div class="max-w-6xl mx-auto">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
              <i class="fas fa-search text-emerald-600 mr-2"></i> Search Results
            </h3>
            <button onclick="clearSearch()" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times"></i> Clear
            </button>
          </div>
          <div id="searchResultsContent" class="space-y-4">
            <!-- Search results will be displayed here -->
          </div>
        </div>
      </div>

      <!-- Content -->
      <main class="p-6 space-y-6 overflow-auto scrollbar-thin">
        <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Stall Renewal Management Section -->
        <?php if (!empty($tenantStalls)): ?>
          <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4">
              <h2 class="text-xl font-bold text-white flex items-center">
                <i class="fas fa-store mr-3"></i>
                My Stalls - Renewal Management
              </h2>
              <p class="text-emerald-100 text-sm mt-1">Complete renewal cycle: Application → Approval → Payment → New Cycle</p>
            </div>
            
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($tenantStalls as $stall): 
                  $stallId = $stall['stall_id'];
                  $renewalStatus = $stallRenewalStatuses[$stallId] ?? ['status' => 'unknown', 'days_remaining' => 0, 'can_renew' => false];
                  $currentRenewal = $stallRenewalApplications[$stallId] ?? null;
                  
                  $statusColor = match($renewalStatus['status']) {
                    'expired' => 'orange',
                    'urgent' => 'orange', 
                    'expiring_soon' => 'orange',
                    'renewal_available' => 'yellow',
                    'active' => 'green',
                    'renewal_pending' => 'blue',
                    'payment_pending' => 'purple',
                    default => 'gray'
                  };
                  $statusIcon = match($renewalStatus['status']) {
                    'expired' => 'fa-times-circle',
                    'urgent' => 'fa-exclamation-triangle',
                    'expiring_soon' => 'fa-clock',
                    'renewal_available' => 'fa-sync-alt',
                    'active' => 'fa-check-circle',
                    'renewal_pending' => 'fa-hourglass-half',
                    'payment_pending' => 'fa-credit-card',
                    default => 'fa-question-circle'
                  };
                  $statusText = match($renewalStatus['status']) {
                    'expired' => 'Expired',
                    'urgent' => 'Urgent Renewal',
                    'expiring_soon' => 'Expiring Soon',
                    'renewal_available' => 'Ready to Renew',
                    'active' => 'Active',
                    'renewal_pending' => 'Pending Approval',
                    'payment_pending' => 'Payment Pending',
                    default => 'Unknown'
                  };
                ?>
                  <div class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-all duration-300 <?php echo in_array($renewalStatus['status'], ['urgent', 'expired', 'payment_pending']) ? 'ring-2 ring-orange-200' : ''; ?>">
                    <!-- Stall Header -->
                    <div class="bg-gradient-to-r from-<?php echo $statusColor; ?>-50 to-<?php echo $statusColor; ?>-100 px-4 py-3 border-b border-<?php echo $statusColor; ?>-200">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center">
                          <div class="w-10 h-10 bg-<?php echo $statusColor; ?>-500 rounded-full flex items-center justify-center text-white">
                            <i class="fas fa-store text-sm"></i>
                          </div>
                          <div class="ml-3">
                            <h3 class="font-bold text-gray-900">Stall <?php echo htmlspecialchars($stall['stall_number']); ?></h3>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($stall['tradename']); ?></p>
                          </div>
                        </div>
                        <div class="text-right">
                          <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                            <i class="fas <?php echo $statusIcon; ?> mr-1"></i>
                            <?php echo $statusText; ?>
                          </span>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Stall Details -->
                    <div class="p-4 space-y-3">
                      <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Monthly Rate:</span>
                        <span class="font-semibold text-gray-900">₱<?php echo number_format($stall['monthly_rate'], 2); ?></span>
                      </div>
                      
                      <?php if ($stall['lease_expiration_date']): ?>
                        <div class="flex justify-between text-sm">
                          <span class="text-gray-600">Expiration:</span>
                          <span class="font-medium <?php echo $renewalStatus['status'] === 'expired' ? 'text-orange-600' : 'text-gray-900'; ?>">
                            <?php echo date('M d, Y', strtotime($stall['lease_expiration_date'])); ?>
                          </span>
                        </div>
                        
                        <?php if ($renewalStatus['status'] !== 'renewal_pending' && $renewalStatus['status'] !== 'payment_pending'): ?>
                          <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Days Remaining:</span>
                            <span class="font-medium <?php 
                              echo $renewalStatus['status'] === 'expired' ? 'text-orange-600' : 
                                   ($renewalStatus['status'] === 'urgent' ? 'text-orange-600' : 
                                   ($renewalStatus['status'] === 'expiring_soon' ? 'text-orange-600' : 'text-gray-900')); 
                            ?>">
                              <?php 
                              if ($renewalStatus['status'] === 'expired') {
                                echo $renewalStatus['days_remaining'] . ' days ago';
                              } else {
                                echo $renewalStatus['days_remaining'] . ' days';
                              }
                              ?>
                            </span>
                          </div>
                        <?php endif; ?>
                      <?php endif; ?>

                      <div class="flex justify-between text-sm pt-2 border-t border-gray-100">
                        <span class="text-gray-600">BIR Reg. Expiry:</span>
                        <span class="font-medium <?php 
                          if ($stall['bir_expiry_date']) {
                            $birExp = (new DateTime($stall['bir_expiry_date']))->setTime(0,0,0);
                            $today = (new DateTime())->setTime(0,0,0);
                            if ($today > $birExp) echo 'text-red-600 font-bold';
                            elseif ($today == $birExp) echo 'text-orange-600 font-bold';
                            else echo 'text-gray-900';
                          } else {
                            echo 'text-gray-400 italic';
                          }
                        ?>">
                          <?php 
                            if ($stall['bir_expiry_date']) {
                              $birDate = (new DateTime($stall['bir_expiry_date']))->setTime(0,0,0);
                              $today = (new DateTime())->setTime(0,0,0);
                              $diff = $today->diff($birDate);
                              $days = (int)$diff->format('%r%a');
                              
                              $dateStr = date('M d, Y', strtotime($stall['bir_expiry_date']));
                              if ($days < 0) {
                                echo "$dateStr (" . abs($days) . " days ago)";
                              } else {
                                echo "$dateStr ($days days)";
                              }
                            } else {
                              echo 'Not Set';
                            }
                          ?>
                       </span>
                      </div>
                      
                      <!-- Standalone BIR Update Button -->
                      <div class="pt-2">
                        <button onclick="openBIRUpdateModal('<?php echo htmlspecialchars($stall['stall_number']); ?>')" 
                                class="w-full px-4 py-2 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors font-medium text-xs flex items-center justify-center gap-2">
                          <i class="fas fa-file-upload"></i>
                          Update BIR Document
                        </button>
                      </div>
                      
                      <!-- Renewal Progress Indicator -->
                      <?php if ($currentRenewal): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                          <div class="text-xs font-medium text-blue-800 mb-2">Renewal Progress:</div>
                          <div class="flex items-center justify-between text-xs">
                            <span class="flex items-center <?php echo in_array($currentRenewal['status'], ['pending', 'approved', 'payment_pending', 'completed']) ? 'text-green-600' : 'text-gray-400'; ?>">
                              <i class="fas fa-file-alt mr-1"></i>Application
                            </span>
                            <span class="flex items-center <?php echo in_array($currentRenewal['status'], ['approved', 'payment_pending', 'completed']) ? 'text-green-600' : 'text-gray-400'; ?>">
                              <i class="fas fa-check mr-1"></i>Approval
                            </span>
                            <span class="flex items-center <?php echo in_array($currentRenewal['status'], ['completed']) ? 'text-green-600' : 'text-gray-400'; ?>">
                              <i class="fas fa-credit-card mr-1"></i>Payment
                            </span>
                          </div>
                        </div>
                      <?php endif; ?>
                      
                      <!-- Action Buttons -->
                      <?php if ($renewalStatus['status'] === 'payment_pending'): ?>
                        <button onclick="window.location.href='submit_renewal_payment.php?renewal_id=<?php echo $renewalStatus['renewal_id']; ?>&type=<?php echo $renewalStatus['renewal_type']; ?>'" 
                                class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium text-sm">
                          <i class="fas fa-credit-card mr-2"></i>Complete Payment (₱<?php echo number_format($renewalStatus['total_amount']); ?>)
                        </button>
                      <?php elseif ($renewalStatus['status'] === 'renewal_pending'): ?>
                        <div class="text-center">
                          <span class="text-xs text-blue-600">
                            <i class="fas fa-hourglass-half mr-1"></i>
                            Waiting for admin approval
                          </span>
                        </div>
                      <?php elseif ($renewalStatus['can_renew']): ?>
                        <button onclick="redirectToRenewal('<?php echo $stallId; ?>', '<?php echo htmlspecialchars($stall['stall_number']); ?>')" 
                                class="w-full mt-3 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium text-sm">
                          <i class="fas fa-redo mr-2"></i>Start New Renewal
                        </button>
                      <?php elseif ($renewalStatus['status'] === 'active'): ?>
                        <div class="text-center mt-3">
                          <span class="text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Available in <?php echo max(0, $renewalStatus['days_remaining'] - 90); ?> days
                          </span>
                        </div>
                      <?php else: ?>
                        <div class="text-center mt-3">
                          <span class="text-xs text-gray-500">
                            <i class="fas fa-lock mr-1"></i>
                            <?php echo $renewalStatus['status'] === 'expired' && $renewalStatus['days_remaining'] > 30 ? 'Renewal period ended' : 'Not available'; ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              
              <!-- Summary Stats -->
              <div class="mt-6 pt-6 border-t border-gray-200">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-gray-900"><?php echo count($tenantStalls); ?></div>
                    <div class="text-sm text-gray-600">Total Stalls</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo count(array_filter($stallRenewalStatuses, fn($s) => $s['status'] === 'renewal_pending')); ?></div>
                    <div class="text-sm text-gray-600">Pending</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600"><?php echo count(array_filter($stallRenewalStatuses, fn($s) => $s['status'] === 'payment_pending')); ?></div>
                    <div class="text-sm text-gray-600">Payment</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600"><?php echo count($urgentRenewalStalls) + count($expiredStalls); ?></div>
                    <div class="text-sm text-gray-600">Need Action</div>
                  </div>
                  <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo count(array_filter($stallRenewalStatuses, fn($s) => $s['status'] === 'active')); ?></div>
                    <div class="text-sm text-gray-600">Active</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
        
        <?php
          $page = $_GET['page'] ?? '';
          switch ($page) {
            case 'renewal':
                $successMessage = $_SESSION['success_message'] ?? '';
                $errorMessage = $_SESSION['error_message'] ?? '';
                $warningMessage = $_SESSION['warning_message'] ?? '';
                unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['warning_message']);
        ?>
              <div class="dashboard-card max-w-4xl mx-auto">
                <div class="section-header">
                  <h1 class="section-title">
                    <i class="fas fa-sync-alt"></i>Renewal / New Application
                  </h1>
                </div>
                <p class="text-gray-600 mb-6">Submit your renewal request or new application with required documents</p>

                <?php if (!empty($successMessage)): ?>
                  <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 border border-emerald-200 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($successMessage); ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($errorMessage)): ?>
                  <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 border border-red-200 flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errorMessage); ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($warningMessage)): ?>
                  <div class="mb-4 rounded-lg bg-orange-50 text-orange-700 px-4 py-3 border border-orange-200 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($warningMessage); ?>
                  </div>
                <?php endif; ?>

                <!-- Request Type Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-semibold text-blue-900 mb-2">
                      <i class="fas fa-file-alt mr-2"></i>New Application
                    </h3>
                    <ul class="text-sm text-blue-800 space-y-1">
                      <li>✓ 3-month advance payment</li>
                      <li>✓ New account created</li>
                      <li>✓ Stall selection required</li>
                    </ul>
                  </div>
                  <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                    <h3 class="font-semibold text-emerald-900 mb-2">
                      <i class="fas fa-sync-alt mr-2"></i>Renewal Request
                    </h3>
                    <ul class="text-sm text-emerald-800 space-y-1">
                      <li>✓ Monthly payment only</li>
                      <li>✓ Use existing account</li>
                      <li>✓ Stall pre-filled</li>
                    </ul>
                  </div>
                </div>

                <!-- Action Button -->
                <div class="text-center">
                  <?php if ($pendingRenewal): ?>
                    <button disabled class="inline-block px-8 py-3 bg-gray-400 text-white rounded-lg font-semibold shadow cursor-not-allowed opacity-75">
                      <i class="fas fa-hourglass-half mr-2"></i>Renewal / Application Pending
                    </button>
                    <p class="text-xs text-orange-600 mt-2 font-medium">
                      <i class="fas fa-info-circle mr-1"></i>
                      You already have a request in progress. Please wait for admin review.
                    </p>
                  <?php elseif ($verifyingRenewal): ?>
                    <button disabled class="inline-block px-8 py-3 bg-blue-400 text-white rounded-lg font-semibold shadow cursor-not-allowed opacity-75">
                      <i class="fas fa-search-dollar mr-2"></i>Payment Under Review
                    </button>
                    <p class="text-xs text-blue-600 mt-2 font-medium">
                      <i class="fas fa-info-circle mr-1"></i>
                      Your payment proof is being reviewed by the administration.
                    </p>
                  <?php elseif ($approvedRenewal): ?>
                    <button disabled class="inline-block px-8 py-3 bg-emerald-400 text-white rounded-lg font-semibold shadow cursor-not-allowed opacity-75">
                      <i class="fas fa-check-circle mr-2"></i>Renewal Approved
                    </button>
                    <p class="text-xs text-emerald-600 mt-2 font-medium">
                      <i class="fas fa-info-circle mr-1"></i>
                      Your request is approved! Please proceed to the payment section below.
                    </p>
                  <?php else: ?>
                    <a href="unified_renewal_form.php" class="inline-block px-8 py-3 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-700 hover:to-emerald-600 text-white rounded-lg font-semibold shadow-lg transition transform hover:scale-105">
                      <i class="fas fa-arrow-right mr-2"></i>Start Renewal / Application
                    </a>
                  <?php endif; ?>
                </div>

                <!-- Pending Renewal Status -->
                <?php if ($pendingRenewal): ?>
                  <div class="mt-8 p-5 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="font-semibold text-blue-900 mb-3">
                      <i class="fas fa-hourglass-half mr-2"></i>Pending Renewal Request
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                      <div>
                        <span class="text-blue-700 font-medium">Submitted:</span>
                        <p class="text-blue-900 font-semibold"><?php echo date('F j, Y', strtotime($pendingRenewal['submitted_at'])); ?></p>
                      </div>
                      <div>
                        <span class="text-blue-700 font-medium">Total Amount:</span>
                        <p class="text-blue-900 font-semibold">₱<?php echo number_format($pendingRenewal['total_amount'], 2); ?></p>
                      </div>
                      <div>
                        <span class="text-blue-700 font-medium">Status:</span>
                        <p class="text-blue-900 font-semibold"><?php echo ucfirst($pendingRenewal['status']); ?></p>
                      </div>
                      <?php if ($pendingRenewal['late_renewal_fee'] > 0): ?>
                        <div class="md:col-span-3">
                          <span class="text-blue-700 font-medium">Late Fee:</span>
                          <p class="text-red-600 font-semibold">₱<?php echo number_format($pendingRenewal['late_renewal_fee'], 2); ?></p>
                        </div>
                      <?php endif; ?>
                    </div>
                    <p class="mt-3 text-sm text-blue-800">
                      <i class="fas fa-info-circle mr-1"></i>
                      Your request is being reviewed by admin. You will be notified once it's processed.
                    </p>
                  </div>
                <?php endif; ?>

                <!-- Approved Renewal - Payment Required -->
                <?php if ($approvedRenewal): ?>
                    <div class="mt-8 p-5 bg-green-50 border border-green-200 rounded-lg">
                      <h3 class="font-semibold text-green-900 mb-3">
                        <i class="fas fa-check-circle mr-2"></i>Renewal Approved - Payment Required
                      </h3>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm mb-4">
                        <div>
                          <span class="text-green-700 font-medium">Approved On:</span>
                          <p class="text-green-900 font-semibold"><?php echo date('F j, Y', strtotime($approvedRenewal['submitted_at'])); ?></p>
                        </div>
                        <div>
                          <span class="text-green-700 font-medium">Total Amount:</span>
                          <p class="text-green-900 font-semibold">₱<?php echo number_format($approvedRenewal['total_amount'], 2); ?></p>
                        </div>
                        <?php if ($approvedRenewal['late_renewal_fee'] > 0): ?>
                          <div class="md:col-span-2">
                            <span class="text-green-700 font-medium">Late Fee:</span>
                            <p class="text-red-600 font-semibold">₱<?php echo number_format($approvedRenewal['late_renewal_fee'], 2); ?></p>
                          </div>
                        <?php endif; ?>
                      </div>
                      
                      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                        <p class="text-sm text-yellow-800">
                          <i class="fas fa-exclamation-triangle mr-1"></i>
                          <strong>Payment Required:</strong> Please complete your payment to finalize the renewal.
                        </p>
                      </div>

                      <div class="flex gap-3">
                        <form action="submit_renewal_payment.php" method="GET" class="flex-1">
                          <input type="hidden" name="renewal_id" value="<?php echo $approvedRenewal['id']; ?>">
                          <input type="hidden" name="amount" value="<?php echo $approvedRenewal['total_amount']; ?>">
                          <input type="hidden" name="type" value="<?php echo $approvedRenewal['source'] === 'unified' ? 'unified_renewal' : 'renewal'; ?>">
                          <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                            <i class="fas fa-credit-card mr-2"></i>Pay Now
                          </button>
                        </form>
                      </div>
                    </div>
                <?php endif; ?>

                <!-- Contract Status -->
                <?php if ($contractStatus === 'terminated'): ?>
                  <div class="mt-8 p-5 bg-red-50 border border-red-200 rounded-lg text-center">
                    <p class="text-red-700 font-semibold">
                      <i class="fas fa-exclamation-triangle mr-2"></i>
                      Your contract has been terminated. Please contact the admin for assistance.
                    </p>
                  </div>
                <?php elseif ($contractData && $canRenew): ?>
                  <div class="mt-8 p-5 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h3 class="font-semibold text-yellow-900 mb-2">
                      <i class="fas fa-calendar-alt mr-2"></i>Contract Status
                    </h3>
                    <p class="text-sm text-yellow-800">
                      Your contract expires on <strong><?php echo date('F j, Y', strtotime($contractData['lease_expiration_date'])); ?></strong>
                      (<?php echo $daysRemaining; ?> days remaining)
                    </p>
                  </div>
                <?php endif; ?>
              </div>
        <?php
              break;

            case 'payment':
                $paymentSuccessMessage = '';
                $paymentErrorMessage = '';
                $userId = $_SESSION['user_id'] ?? null;
                $waterBill = 0; $electricBill = 0; $monthlyRate = 0;
                if ($userId) {
                    // Prefer per-tenant expenses if available
                    try {
                        $stmt = $pdo->prepare("SELECT water_bill, electric_bill FROM tenant_expenses WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$userId]);
                        $tExpense = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $tExpense = null; // table might not exist yet; fallback below
                    }
                    if ($tExpense) {
                        $waterBill = (float)($tExpense['water_bill'] ?? 0);
                        $electricBill = (float)($tExpense['electric_bill'] ?? 0);
                    }
                    // No fallback - bills will be 0 until admin sets them

                    $stmt = $pdo->prepare("SELECT stall_id FROM tenant_details WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($tenant) {
                        $stallId = $tenant['stall_id'];
                        $stmt = $pdo->prepare("SELECT monthly_rate FROM stalls WHERE id = ?");
                        $stmt->execute([$stallId]);
                        $stall = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($stall) { $monthlyRate = (float)$stall['monthly_rate']; }
                    }
                }
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
                    // Validate that user exists in database
                    $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $stmtCheckUser->execute([$userId]);
                    if (!$stmtCheckUser->fetch()) {
                        $paymentErrorMessage = "Invalid user session. Please logout and login again.";
                    } elseif (isset($_FILES['payment_image']) && $_FILES['payment_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/payments/' . $userId . '/';
                        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                        $tmpName = $_FILES['payment_image']['tmp_name'];
                        $fileName = basename($_FILES['payment_image']['name']);
                        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
                        if (move_uploaded_file($tmpName, $targetFile)) {
                            try {
                                $stmtInsert = $pdo->prepare("INSERT INTO payments (user_id, payment_image) VALUES (?, ?)");
                                $stmtInsert->execute([$userId, $targetFile]);
                                $paymentSuccessMessage = "Payment image uploaded successfully.";
                            } catch (PDOException $e) {
                                $paymentErrorMessage = "Database error: " . $e->getMessage();
                            }
                        } else { $paymentErrorMessage = "Failed to move uploaded file."; }
                    } else { $paymentErrorMessage = "Please select a valid image file."; }
                }
        ?>
              <div class="dashboard-card max-w-lg mx-auto">
                <div class="section-header mb-4">
                  <h1 class="section-title"><i class="fas fa-credit-card"></i>Upload Payment Image</h1>
                </div>
                <p class="text-sm text-gray-500 mb-4">Please upload your proof of payment for processing.</p>

                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-6">
                  <p class="font-medium mb-1">Payment Remarks</p>
                  <ul class="list-disc list-inside text-sm">
                    <li>Rent</li>
                    <li>Water Bill</li>
                    <li>Electricity Bill</li>
                  </ul>
                </div>

                <?php if ($paymentSuccessMessage): ?>
                  <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 border border-emerald-200" role="alert">
                    <?php echo htmlspecialchars($paymentSuccessMessage); ?>
                  </div>
                <?php endif; ?>
                <?php if ($paymentErrorMessage): ?>
                  <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 border border-red-200" role="alert">
                    <?php echo htmlspecialchars($paymentErrorMessage); ?>
                  </div>
                <?php endif; ?>

                <form action="" method="post" enctype="multipart/form-data" class="space-y-5">
                  <div class="space-y-3 text-sm">
                    <p class="flex items-center gap-2"><i class="fas fa-tint text-blue-500"></i> <strong>Water Bill:</strong> ₱<span id="waterBill"><?php echo $waterBill; ?></span></p>
                    <p class="flex items-center gap-2"><i class="fas fa-bolt text-yellow-500"></i> <strong>Electric Bill:</strong> ₱<span id="electricBill"><?php echo $electricBill; ?></span></p>
                    <p class="flex items-center gap-2"><i class="fas fa-wallet text-emerald-600"></i> <strong>Monthly Rent:</strong> ₱<span id="monthlyRate"><?php echo $monthlyRate; ?></span></p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rent Payment Type</label>
                    <select id="paymentType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                      <option value="full">Full Payment</option>
                      <option value="partial">Partial Payment (Installment - Pay in 3)</option>
                    </select>
                  </div>

                  <p class="text-lg font-semibold">Total Amount: ₱<span id="totalAmount">0.00</span></p>

                  <div>
                    <label for="payment_image" class="block text-sm font-medium text-gray-700 mb-1">Upload Proof of Payment</label>
                    <input type="file" name="payment_image" id="payment_image" required class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                  </div>

                  <button type="submit" name="submit_payment" class="btn-primary w-full md:w-auto"><i class="fas fa-upload mr-2"></i>Upload Image</button>
                </form>

                <script>
                  document.addEventListener('DOMContentLoaded', function () {
                    const waterBill = parseFloat(document.getElementById('waterBill').textContent) || 0;
                    const electricBill = parseFloat(document.getElementById('electricBill').textContent) || 0;
                    const monthlyRate = parseFloat(document.getElementById('monthlyRate').textContent) || 0;
                    const paymentType = document.getElementById('paymentType');
                    const totalAmount = document.getElementById('totalAmount');
                    function computeTotal() {
                      let rent = paymentType.value === 'partial' ? monthlyRate / 3 : monthlyRate;
                      let total = rent + waterBill + electricBill;
                      totalAmount.textContent = total.toFixed(2);
                    }
                    paymentType.addEventListener('change', computeTotal);
                    computeTotal();
                  });
                </script>
              </div>
        <?php
              break;

            
            case 'space':
        ?>
              <?php if ($stallDetails): ?>
                <div class="dashboard-card max-w-2xl mx-auto">
                  <div class="section-header mb-4">
                    <h1 class="section-title"><i class="fas fa-store"></i>Area Details</h1>
                  </div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                      <p class="text-gray-500">Stall Number</p>
                      <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($stallDetails['stall_number']); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-500">Floor Area</p>
                      <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($stallDetails['floor_area']); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-500">Monthly Rate</p>
                      <p class="text-gray-900 font-medium">₱<?php echo number_format($stallDetails['monthly_rate'], 2); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-500">Status</p>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $stallDetails['status'] == 'available' ? 'bg-emerald-100 text-emerald-800' : ($stallDetails['status'] == 'reserved' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $stallDetails['status'])); ?>
                      </span>
                    </div>
                    <div class="md:col-span-2">
                      <p class="text-gray-500">Description</p>
                      <p class="text-gray-900"><?php echo htmlspecialchars($stallDetails['description']); ?></p>
                    </div>
                    <?php if (!empty($stallDetails['image_path'])): ?>
                      <div class="md:col-span-2">
                        <p class="text-gray-500">Stall Image</p>
                        <img src="<?php echo htmlspecialchars($stallDetails['image_path']); ?>" alt="Stall Image" class="mt-2 rounded-lg border border-gray-200 max-h-60 object-cover w-full">
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="mt-6">
                    <a href="tenant_payment.php" class="btn-primary inline-flex items-center">
                      <i class="fas fa-money-bill-wave mr-2"></i>Make Payment
                    </a>
                  </div>
                </div>
              <?php elseif ($applicationStatus === 'approved'): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg max-w-md mx-auto">
                  Your application has been approved but no stall has been assigned yet. Please contact support.
                </div>
              <?php endif; ?>
        <?php
              break;

            case 'status':
        ?>
              <div class="dashboard-card max-w-4xl mx-auto">
                <div class="section-header">
                  <h1 class="section-title"><i class="fas fa-user-check"></i>Your Account Status</h1>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  <!-- Status Cards -->
                  <div class="lg:col-span-2 space-y-4">
                    <!-- Application Status -->
                    <div class="flex items-center justify-between p-4 rounded-lg border <?php 
                      echo $applicationStatus === 'approved' ? 'bg-emerald-50 border-emerald-200' :
                           ($applicationStatus === 'pending' ? 'bg-yellow-50 border-yellow-200' :
                           ($applicationStatus === 'declined' ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'));
                    ?>">
                      <div class="flex items-center">
                        <div class="p-2 rounded-full mr-3 <?php 
                          echo $applicationStatus === 'approved' ? 'bg-emerald-100 text-emerald-600' :
                               ($applicationStatus === 'pending' ? 'bg-yellow-100 text-yellow-600' :
                               ($applicationStatus === 'declined' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'));
                        ?>">
                          <?php 
                            if ($applicationStatus === 'approved') echo '<i class="fas fa-check-circle text-xl"></i>';
                            elseif ($applicationStatus === 'pending') echo '<i class="fas fa-clock text-xl"></i>';
                            elseif ($applicationStatus === 'declined') echo '<i class="fas fa-times-circle text-xl"></i>';
                            else echo '<i class="fas fa-question-circle text-xl"></i>';
                          ?>
                        </div>
                        <div>
                          <h3 class="font-semibold text-gray-800">Application Status</h3>
                          <p class="text-sm text-gray-600">
                            <?php 
                              $statusText = [
                                'approved' => 'Your application has been approved',
                                'pending' => 'Your application is under review',
                                'declined' => 'Your application was declined',
                                'default' => 'Application not submitted'
                              ];
                              echo $statusText[$applicationStatus] ?? $statusText['default'];
                            ?>
                          </p>
                        </div>
                      </div>
                      <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                        echo $applicationStatus === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                             ($applicationStatus === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                             ($applicationStatus === 'declined' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                      ?>"><?php echo htmlspecialchars(ucfirst($applicationStatus)); ?></span>
                    </div>

                    <!-- Payment Status -->
                    <div class="flex items-center justify-between p-4 rounded-lg border bg-blue-50 border-blue-200">
                      <div class="flex items-center">
                        <div class="p-2 rounded-full mr-3 <?php 
                          if (strtolower($paymentStatus) === 'approved') echo 'bg-emerald-100 text-emerald-600';
                          elseif (strtolower($paymentStatus) === 'declined') echo 'bg-red-100 text-red-600';
                          else echo 'bg-yellow-100 text-yellow-600';
                        ?>">
                          <?php 
                            if (strtolower($paymentStatus) === 'approved') echo '<i class="fas fa-check-circle text-xl"></i>';
                            elseif (strtolower($paymentStatus) === 'declined') echo '<i class="fas fa-times-circle text-xl"></i>';
                            else echo '<i class="fas fa-clock text-xl"></i>';
                          ?>
                        </div>
                        <div>
                          <h3 class="font-semibold text-gray-800">Payment Status</h3>
                          <p class="text-sm text-gray-600">
                            <?php 
                              $statusText = [
                                'approved' => 'Your payment has been processed',
                                'pending' => 'Your payment is being reviewed',
                                'declined' => 'Your payment was declined',
                                'default' => 'No payment submitted'
                              ];
                              echo $statusText[strtolower($paymentStatus)] ?? $statusText['default'];
                            ?>
                          </p>
                        </div>
                      </div>
                      <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                        echo strtolower($paymentStatus) === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                             (strtolower($paymentStatus) === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                             (strtolower($paymentStatus) === 'declined' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                      ?>"><?php echo htmlspecialchars(ucfirst($paymentStatus)); ?></span>
                    </div>
                  </div>

                  <!-- Assigned Stall Info (Right Side) -->
                  <div class="lg:col-span-1">
                    <?php if ($applicationStatus === 'approved' && $stallDetails): ?>
                      <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg h-full">
                        <h3 class="font-semibold text-emerald-800 mb-3 flex items-center">
                          <i class="fas fa-store mr-2"></i>
                          Your Assigned Stall
                        </h3>
                        <div class="space-y-2 text-sm">
                          <div class="flex justify-between items-center">
                            <span class="text-emerald-700 font-medium">Stall Number:</span>
                            <span class="text-emerald-900 font-semibold"><?php echo htmlspecialchars($stallDetails['stall_number']); ?></span>
                          </div>
                          <div class="flex justify-between items-center">
                            <span class="text-emerald-700 font-medium">Status:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php 
                              echo $stallDetails['status'] == 'available' ? 'bg-emerald-100 text-emerald-800' : 
                                   ($stallDetails['status'] == 'reserved' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($stallDetails['status'] == 'not_available' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); 
                            ?>">
                              <?php echo ucfirst(str_replace('_', ' ', $stallDetails['status'])); ?>
                            </span>
                          </div>
                          <?php if (!empty($stallDetails['floor_area'])): ?>
                            <div class="flex justify-between items-center">
                              <span class="text-emerald-700 font-medium">Floor Area:</span>
                              <span class="text-emerald-900"><?php echo htmlspecialchars($stallDetails['floor_area']); ?></span>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($stallDetails['monthly_rate'])): ?>
                            <div class="flex justify-between items-center">
                              <span class="text-emerald-700 font-medium">Monthly Rate:</span>
                              <span class="text-emerald-900 font-semibold">₱<?php echo number_format($stallDetails['monthly_rate'], 2); ?></span>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php elseif ($applicationStatus === 'approved'): ?>
                      <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg h-full">
                        <h3 class="font-semibold text-yellow-800 mb-2 flex items-center">
                          <i class="fas fa-exclamation-triangle mr-2"></i>
                          Stall Assignment
                        </h3>
                        <p class="text-yellow-700 text-sm">Your application has been approved but no stall has been assigned yet. Please contact support.</p>
                      </div>
                    <?php elseif ($applicationStatus === 'declined'): ?>
                      <div class="p-4 bg-red-50 border border-red-200 rounded-lg h-full">
                        <h3 class="font-semibold text-red-800 mb-2 flex items-center">
                          <i class="fas fa-exclamation-circle mr-2"></i>
                          Application Declined
                        </h3>
                        <p class="text-red-700 text-sm">Please contact support for more information about your application status.</p>
                      </div>
                    <?php else: ?>
                      <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg h-full">
                        <h3 class="font-semibold text-gray-800 mb-2 flex items-center">
                          <i class="fas fa-info-circle mr-2"></i>
                          Stall Information
                        </h3>
                        <p class="text-gray-600 text-sm">Stall information will be available once your application is approved.</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
        <?php
              break;

            default:
            ?>
              <section class="bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-2xl text-white p-6 shadow">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                  <div>
                    <h1 class="text-2xl md:text-3xl font-bold">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
                    <p class="text-emerald-100 mt-1">Manage your space, payments, and requests in one place.</p>
                  </div>
                  <div class="flex gap-3">
                    <?php if ($approvedStallsCount > 0): ?>
                    <a href="apply_additional_stall.php" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg font-medium shadow-lg">
                      <i class="fas fa-plus-circle mr-1"></i> Add Stall
                    </a>
                    <?php endif; ?>
                    <a href="tenant_payment.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg font-medium">Make Payment</a>
                    <a href="work_permit_form.php" class="px-4 py-2 bg-white text-emerald-700 hover:bg-emerald-50 rounded-lg font-medium">Work Permit</a>
                  </div>
                </div>
              </section>

              <!-- 30-Day Contract Expiration Warning -->
              <?php if ($contractData && $daysRemaining <= 30 && $daysRemaining > 0): ?>
              <section class="bg-gradient-to-r from-red-600 to-red-500 rounded-2xl text-white p-6 shadow-lg animate-pulse cursor-pointer hover:from-red-700 hover:to-red-600 transition-all" onclick="showRenewalModal()">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                  <div class="flex items-center gap-4">
                    <div class="bg-white/20 p-3 rounded-full">
                      <i class="fas fa-exclamation-triangle text-2xl text-yellow-300"></i>
                    </div>
                    <div>
                      <h2 class="text-xl md:text-2xl font-bold text-yellow-300">
                         Contract Expiration Notice
                      </h2>
                      <p class="text-red-100 mt-1">
                        Your contract expires in <strong class="text-yellow-300 text-xl"><?php echo $daysRemaining; ?> days</strong> on 
                        <strong><?php echo date('F j, Y', strtotime($contractData['lease_expiration_date'])); ?></strong>
                      </p>
                      <p class="text-red-200 text-sm mt-1">
                        Click here to renew your contract now to avoid interruption
                      </p>
                    </div>
                  </div>
                  <div class="bg-white/10 px-4 py-2 rounded-lg font-medium hover:bg-white/20 transition-colors">
                    <span class="text-yellow-300">Renew Now</span>
                    <i class="fas fa-arrow-right ml-2"></i>
                  </div>
                </div>
              </section>
              <?php endif; ?>

              <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Application Status</p>
                    <i class="fas fa-file-signature text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold"><?php echo htmlspecialchars(ucfirst($applicationStatus)); ?></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Payment Status</p>
                    <i class="fas fa-receipt text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold"><?php echo htmlspecialchars(ucfirst($paymentStatus)); ?></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Stall</p>
                    <i class="fas fa-store text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold">
                    <?php echo $stallDetails ? htmlspecialchars($stallDetails['stall_number']) : 'Unassigned'; ?>
                  </p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Lease Start</p>
                    <i class="fas fa-calendar-alt text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold"><?php echo htmlspecialchars($startDate); ?></p>
                </div>
              </section>

              <?php
              // Fetch all user's stalls for multi-stall display with payment status and contract info
              $stmtAllStalls = $pdo->prepare("
                  SELECT td.*, s.stall_number, s.description, s.floor_area, s.monthly_rate, s.image_path,
                         (SELECT COUNT(*) FROM payments p WHERE p.user_id = td.user_id AND p.stall_id = s.id AND p.status = 'approved') as has_payment,
                         tld.lease_start_date, tld.lease_expiration_date
                  FROM tenant_details td
                  LEFT JOIN stalls s ON td.stall_id = s.id
                  LEFT JOIN users u ON u.id = td.user_id
                  LEFT JOIN tenants t ON t.email = u.email
                  LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
                  WHERE td.user_id = ?
                  ORDER BY td.status DESC, td.created_at DESC
              ");
              $stmtAllStalls->execute([$userId]);
              $allMyStalls = $stmtAllStalls->fetchAll(PDO::FETCH_ASSOC);
              $approvedStallsCount = count(array_filter($allMyStalls, fn($s) => $s['status'] === 'approved'));
              ?>

              <!-- My Stalls Section -->
              <?php if (count($allMyStalls) > 0): ?>
              <section class="dashboard-card">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h2 class="section-title"><i class="fas fa-store-alt"></i>My Stalls</h2>
                    <p class="text-sm text-gray-500 mt-1">You have <?php echo count($allMyStalls); ?> stall application(s)</p>
                  </div>
                  <?php if ($approvedStallsCount > 0): ?>
                  <a href="apply_additional_stall.php" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg font-medium transition transform hover:scale-105 shadow">
                    <i class="fas fa-plus-circle mr-2"></i>Apply for Additional Stall
                  </a>
                  <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <?php foreach ($allMyStalls as $stallApp): ?>
                    <div class="border-2 <?php echo $stallApp['status'] === 'approved' ? 'border-green-500 bg-green-50' : ($stallApp['status'] === 'pending' ? 'border-yellow-500 bg-yellow-50' : 'border-red-500 bg-red-50'); ?> rounded-lg p-4 hover:shadow-lg transition">
                      <?php if ($stallApp['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($stallApp['image_path']); ?>" alt="Stall" class="w-full h-32 object-cover rounded-lg mb-3">
                      <?php else: ?>
                        <div class="w-full h-32 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-lg mb-3 flex items-center justify-center">
                          <i class="fas fa-store text-white text-4xl"></i>
                        </div>
                      <?php endif; ?>
                      
                      <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($stallApp['stall_number'] ?? 'N/A'); ?></span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $stallApp['status'] === 'approved' ? 'bg-green-200 text-green-800' : ($stallApp['status'] === 'pending' ? 'bg-yellow-200 text-yellow-800' : 'bg-red-200 text-red-800'); ?>">
                          <?php echo ucfirst($stallApp['status']); ?>
                        </span>
                      </div>
                      
                      <div class="space-y-1 text-sm text-gray-700">
                        <p><i class="fas fa-info-circle text-emerald-600 w-4"></i> <?php echo htmlspecialchars($stallApp['description'] ?? 'N/A'); ?></p>
                        <p><i class="fas fa-ruler-combined text-emerald-600 w-4"></i> <?php echo htmlspecialchars($stallApp['floor_area'] ?? 'N/A'); ?> sq.m</p>
                        <p class="font-bold text-emerald-700 mt-2">₱<?php echo number_format($stallApp['monthly_rate'] ?? 0, 2); ?>/month</p>
                      </div>
                      
                      <?php if ($stallApp['status'] === 'declined' && $stallApp['admin_feedback']): ?>
                        <div class="mt-3 p-2 bg-red-100 rounded text-xs text-red-800">
                          <strong>Feedback:</strong> <?php echo htmlspecialchars($stallApp['admin_feedback']); ?>
                        </div>
                      <?php endif; ?>
                      
                      <?php if ($stallApp['status'] === 'approved'): ?>
                        <?php if ($stallApp['has_payment'] > 0): ?>
                          <div class="mt-3 space-y-2">
                            <div class="w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-center rounded-lg font-medium shadow text-sm">
                              <i class="fas fa-check-double mr-2"></i>Payment Completed
                            </div>
                            <button onclick="showContractModal(<?php echo $stallApp['stall_id']; ?>, '<?php echo addslashes(htmlspecialchars($stallApp['stall_number'])); ?>', '<?php echo addslashes(htmlspecialchars($stallApp['description'])); ?>', '<?php echo addslashes(htmlspecialchars($stallApp['image_path'])); ?>', '<?php echo addslashes(htmlspecialchars($stallApp['tradename'] ?? 'N/A')); ?>', '<?php echo addslashes(htmlspecialchars($stallApp['floor_area'] ?? 'N/A')); ?>', <?php echo $stallApp['monthly_rate'] ?? 0; ?>, '<?php echo $stallApp['lease_start_date'] ?? 'N/A'; ?>', '<?php echo $stallApp['lease_expiration_date'] ?? 'N/A'; ?>')" class="w-full px-4 py-2 bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600 text-white text-center rounded-lg font-medium transition transform hover:scale-105 shadow text-sm">
                              <i class="fas fa-file-contract mr-2"></i>View Contract
                            </button>
                          </div>
                        <?php else: ?>
                          <a href="tenant_payment.php?stall_id=<?php echo $stallApp['stall_id']; ?>&additional_stall=1" class="mt-3 block w-full px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white text-center rounded-lg font-medium transition transform hover:scale-105 shadow text-sm">
                            <i class="fas fa-check-circle mr-2"></i>Complete Transaction - Proceed to Payment
                          </a>
                        <?php endif; ?>
                      <?php elseif ($stallApp['status'] === 'declined'): ?>
                        <a href="resubmit_application.php?id=<?php echo $stallApp['id']; ?>" class="mt-3 block w-full px-4 py-2 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white text-center rounded-lg font-medium transition transform hover:scale-105 shadow text-sm">
                          <i class="fas fa-redo mr-2"></i>Resubmit Application
                        </a>
                      <?php endif; ?>
                      
                      <div class="mt-3 text-xs text-gray-500">
                        Applied: <?php echo date('M d, Y', strtotime($stallApp['created_at'])); ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php endif; ?>

              <section class="dashboard-card">
                <h2 class="section-title mb-4"><i class="fas fa-bolt"></i>Quick Actions</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                  <a href="tenant_dashboard.php?page=space" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-map-marked-alt text-emerald-600"></i><span>Area</span>
                  </a>
                  <a href="work_permit_form.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-hard-hat text-emerald-600"></i><span>Work Permit</span>
                  </a>
                  <a href="tenant_dashboard.php?page=renewal" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-sync-alt text-emerald-600"></i><span>Renewal</span>
                  </a>
                  <a href="tenant_payment.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-credit-card text-emerald-600"></i><span>Payment</span>
                  </a>
                  <?php if ($approvedStallsCount > 0): ?>
                  <a href="apply_additional_stall.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border-2 border-purple-600 bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium">
                    <i class="fas fa-plus-circle text-purple-600"></i><span>Add Stall</span>
                  </a>
                  <?php endif; ?>
                </div>
              </section>

              <!-- Analytics Dashboard -->
              <section class="dashboard-card">
                <div class="flex items-center justify-between mb-4">
                  <h2 class="section-title"><i class="fas fa-chart-pie"></i>Analytics Dashboard</h2>
                  <select class="text-sm border border-gray-300 rounded-lg px-3 py-1 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option>This Month</option>
                    <option>Last 3 Months</option>
                    <option>Last 6 Months</option>
                    <option>This Year</option>
                  </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                  <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-sm text-emerald-600 font-medium">Total Revenue</span>
                      <i class="fas fa-chart-line text-emerald-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-emerald-900">₱<?php echo number_format(($stallDetails['monthly_rate'] ?? 0) * 12, 2); ?></p>
                    <p class="text-xs text-emerald-600 mt-1">+12% from last month</p>
                  </div>
                  
                  <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-sm text-blue-600 font-medium">Active Stalls</span>
                      <i class="fas fa-store text-blue-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-blue-900"><?php echo $approvedStallsCount; ?></p>
                    <p class="text-xs text-blue-600 mt-1">Total occupied</p>
                  </div>
                  
                  <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-sm text-purple-600 font-medium">Pending Tasks</span>
                      <i class="fas fa-tasks text-purple-500"></i>
                    </div>
                    <p class="text-2xl font-bold text-purple-900"><?php echo ($applicationStatus === 'pending' ? 1 : 0) + (!$hasApprovedPayment ? 1 : 0); ?></p>
                    <p class="text-xs text-purple-600 mt-1">Requires attention</p>
                  </div>
                </div>

                <!-- Payment Chart -->
                <div class="bg-gray-50 rounded-lg p-4">
                  <h3 class="text-sm font-semibold text-gray-700 mb-3">Payment Overview</h3>
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">January</span>
                      <div class="flex items-center gap-2">
                        <div class="w-32 bg-gray-200 rounded-full h-2">
                          <div class="bg-emerald-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                        <span class="text-sm font-medium">₱<?php echo number_format($stallDetails['monthly_rate'] ?? 0, 2); ?></span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">February</span>
                      <div class="flex items-center gap-2">
                        <div class="w-32 bg-gray-200 rounded-full h-2">
                          <div class="bg-emerald-500 h-2 rounded-full" style="width: 100%"></div>
                        </div>
                        <span class="text-sm font-medium">₱<?php echo number_format($stallDetails['monthly_rate'] ?? 0, 2); ?></span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">March</span>
                      <div class="flex items-center gap-2">
                        <div class="w-32 bg-gray-200 rounded-full h-2">
                          <div class="bg-emerald-500 h-2 rounded-full" style="width: <?php echo $hasApprovedPayment ? '100' : '0'; ?>%"></div>
                        </div>
                        <span class="text-sm font-medium">₱<?php echo number_format($stallDetails['monthly_rate'] ?? 0, 2); ?></span>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Calendar Integration -->
              <section class="dashboard-card">
                <div class="flex items-center justify-between mb-4">
                  <h2 class="section-title"><i class="fas fa-calendar-alt"></i>Calendar & Reminders</h2>
                  <button class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">
                    <i class="fas fa-calendar-plus mr-1"></i> Add Event
                  </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Upcoming Events -->
                  <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Upcoming Events</h3>
                    <div class="space-y-2">
                      <div class="flex items-start gap-3 p-3 bg-red-50 rounded-lg border border-red-200">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center flex-shrink-0">
                          <i class="fas fa-exclamation text-white text-xs"></i>
                        </div>
                        <div class="flex-1">
                          <p class="text-sm font-medium text-red-800">Payment Due</p>
                          <p class="text-xs text-red-600">March 31, 2024</p>
                          <p class="text-xs text-red-500 mt-1">Monthly rent payment</p>
                        </div>
                      </div>
                      
                      <div class="flex items-start gap-3 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center flex-shrink-0">
                          <i class="fas fa-sync text-white text-xs"></i>
                        </div>
                        <div class="flex-1">
                          <p class="text-sm font-medium text-yellow-800">Contract Renewal</p>
                          <p class="text-xs text-yellow-600">June 15, 2024</p>
                          <p class="text-xs text-yellow-500 mt-1">Contract expires in 90 days</p>
                        </div>
                      </div>
                      
                      <div class="flex items-start gap-3 p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                          <i class="fas fa-file text-white text-xs"></i>
                        </div>
                        <div class="flex-1">
                          <p class="text-sm font-medium text-blue-800">Document Renewal</p>
                          <p class="text-xs text-blue-600">April 30, 2024</p>
                          <p class="text-xs text-blue-500 mt-1">BIR certificate renewal</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Mini Calendar -->
                  <div>
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">March 2024</h3>
                    <div class="bg-gray-50 rounded-lg p-3">
                      <div class="grid grid-cols-7 gap-1 text-xs text-center mb-2">
                        <div class="text-gray-500">S</div>
                        <div class="text-gray-500">M</div>
                        <div class="text-gray-500">T</div>
                        <div class="text-gray-500">W</div>
                        <div class="text-gray-500">T</div>
                        <div class="text-gray-500">F</div>
                        <div class="text-gray-500">S</div>
                      </div>
                      <div class="grid grid-cols-7 gap-1 text-xs">
                        <?php for ($day = 1; $day <= 31; $day++): ?>
                          <div class="<?php echo $day == 31 ? 'bg-red-500 text-white rounded' : 'text-gray-700'; ?> p-1 text-center cursor-pointer hover:bg-gray-200 rounded">
                            <?php echo $day; ?>
                          </div>
                        <?php endfor; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </section>

              <!-- Enhanced Quick Actions Panel -->
              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <div class="flex items-center justify-between mb-4">
                  <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                  <span class="text-xs text-gray-500">Most used functions</span>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                  <a href="tenant_payment.php" class="group flex flex-col items-center justify-center gap-2 p-4 rounded-lg border hover:bg-emerald-50 hover:border-emerald-300 transition-all">
                    <div class="w-12 h-12 bg-emerald-100 rounded-full flex items-center justify-center group-hover:bg-emerald-200 transition-colors">
                      <i class="fas fa-credit-card text-emerald-600 text-lg"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Make Payment</span>
                    <span class="text-xs text-gray-500">Pay bills</span>
                  </a>
                  
                  <a href="work_permit_form.php" class="group flex flex-col items-center justify-center gap-2 p-4 rounded-lg border hover:bg-blue-50 hover:border-blue-300 transition-all">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                      <i class="fas fa-hard-hat text-blue-600 text-lg"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Work Permit</span>
                    <span class="text-xs text-gray-500">Request permit</span>
                  </a>
                  
                  <a href="tenant_dashboard.php?page=renewal" class="group flex flex-col items-center justify-center gap-2 p-4 rounded-lg border hover:bg-purple-50 hover:border-purple-300 transition-all">
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                      <i class="fas fa-sync-alt text-purple-600 text-lg"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Renewal</span>
                    <span class="text-xs text-gray-500">Contract</span>
                  </a>
                  
                  <button onclick="openSupportModal()" class="group flex flex-col items-center justify-center gap-2 p-4 rounded-lg border hover:bg-orange-50 hover:border-orange-300 transition-all">
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                      <i class="fas fa-headset text-orange-600 text-lg"></i>
                    </div>
                    <span class="text-sm font-medium text-gray-700">Support</span>
                    <span class="text-xs text-gray-500">Contact admin</span>
                  </button>
                </div>
                
                <!-- Emergency Actions -->
                <div class="mt-4 pt-4 border-t border-gray-200">
                  <h3 class="text-sm font-semibold text-gray-700 mb-2">Emergency Actions</h3>
                  <div class="flex gap-2">
                    <button onclick="openEmergencyModal()" class="flex-1 px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition text-sm font-medium">
                      <i class="fas fa-exclamation-triangle mr-1"></i> Report Issue
                    </button>
                    <button onclick="location.href='tel:+1234567890'" class="flex-1 px-3 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition text-sm font-medium">
                      <i class="fas fa-phone mr-1"></i> Call Admin
                    </button>
                  </div>
                </div>
              </section>

              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-lg font-semibold text-gray-900">Contracts</h2>
                  <?php if (count($tenantContracts) > 0): ?>
                    <span class="text-xs px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-semibold">
                      <?php echo count($tenantContracts); ?> record(s)
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (count($tenantContracts) === 0): ?>
                  <p class="text-sm text-gray-500">No contracts generated yet. Once the admin prepares a contract, it will appear here for download.</p>
                <?php else: ?>
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-xs tracking-widest">
                          <th class="px-4 py-3 text-left">Version</th>
                          <th class="px-4 py-3 text-left">Stall</th>
                          <th class="px-4 py-3 text-left">Business Name</th>
                          <th class="px-4 py-3 text-left">Status</th>
                          <th class="px-4 py-3 text-left">Generated</th>
                          <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <?php foreach ($tenantContracts as $contractRow): ?>
                          <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-800">#<?php echo (int)$contractRow['version']; ?></td>
                            <td class="px-4 py-3">
                              <div class="flex flex-col">
                                <span class="font-medium text-gray-900"><?php echo htmlspecialchars($contractRow['stall_number'] ?? 'N/A'); ?></span>
                                <?php if (!empty($contractRow['stall_description'])): ?>
                                  <span class="text-xs text-gray-500"><?php echo htmlspecialchars($contractRow['stall_description']); ?></span>
                                <?php endif; ?>
                              </div>
                            </td>
                            <td class="px-4 py-3">
                              <span class="font-medium text-gray-800"><?php echo htmlspecialchars($contractRow['tradename'] ?? 'N/A'); ?></span>
                              <?php if (!empty($contractRow['monthly_rate'])): ?>
                                <div class="text-xs text-green-600 font-semibold">₱<?php echo number_format($contractRow['monthly_rate'], 2); ?>/mo</div>
                              <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                              <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold <?php echo $contractRow['contract_status'] === 'final' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                <i class="fas fa-file-contract"></i>
                                <?php echo strtoupper($contractRow['contract_status']); ?>
                              </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                              <?php echo date('M d, Y g:i A', strtotime($contractRow['created_at'])); ?>
                            </td>
                            <td class="px-4 py-3">
                              <div class="flex items-center gap-3">
                                <a href="view_contract.php?id=<?php echo $contractRow['id']; ?>" class="text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1">
                                  <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (!empty($contractRow['contract_path']) && file_exists(__DIR__ . '/' . $contractRow['contract_path'])): ?>
                                  <a href="<?php echo htmlspecialchars($contractRow['contract_path']); ?>" download class="text-gray-500 hover:text-gray-700 text-sm flex items-center gap-1">
                                    <i class="fas fa-download"></i> Download
                                  </a>
                                <?php endif; ?>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </section>

              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-lg font-semibold text-gray-900">Recent Notifications</h2>
                  <a href="#" onclick="showAllNotifications(); return false;" class="text-sm text-emerald-700 hover:text-emerald-800">View all</a>
                </div>
                <?php
                  $recent = array_slice($notifications ?? [], 0, 5);
                  if (count($recent) === 0):
                ?>
                  <p class="text-sm text-gray-500">No recent notifications.</p>
                <?php else: ?>
                  <ul class="divide-y divide-gray-100">
                    <?php foreach ($recent as $n): ?>
                      <li class="py-3 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                          <i class="fas fa-bell text-xs"></i>
                        </div>
                        <div class="flex-1">
                          <p class="text-sm text-gray-800"><?php echo htmlspecialchars($n['category'] ?? 'Notification'); ?></p>
                          <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($n['message']); ?></p>
                          <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y h:i A', strtotime($n['created_at'] ?? 'now')); ?></p>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </section>
            <?php
              break;
          }
        ?>

        
      </div>
      </main>
    </div>
  </div>
<script>
  (function() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggle || !overlay) return;

    function openSidebar() {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = 'auto';
    }
    toggle.addEventListener('click', function() {
      const isOpen = !sidebar.classList.contains('-translate-x-full');
      if (isOpen) closeSidebar(); else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeSidebar();
    });

    function handleResize() {
      if (window.innerWidth >= 1024) { // lg breakpoint
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
      } else {
        if (!overlay.classList.contains('hidden')) return; // keep state if open
        sidebar.classList.add('-translate-x-full');
      }
    }
    window.addEventListener('resize', handleResize);
    handleResize();
  })();
  
  // Search functionality
  const searchForm = document.getElementById('searchForm');
  const searchInput = document.getElementById('searchInput');
  const searchResults = document.getElementById('searchResults');
  const searchResultsContent = document.getElementById('searchResultsContent');
  let searchTimeout;

  if (searchForm && searchInput) {
    searchForm.addEventListener('submit', function(e) {
      e.preventDefault();
      performSearch();
    });

    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const query = this.value.trim();
      
      if (query.length < 2) {
        clearSearch();
        return;
      }
      
      searchTimeout = setTimeout(() => {
        performSearch();
      }, 300);
    });
  }

  function performSearch() {
    const query = searchInput.value.trim();
    
    if (query.length < 2) {
      clearSearch();
      return;
    }

    fetch(`tenant_dashboard.php?ajax=search&q=${encodeURIComponent(query)}`)
      .then(response => response.json())
      .then(data => {
        displaySearchResults(data, query);
      })
      .catch(error => {
        console.error('Search error:', error);
        searchResultsContent.innerHTML = '<p class="text-gray-500 text-center">Error performing search</p>';
      });
  }

  function displaySearchResults(results, query) {
    if (!results || Object.keys(results).length === 0) {
      searchResultsContent.innerHTML = `
        <div class="text-center py-8">
          <i class="fas fa-search text-gray-300 text-4xl mb-3"></i>
          <p class="text-gray-500">No results found for "${query}"</p>
        </div>
      `;
      searchResults.classList.remove('hidden');
      return;
    }

    let html = '';
    
    // Display stalls
    if (results.stalls && results.stalls.length > 0) {
      html += `
        <div class="bg-white rounded-lg p-4 border border-emerald-200">
          <h4 class="font-semibold text-emerald-700 mb-3">
            <i class="fas fa-store mr-2"></i>Stalls (${results.stalls.length})
          </h4>
          <div class="space-y-2">
      `;
      
      results.stalls.forEach(stall => {
        const statusColor = stall.status === 'approved' ? 'green' : 'yellow';
        html += `
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer" onclick="showStallDetails(${stall.id})">
            <div>
              <div class="font-semibold text-gray-800">${stall.title}</div>
              <div class="text-sm text-gray-600">${stall.description}</div>
              <div class="text-xs text-gray-500">${stall.tradename || 'No tenant'}</div>
            </div>
            <div class="text-right">
              <span class="px-2 py-1 bg-${statusColor}-100 text-${statusColor}-800 text-xs rounded-full">${stall.status || 'available'}</span>
              <div class="text-sm font-bold text-emerald-600 mt-1">₱${Number(stall.monthly_rate).toFixed(2)}</div>
            </div>
          </div>
        `;
      });
      
      html += '</div></div>';
    }
    
    // Display payments
    if (results.payments && results.payments.length > 0) {
      html += `
        <div class="bg-white rounded-lg p-4 border border-emerald-200">
          <h4 class="font-semibold text-emerald-700 mb-3">
            <i class="fas fa-credit-card mr-2"></i>Payments (${results.payments.length})
          </h4>
          <div class="space-y-2">
      `;
      
      results.payments.forEach(payment => {
        const statusColor = payment.status === 'approved' ? 'green' : (payment.status === 'pending' ? 'blue' : 'red');
        html += `
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer" onclick="showPaymentDetails(${payment.id})">
            <div>
              <div class="font-semibold text-gray-800">${payment.stall_number || 'Unknown'}</div>
              <div class="text-sm text-gray-600">${payment.billing_month}</div>
              <div class="text-xs text-gray-500">${payment.payment_method}</div>
            </div>
            <div class="text-right">
              <span class="px-2 py-1 bg-${statusColor}-100 text-${statusColor}-800 text-xs rounded-full">${payment.status}</span>
              <div class="text-sm font-bold text-emerald-600 mt-1">₱${Number(payment.amount).toFixed(2)}</div>
            </div>
          </div>
        `;
      });
      
      html += '</div></div>';
    }
    
    // Display contracts
    if (results.contracts && results.contracts.length > 0) {
      html += `
        <div class="bg-white rounded-lg p-4 border border-emerald-200">
          <h4 class="font-semibold text-emerald-700 mb-3">
            <i class="fas fa-file-contract mr-2"></i>Contracts (${results.contracts.length})
          </h4>
          <div class="space-y-2">
      `;
      
      results.contracts.forEach(contract => {
        const statusColor = contract.contract_status === 'active' ? 'green' : (contract.contract_status === 'expired' ? 'red' : 'yellow');
        html += `
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer" onclick="showContractDetails(${contract.id})">
            <div>
              <div class="font-semibold text-gray-800">${contract.tradename}</div>
              <div class="text-sm text-gray-600">${contract.stall_number}</div>
              <div class="text-xs text-gray-500">Version ${contract.version}</div>
            </div>
            <div class="text-right">
              <span class="px-2 py-1 bg-${statusColor}-100 text-${statusColor}-800 text-xs rounded-full">${contract.contract_status}</span>
              <div class="text-xs text-gray-500 mt-1">${new Date(contract.created_at).toLocaleDateString()}</div>
            </div>
          </div>
        `;
      });
      
      html += '</div></div>';
    }
    
    searchResultsContent.innerHTML = html;
    searchResults.classList.remove('hidden');
  }

  function clearSearch() {
    searchResults.classList.add('hidden');
    searchInput.value = '';
  }

  function showModal(title, content) {
    const modalHtml = `
      <div id="documentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
          <div class="p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-bold">${title}</h2>
              <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>
            ${content}
          </div>
        </div>
      </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('documentModal');
    if (existingModal) {
      existingModal.remove();
    }
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
  }

  function closeModal() {
    const modal = document.getElementById('documentModal');
    if (modal) {
      modal.remove();
    }
  }

  function showStallDetails(stallId) {
    // Navigate to stall details or show modal
    window.location.href = `tenant_dashboard.php?page=stall_details&id=${stallId}`;
  }

  function showPaymentDetails(paymentId) {
    // Navigate to payment details or show modal
    window.location.href = `tenant_dashboard.php?page=payment_details&id=${paymentId}`;
  }

  function showContractDetails(contractId) {
    // Navigate to contract details or show modal
    window.location.href = `tenant_dashboard.php?page=contract_details&id=${contractId}`;
  }

  // Support Modal Functions
  function openSupportModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Contact Support</h3>
          <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-500">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h4 class="font-semibold text-blue-800 mb-2">
              <i class="fas fa-phone-alt mr-2"></i>Emergency Contact
            </h4>
            <p class="text-sm text-blue-700">Hotline: +63 2 1234 5678</p>
            <p class="text-sm text-blue-700">Available 24/7 for emergencies</p>
          </div>
          
          <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
            <h4 class="font-semibold text-emerald-800 mb-2">
              <i class="fas fa-envelope mr-2"></i>Email Support
            </h4>
            <p class="text-sm text-emerald-700">support@xentromall.com</p>
            <p class="text-sm text-emerald-700">Response within 24 hours</p>
          </div>
          
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Your Message</label>
            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-transparent" rows="4" placeholder="Describe your issue or question..."></textarea>
          </div>
          
          <div class="flex gap-2">
            <button onclick="this.closest('.fixed').remove()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
              Cancel
            </button>
            <button onclick="sendSupportMessage()" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
              Send Message
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  function sendSupportMessage() {
    // Show success message
    const modal = document.querySelector('.fixed');
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-sm overflow-hidden">
        <div class="p-6 text-center">
          <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check text-emerald-600 text-2xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Message Sent!</h3>
          <p class="text-sm text-gray-600 mb-4">We'll get back to you within 24 hours.</p>
          <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition">
            OK
          </button>
        </div>
      </div>
    `;
  }

  // Emergency Modal Functions
  function openEmergencyModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="text-lg font-semibold text-red-900">Emergency Report</h3>
          <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-500">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <h4 class="font-semibold text-red-800 mb-2">
              <i class="fas fa-exclamation-triangle mr-2"></i>Emergency Hotline
            </h4>
            <p class="text-sm text-red-700">Call immediately: +63 2 1234 5678</p>
            <p class="text-sm text-red-700">Available 24/7 for emergencies</p>
          </div>
          
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Emergency Type</label>
            <select class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent">
              <option value="">Select emergency type</option>
              <option value="power">Power Outage</option>
              <option value="water">Water Leak</option>
              <option value="security">Security Issue</option>
              <option value="fire">Fire Emergency</option>
              <option value="structural">Structural Damage</option>
              <option value="other">Other</option>
            </select>
          </div>
          
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Description</label>
            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" rows="4" placeholder="Describe the emergency..."></textarea>
          </div>
          
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Stall Number</label>
            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent" placeholder="Your stall number" value="<?php echo htmlspecialchars($stallDetails['stall_number'] ?? ''); ?>">
          </div>
          
          <div class="flex gap-2">
            <button onclick="this.closest('.fixed').remove()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
              Cancel
            </button>
            <button onclick="sendEmergencyReport()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
              Send Report
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  function sendEmergencyReport() {
    // Show success message
    const modal = document.querySelector('.fixed');
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-sm overflow-hidden">
        <div class="p-6 text-center">
          <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check text-red-600 text-2xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-red-900 mb-2">Emergency Report Sent!</h3>
          <p class="text-sm text-gray-600 mb-4">Emergency team has been notified.</p>
          <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
            OK
          </button>
        </div>
      </div>
    `;
  }

  // Renewal Modal Function
  function showRenewalModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-orange-600 to-orange-500">
          <h3 class="text-lg font-semibold text-white">Contract Renewal Required</h3>
          <button onclick="this.closest('.fixed').remove()" class="text-white/80 hover:text-white transition-colors">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
            <h4 class="font-semibold text-orange-800 mb-2 flex items-center">
              <i class="fas fa-exclamation-triangle mr-2"></i>Action Required
            </h4>
            <p class="text-sm text-orange-700">Your contract will expire soon. Please decide whether to renew your contract.</p>
          </div>
          
          <div class="text-center space-y-4">
            <p class="text-gray-700 font-medium">Do you want to renew your contract?</p>
            
            <div class="grid grid-cols-2 gap-3">
              <button onclick="proceedToRenewal()" class="px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all font-medium shadow hover:shadow-lg transform hover:scale-105">
                <i class="fas fa-check-circle mr-2"></i>Yes, Renew Now
              </button>
              <button onclick="declineRenewal()" class="px-4 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-all font-medium shadow hover:shadow-lg transform hover:scale-105">
                <i class="fas fa-times-circle mr-2"></i>No, Don't Renew
              </button>
            </div>
          </div>
          
          <div class="bg-gray-50 rounded-lg p-3 text-center">
            <p class="text-xs text-gray-600">
              <i class="fas fa-info-circle mr-1"></i>
              Note: If you don't renew, your access will be terminated upon contract expiration.
            </p>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  function proceedToRenewal() {
    // Redirect to renewal page
    window.location.href = 'tenant_dashboard.php?page=renewal';
  }

  function declineRenewal() {
    // Show confirmation message
    const modal = document.querySelector('.fixed');
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-md overflow-hidden shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-yellow-600 to-yellow-500">
          <h3 class="text-lg font-semibold text-white">Renewal Declined</h3>
          <button onclick="this.closest('.fixed').remove()" class="text-white/80 hover:text-white transition-colors">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        <div class="p-6 text-center space-y-4">
          <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto">
            <i class="fas fa-info-circle text-yellow-600 text-2xl"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900">Renewal Declined</h3>
          <p class="text-sm text-gray-600">You can change your mind anytime before the contract expires.</p>
          <button onclick="this.closest('.fixed').remove()" class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all font-medium shadow hover:shadow-lg">
            <i class="fas fa-check mr-2"></i>Close
          </button>
        </div>
      </div>
    `;
  }

  function showContractModal(stallId, stallNumber, description, imagePath, tradename, floorArea, monthlyRate, leaseStartDate, leaseExpirationDate) {
    // Format dates
    const formattedStartDate = leaseStartDate && leaseStartDate !== 'N/A' ? new Date(leaseStartDate).toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    }) : 'Not specified';
    
    const formattedEndDate = leaseExpirationDate && leaseExpirationDate !== 'N/A' ? new Date(leaseExpirationDate).toLocaleDateString('en-US', { 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    }) : 'Not specified';

    // Calculate contract duration
    let durationText = 'Not specified';
    if (leaseStartDate && leaseExpirationDate && leaseStartDate !== 'N/A' && leaseExpirationDate !== 'N/A') {
      const start = new Date(leaseStartDate);
      const end = new Date(leaseExpirationDate);
      const months = Math.round((end - start) / (1000 * 60 * 60 * 24 * 30));
      durationText = `${months} months`;
    }

    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
      <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-purple-600 to-indigo-600">
          <h3 class="text-xl font-semibold text-white">
            <i class="fas fa-file-contract mr-2"></i>Contract Details - ${stallNumber}
          </h3>
          <button onclick="this.closest('.fixed').remove()" class="text-white/80 hover:text-white transition-colors">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Stall Image & Basic Info -->
            <div class="space-y-4">
              <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Stall Information</h4>
                
                <!-- Stall Image -->
                <div class="mb-4">
                  ${imagePath && imagePath !== '' ? 
                    `<img src="${imagePath}" alt="${stallNumber}" class="w-full h-48 object-cover rounded-lg shadow-md">` :
                    `<div class="w-full h-48 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-lg flex items-center justify-center">
                      <i class="fas fa-store text-white text-6xl"></i>
                    </div>`
                  }
                </div>
                
                <div class="space-y-2">
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600 font-medium">Stall Number:</span>
                    <span class="font-bold text-gray-900">${stallNumber}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600 font-medium">Description:</span>
                    <span class="text-gray-900">${description}</span>
                  </div>
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600 font-medium">Floor Area:</span>
                    <span class="font-bold text-gray-900">${floorArea} sq.m</span>
                  </div>
                  <div class="flex justify-between items-center py-2">
                    <span class="text-gray-600 font-medium">Monthly Rate:</span>
                    <span class="font-bold text-green-600 text-lg">₱${Number(monthlyRate).toLocaleString('en-PH', {minimumFractionDigits: 2})}</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Contract Details -->
            <div class="space-y-4">
              <div class="bg-purple-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Contract Period</h4>
                
                <div class="space-y-4">
                  <!-- Start Date -->
                  <div class="bg-white rounded-lg p-4 border border-purple-200">
                    <div class="flex items-center mb-2">
                      <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-play text-white"></i>
                      </div>
                      <div>
                        <p class="text-sm text-gray-600">Contract Start</p>
                        <p class="font-bold text-gray-900">${formattedStartDate}</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- End Date -->
                  <div class="bg-white rounded-lg p-4 border border-purple-200">
                    <div class="flex items-center mb-2">
                      <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-stop text-white"></i>
                      </div>
                      <div>
                        <p class="text-sm text-gray-600">Contract Expiration</p>
                        <p class="font-bold text-gray-900">${formattedEndDate}</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Contract Duration -->
                  <div class="bg-gradient-to-r from-purple-100 to-indigo-100 rounded-lg p-4 border border-purple-300">
                    <div class="flex items-center">
                      <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-calendar-alt text-white"></i>
                      </div>
                      <div>
                        <p class="text-sm text-gray-600">Contract Duration</p>
                        <p class="font-bold text-purple-900">${durationText}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Business Information -->
              <div class="bg-blue-50 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">Business Information</h4>
                <div class="space-y-2">
                  <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <span class="text-gray-600 font-medium">Trade Name:</span>
                    <span class="font-bold text-gray-900">${tradename}</span>
                  </div>
                  <div class="flex justify-between items-center py-2">
                    <span class="text-gray-600 font-medium">Status:</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                      <i class="fas fa-check-circle mr-1"></i>Active
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
            <button onclick="window.print()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-all font-medium shadow hover:shadow-lg">
              <i class="fas fa-print mr-2"></i>Print Contract
            </button>
            <button onclick="this.closest('.fixed').remove()" class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-all font-medium shadow hover:shadow-lg">
              <i class="fas fa-check mr-2"></i>Close
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  // Standalone BIR Update Modal Functions
  function openBIRUpdateModal(stallNumber) {
    // Check if modal already exists
    let modal = document.getElementById('birUpdateModal');
    if (!modal) {
      modal = document.createElement('div');
      modal.id = 'birUpdateModal';
      modal.className = 'fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4 hidden';
      modal.innerHTML = `
        <div class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in">
          <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-indigo-600 to-blue-600">
            <h3 class="text-lg font-bold text-white flex items-center gap-2">
              <i class="fas fa-file-upload"></i>
              Update BIR Registration
            </h3>
            <button onclick="closeBIRUpdateModal()" class="text-white/80 hover:text-white transition-colors">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          <form id="birUpdateForm" class="p-6 space-y-4">
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4">
              <p class="text-sm text-indigo-800">
                <i class="fas fa-info-circle mr-1"></i>
                Upload your updated <strong>BIR Form 2303</strong>. The administration will review your submission and update your registration expiry date.
              </p>
            </div>
            
            <div class="space-y-1">
              <label class="block text-sm font-semibold text-gray-700">Stall Number</label>
              <input type="text" id="birStallNumber" readonly class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 font-medium">
            </div>

            <div class="space-y-1">
              <label class="block text-sm font-semibold text-gray-700">BIR Document (Image/PDF)</label>
              <div class="relative group">
                <input type="file" name="bir_document" id="bir_document" accept="image/*,.pdf" required
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div class="border-2 border-dashed border-gray-300 group-hover:border-indigo-400 rounded-xl p-6 text-center transition-all">
                  <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 group-hover:text-indigo-500 mb-2"></i>
                  <p class="text-sm text-gray-600" id="fileNameDisplay">Select or drop file here</p>
                  <p class="text-[10px] text-gray-400 mt-1">Maximum file size: 5MB</p>
                </div>
              </div>
            </div>

            <div id="birUpdateMsg" class="hidden p-3 rounded-lg text-sm"></div>

            <button type="submit" id="submitBIRBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
              <i class="fas fa-paper-plane"></i>
              <span>Submit Update</span>
            </button>
          </form>
        </div>
      `;
      document.body.appendChild(modal);

      // Add file display logic
      document.getElementById('bir_document').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'Select or drop file here';
        document.getElementById('fileNameDisplay').textContent = fileName;
        document.getElementById('fileNameDisplay').classList.add('text-indigo-600', 'font-semibold');
      });

      // Add form submission logic
      document.getElementById('birUpdateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = document.getElementById('submitBIRBtn');
        const msgDiv = document.getElementById('birUpdateMsg');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        
        fetch('submit_bir_standalone.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          msgDiv.textContent = data.message;
          msgDiv.className = `p-3 rounded-lg text-sm ${data.success ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-red-50 text-red-700 border border-red-100'}`;
          msgDiv.classList.remove('hidden');

          if (data.success) {
            submitBtn.style.display = 'none';
            setTimeout(() => {
              window.location.reload();
            }, 2500);
          } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Submit Update</span>';
          }
        })
        .catch(error => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Submit Update</span>';
          msgDiv.textContent = 'An unexpected error occurred. Please try again.';
          msgDiv.className = 'p-3 rounded-lg text-sm bg-red-50 text-red-700 border border-red-100';
          msgDiv.classList.remove('hidden');
        });
      });
    }

    document.getElementById('birStallNumber').value = stallNumber;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeBIRUpdateModal() {
    const modal = document.getElementById('birUpdateModal');
    if (modal) {
      modal.classList.add('hidden');
      document.body.style.overflow = 'auto';
    }
  }
  
</script>
</body>
</html>
 