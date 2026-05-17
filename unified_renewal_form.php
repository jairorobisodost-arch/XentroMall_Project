<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$requestType = 'new_application'; // Default
$isExistingTenant = false;
$tenantData = null;
$contractData = null;
$tenantId = null;

// Check if user is an existing tenant with approved payment
$stmtCheckTenant = $pdo->prepare("
    SELECT COUNT(*) as payment_count
    FROM payments p
    WHERE p.user_id = ? AND p.status = 'approved'
");
$stmtCheckTenant->execute([$userId]);
$paymentStatus = $stmtCheckTenant->fetch(PDO::FETCH_ASSOC);
$isExistingTenant = ($paymentStatus['payment_count'] > 0);

// If existing tenant, get their details
if ($isExistingTenant) {
    $requestType = 'renewal';
    
    // Capture stall_id from URL if provided (from dashboard)
    $targetStallId = $_GET['stall_id'] ?? null;
    
    if ($targetStallId) {
        $stmtTenant = $pdo->prepare("
            SELECT td.id, td.tradename, td.stall_id, td.email, td.contact_person, td.mobile, td.company_name, td.business_address, td.status, td.business_type
            FROM tenant_details td
            WHERE td.user_id = ? AND td.stall_id = ?
            LIMIT 1
        ");
        $stmtTenant->execute([$userId, $targetStallId]);
    } else {
        $stmtTenant = $pdo->prepare("
            SELECT td.id, td.tradename, td.stall_id, td.email, td.contact_person, td.mobile, td.company_name, td.business_address, td.status, td.business_type
            FROM tenant_details td
            WHERE td.user_id = ?
            LIMIT 1
        ");
        $stmtTenant->execute([$userId]);
    }
    $tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Check if tenant data was found
    if (!$tenantData) {
        echo "<!-- DEBUG: No tenant data found for user_id: $userId -->";
    } else {
        echo "<!-- DEBUG: Tenant data found: " . json_encode($tenantData) . " -->";
    }
    
    // Get tenant ID from tenants table
    $stmtUserEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmtUserEmail->execute([$userId]);
    $userEmail = $stmtUserEmail->fetchColumn();
    
    $stmtTenantId = $pdo->prepare("SELECT id as tenant_id FROM tenants WHERE email = ?");
    $stmtTenantId->execute([$userEmail]);
    $tenantIdData = $stmtTenantId->fetch();
    $tenantId = $tenantIdData['tenant_id'] ?? null;
    
    // Get contract details
    if ($tenantId) {
        $stmtContract = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
        $stmtContract->execute([$tenantId]);
        $contractData = $stmtContract->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check for existing pending renewal - make it stall specific if stall_id provided
    $currentStallId = $tenantData['stall_id'] ?? null;
    
    if ($currentStallId) {
        $stmtPending = $pdo->prepare("
            SELECT * FROM unified_renewal_requests 
            WHERE user_id = ? AND stall_id = ? AND status IN ('pending', 'approved', 'payment_pending') 
            ORDER BY submitted_at DESC LIMIT 1
        ");
        $stmtPending->execute([$userId, $currentStallId]);
        $pendingRenewal = $stmtPending->fetch(PDO::FETCH_ASSOC);
        
        if ($pendingRenewal) {
            $stall_num = "";
            $stmtStallNum = $pdo->prepare("SELECT stall_number FROM stalls WHERE id = ?");
            $stmtStallNum->execute([$currentStallId]);
            $stall_num = $stmtStallNum->fetchColumn() ?: "";
            
            if ($pendingRenewal['status'] === 'pending') {
                $_SESSION['warning_message'] = "You already have a pending renewal request for Stall $stall_num. Please wait for admin approval.";
            } elseif (!empty($pendingRenewal['payment_proof'])) {
                $_SESSION['warning_message'] = "Your payment for Stall $stall_num has been submitted and is currently being reviewed.";
            } else {
                $_SESSION['warning_message'] = "Your renewal request for Stall $stall_num has been approved and is waiting for payment.";
            }
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
    }
}

// Calculate initial values for form display
$lateFee = 0;
$monthlyRate = 0;
$totalAmount = 0;
$advanceMonths = 0;
$stallId = null;
$tenantDetailId = null;
$stallNumber = 'N/A'; // Initialize stall number

if ($isExistingTenant && $tenantData) {
    $stallId = $tenantData['stall_id'];
    $tenantDetailId = $tenantData['id'];
    
    // Get stall details for existing tenant
    $stmtStall = $pdo->prepare("SELECT stall_number, monthly_rate FROM stalls WHERE id = ?");
    $stmtStall->execute([$stallId]);
    $stallInfo = $stmtStall->fetch(PDO::FETCH_ASSOC);
    $stallNumber = $stallInfo['stall_number'] ?? 'N/A';
    $monthlyRate = $stallInfo['monthly_rate'] ?? 0;
    
    // Calculate late fee if contract expired
    if ($contractData) {
        $expirationDate = new DateTime($contractData['lease_expiration_date']);
        $today = new DateTime();
        if ($today > $expirationDate) {
            $stmtFee = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'late_renewal_fee'");
            $stmtFee->execute();
            $lateFee = (float)($stmtFee->fetchColumn() ?: 500);
        }
    }
    
    $totalAmount = $monthlyRate; // Monthly payment only
    $advanceMonths = 0;
} else {
    // New application - will be set via form
    $advanceMonths = 3;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal_request'])) {
    try {
        // Validate business type selection
        $businessType = $_POST['business_type'] ?? 'unknown';
        if ($businessType === 'unknown' || $businessType === '') {
            throw new Exception("Please select a business type (Corporation, Sole Proprietorship, or Partnership)");
        }
        
        $pdo->beginTransaction();
        
        // Get user email
        $stmtUserEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmtUserEmail->execute([$userId]);
        $userEmail = $stmtUserEmail->fetchColumn();
        
        // Prepare upload directory
        $uploadDir = 'uploads/renewal_requests/' . $userId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Handle file uploads based on business type
        $uploadedFiles = [];
        
        // Debug: Log business type and files
        error_log("Business Type: " . $businessType);
        error_log("FILES Data: " . json_encode($_FILES));
        
        if ($businessType === 'corporation') {
            $files = [
                'letter_of_intent',
                'business_profile', 
                'business_registration', // SEC Registration
                'secretary_certificate',
                'bir_registration',
                'valid_id',
                'valid_id_2'
            ];
        } elseif ($businessType === 'sole_proprietorship') {
            $files = [
                'letter_of_intent_sole',
                'business_registration_sole', // DTI Permit
                'bir_registration_sole',
                'valid_id_sole_1',
                'valid_id_sole_2'
            ];
        } elseif ($businessType === 'partnership') {
            $files = [
                'letter_of_intent_partner',
                'business_registration_partner', // DTI Permit
                'financial_statement_partner', // Partnership Agreement
                'bir_registration_partner',
                'valid_id_partner_1',
                'valid_id_partner_2'
            ];
        } else {
            $files = []; // No files if no business type selected
        }
        
        error_log("Expected files: " . json_encode($files));
        
        foreach ($files as $file) {
            if (isset($_FILES[$file])) {
                error_log("Processing file: $file, Error: " . $_FILES[$file]['error']);
                if ($_FILES[$file]['error'] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES[$file]['tmp_name'];
                    $fileName = basename($_FILES[$file]['name']);
                    $targetFile = $uploadDir . uniqid() . '_' . $fileName;
                    error_log("Moving file from $tmpName to $targetFile");
                    if (move_uploaded_file($tmpName, $targetFile)) {
                        $uploadedFiles[$file] = $targetFile;
                        error_log("Successfully uploaded: $file -> $targetFile");
                    } else {
                        $uploadedFiles[$file] = null;
                        error_log("Failed to move file: $file");
                    }
                } else {
                    $uploadedFiles[$file] = null;
                    error_log("File upload error for $file: " . $_FILES[$file]['error']);
                }
            } else {
                $uploadedFiles[$file] = null;
                error_log("File not set: $file");
            }
        }
        
        error_log("Uploaded files result: " . json_encode($uploadedFiles));
        
        // Initialize variables
        $lateFee = 0;
        $monthlyRate = 0;
        $totalAmount = 0;
        $advanceMonths = 0;
        $stallId = null;
        $tenantDetailId = null;
        
        // Determine request type and payment logic
        if ($isExistingTenant) {
            // RENEWAL: Monthly payment only, use existing account
            $requestType = 'renewal';
            $paymentType = 'monthly';
            $accountAction = 'use_existing';
            
            $stallId = $_POST['stall_id'] ?? ($tenantData['stall_id'] ?? null);
            $tenantDetailId = $_POST['tenant_detail_id'] ?? ($tenantData['id'] ?? null);

            if (!$stallId || !$tenantDetailId) {
                throw new Exception("Unable to identify the stall for renewal.");
            }
            
            // Double-check for existing pending renewal to prevent duplicates
            $stmtCheckDup = $pdo->prepare("
                SELECT COUNT(*) FROM unified_renewal_requests 
                WHERE user_id = ? AND stall_id = ? AND status IN ('pending', 'approved', 'payment_pending')
            ");
            $stmtCheckDup->execute([$userId, $stallId]);
            if ($stmtCheckDup->fetchColumn() > 0) {
                throw new Exception("You already have a pending or approved renewal application for this stall.");
            }
            
            // Get stall details for monthly rate
            $stmtStall = $pdo->prepare("SELECT monthly_rate FROM stalls WHERE id = ?");
            $stmtStall->execute([$stallId]);
            $stallDetails = $stmtStall->fetch();
            $monthlyRate = $stallDetails['monthly_rate'] ?? 0;
            
            // Calculate late fee if contract expired
            $lateFee = 0;
            if ($contractData) {
                $expirationDate = new DateTime($contractData['lease_expiration_date']);
                $today = new DateTime();
                if ($today > $expirationDate) {
                    $stmtFee = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'late_renewal_fee'");
                    $stmtFee->execute();
                    $lateFee = (float)($stmtFee->fetchColumn() ?: 500);
                }
            }
            
            $totalAmount = $monthlyRate; // Monthly payment only
            $advanceMonths = 0;
            // stallId and tenantDetailId already set above
            
        } else {
            // NEW APPLICATION: 3 months advance payment, create new account
            $requestType = 'new_application';
            $paymentType = 'three_months_advance';
            $accountAction = 'create_new';
            
            // Get stall from form
            $stallId = $_POST['stall_id'] ?? null;
            if (!$stallId) {
                throw new Exception("Stall selection is required for new applications.");
            }
            
            $stmtStall = $pdo->prepare("SELECT monthly_rate FROM stalls WHERE id = ?");
            $stmtStall->execute([$stallId]);
            $stallDetails = $stmtStall->fetch();
            $monthlyRate = $stallDetails['monthly_rate'] ?? 0;
            
            $totalAmount = $monthlyRate * 3; // 3 months advance
            $advanceMonths = 3;
            $lateFee = 0;
            $tenantDetailId = null;
        }
        
        // Get business type from form
        $businessType = $_POST['business_type'] ?? 'unknown';
        
        // Get tenant info from form or existing data
        $tradename = $_POST['tradename'] ?? ($tenantData['tradename'] ?? '');
        $companyName = $_POST['company_name'] ?? ($tenantData['company_name'] ?? '');
        $businessAddress = $_POST['business_address'] ?? ($tenantData['business_address'] ?? '');
        $contactPerson = $_POST['contact_person'] ?? ($tenantData['contact_person'] ?? '');
        $mobile = $_POST['mobile'] ?? ($tenantData['mobile'] ?? '');
        
        // Insert renewal request with business type - use new table structure
        if ($businessType === 'corporation') {
            $stmt = $pdo->prepare("
                INSERT INTO unified_renewal_requests (
                    user_id, tenant_id, tenant_detail_id,
                    request_type, tradename, company_name, business_address, contact_person, mobile, email,
                    business_type,
                    stall_id, monthly_rate,
                    payment_type, total_amount, advance_months, late_renewal_fee,
                    account_action,
                    letter_of_intent, business_profile, business_registration, secretary_certificate,
                    bir_registration, valid_id, valid_id_2,
                    status, submitted_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, NOW()
                )
            ");

            $stmt->execute([
                $userId, $tenantId, $tenantDetailId,
                $requestType, $tradename, $companyName, $businessAddress, $contactPerson, $mobile, $userEmail,
                $businessType,
                $stallId, $monthlyRate,
                $paymentType, $totalAmount, $advanceMonths, $lateFee,
                $accountAction,
                $uploadedFiles['letter_of_intent'] ?? null,
                $uploadedFiles['business_profile'] ?? null,
                $uploadedFiles['business_registration'] ?? null,
                $uploadedFiles['secretary_certificate'] ?? null,
                $uploadedFiles['bir_registration'] ?? null,
                $uploadedFiles['valid_id'] ?? null,
                $uploadedFiles['valid_id_2'] ?? null,
                'pending'
            ]);

        } elseif ($businessType === 'sole_proprietorship') {
            $stmt = $pdo->prepare("
                INSERT INTO unified_renewal_requests (
                    user_id, tenant_id, tenant_detail_id,
                    request_type, tradename, company_name, business_address,
                    contact_person, mobile, email,
                    business_type,
                    stall_id, monthly_rate,
                    payment_type, total_amount, advance_months, late_renewal_fee,
                    account_action,
                    letter_of_intent_sole,
                    business_registration_sole,
                    bir_registration_sole,
                    valid_id_sole_1,
                    valid_id_sole_2,
                    status, submitted_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, NOW()
                )
            ");

            $stmt->execute([
                $userId, $tenantId, $tenantDetailId,
                $requestType, $tradename, $companyName, $businessAddress,
                $contactPerson, $mobile, $userEmail,
                $businessType,
                $stallId, $monthlyRate,
                $paymentType, $totalAmount, $advanceMonths, $lateFee,
                $accountAction,
                $uploadedFiles['letter_of_intent_sole'] ?? null,
                $uploadedFiles['business_registration_sole'] ?? null,
                $uploadedFiles['bir_registration_sole'] ?? null,
                $uploadedFiles['valid_id_sole_1'] ?? null,
                $uploadedFiles['valid_id_sole_2'] ?? null,
                'pending'
            ]);

        } elseif ($businessType === 'partnership') {
            $stmt = $pdo->prepare("
                INSERT INTO unified_renewal_requests (
                    user_id, tenant_id, tenant_detail_id,
                    request_type, tradename, company_name, business_address,
                    contact_person, mobile, email,
                    business_type,
                    stall_id, monthly_rate,
                    payment_type, total_amount, advance_months, late_renewal_fee,
                    account_action,
                    letter_of_intent_partner,
                    business_registration_partner,
                    bir_registration_partner,
                    financial_statement_partner,
                    valid_id_partner_1,
                    valid_id_partner_2,
                    status, submitted_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, NOW()
                )
            ");

            $stmt->execute([
                $userId, $tenantId, $tenantDetailId,
                $requestType, $tradename, $companyName, $businessAddress,
                $contactPerson, $mobile, $userEmail,
                $businessType,
                $stallId, $monthlyRate,
                $paymentType, $totalAmount, $advanceMonths, $lateFee,
                $accountAction,
                $uploadedFiles['letter_of_intent_partner'] ?? null,
                $uploadedFiles['business_registration_partner'] ?? null,
                $uploadedFiles['bir_registration_partner'] ?? null,
                $uploadedFiles['financial_statement_partner'] ?? null,
                $uploadedFiles['valid_id_partner_1'] ?? null,
                $uploadedFiles['valid_id_partner_2'] ?? null,
                'pending'
            ]);

        }
        
        $renewalRequestId = $pdo->lastInsertId();
        
        // Create audit log entry
        $stmtLog = $pdo->prepare("
            INSERT INTO unified_renewal_audit_log 
            (renewal_request_id, action, old_status, new_status, admin_id, notes)
            VALUES (?, 'submit', NULL, 'pending', NULL, ?)
        ");
        $stmtLog->execute([$renewalRequestId, "Submitted as " . $requestType]);
        
        // Send email notification to admin
        $adminEmail = 'mallxentro5@gmail.com';
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mallxentro5@gmail.com';
            $mail->Password = 'iwld cjlr kmcy bxab';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
            $mail->addAddress($adminEmail, 'XentroMall Admin');
            
            $mail->isHTML(true);
            
            if ($requestType === 'renewal') {
                $mail->Subject = "🔄 Tenant Renewal Request - " . $tradename;
                $paymentInfo = "Monthly Payment: ₱" . number_format($monthlyRate, 2);
                if ($lateFee > 0) {
                    $paymentInfo .= " + Late Fee: ₱" . number_format($lateFee, 2);
                }
            } else {
                $mail->Subject = "📝 New Tenant Application - " . $tradename;
                $paymentInfo = "3-Month Advance Payment: ₱" . number_format($totalAmount, 2);
            }
            
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                    .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                    .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #667eea; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                    .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                    .info-row:last-child { border-bottom: none; }
                    .info-label { font-weight: bold; color: #374151; width: 180px; }
                    .info-value { color: #1f2937; flex: 1; }
                    .badge { display: inline-block; background: #667eea; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    .action-button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>" . ($requestType === 'renewal' ? '🔄 Tenant Renewal Request' : '📝 New Tenant Application') . "</h1>
                        <p>XentroMall Management System</p>
                    </div>
                    <div class='content'>
                        <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                            A new " . ($requestType === 'renewal' ? 'renewal request' : 'application') . " has been submitted and is waiting for your review.
                        </p>

                        <div class='info-box'>
                            <h3 style='margin-top: 0; color: #667eea;'>📋 Request Details</h3>
                            <div class='info-row'>
                                <div class='info-label'>Request Type:</div>
                                <div class='info-value'><span class='badge'>" . ($requestType === 'renewal' ? '🔄 Renewal' : '📝 New Application') . "</span></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Request ID:</div>
                                <div class='info-value'>#" . $renewalRequestId . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Payment Type:</div>
                                <div class='info-value'>" . ($paymentType === 'monthly' ? 'Monthly Only' : '3-Month Advance') . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Total Amount:</div>
                                <div class='info-value'><strong>₱" . number_format($totalAmount, 2) . "</strong></div>
                            </div>
                            " . ($lateFee > 0 ? "<div class='info-row'><div class='info-label'>Late Fee:</div><div class='info-value'>₱" . number_format($lateFee, 2) . "</div></div>" : "") . "
                        </div>

                        <div class='info-box'>
                            <h3 style='margin-top: 0; color: #667eea;'>🏢 Tenant Information</h3>
                            <div class='info-row'>
                                <div class='info-label'>Trade Name:</div>
                                <div class='info-value'><strong>" . htmlspecialchars($tradename) . "</strong></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Contact Person:</div>
                                <div class='info-value'>" . htmlspecialchars($contactPerson) . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Email:</div>
                                <div class='info-value'>" . htmlspecialchars($userEmail) . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Mobile:</div>
                                <div class='info-value'>" . htmlspecialchars($mobile) . "</div>
                            </div>
                        </div>

                        <div style='text-align: center; margin: 30px 0;'>
                            <p style='font-size: 15px; color: #374151; margin-bottom: 15px;'>
                                Please review the submitted documents and take appropriate action.
                            </p>
                            <a href='" . BASE_URL . "admin_unified_renewal.php' class='action-button'>
                                Review Request →
                            </a>
                        </div>

                        <div class='footer'>
                            <p style='margin: 5px 0;'>This is an automated notification from XentroMall Management System</p>
                            <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
            
            $mail->send();
        } catch (Exception $e) {
            error_log("Email failed: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        $_SESSION['success_message'] = ($requestType === 'renewal' ? 'Renewal' : 'Application') . " submitted successfully! Waiting for admin approval.";
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Error submitting request: " . $e->getMessage();
        header('Location: unified_renewal_form.php');
        exit;
    }
}

// Get available stalls for new applications
$availableStalls = [];
if (!$isExistingTenant) {
    $stmtStalls = $pdo->prepare("SELECT id, stall_number, description, floor_area, monthly_rate FROM stalls WHERE status = 'available' ORDER BY stall_number");
    $stmtStalls->execute();
    $availableStalls = $stmtStalls->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo $isExistingTenant ? 'Renewal Request' : 'New Application'; ?> - XentroMall</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #22c55e 0%, #166534 100%);
            min-height: 100vh;
            padding: 20px;
            overflow-x: hidden;
        }

        * {
            box-sizing: border-box;
        }
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            max-width: 900px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #22c55e;
            padding-bottom: 20px;
        }
        .form-header h1 {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin: 0;
        }
        .form-header p {
            color: #6b7280;
            margin-top: 8px;
        }
        .request-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }
        .request-type-badge.renewal {
            background: #dcfce7;
            color: #166534;
        }
        .request-type-badge.new-app {
            background: #dbeafe;
            color: #1e40af;
        }
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #22c55e;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-section h2 {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            color: #166534;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="date"]:focus,
        select:focus,
        textarea:focus {
            border-color: #22c55e;
            outline: none;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        input[type="file"]:hover {
            border-color: #22c55e;
            background: #f8fafc;
        }
        .file-info {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #22c55e 0%, #166534 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(34, 197, 94, 0.3);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        .tenant-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .tenant-info p {
            margin: 5px 0;
            color: #1e40af;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #22c55e;
            text-decoration: none;
            font-weight: 500;
        }

        /* Mobile Responsive Styles */
        @media screen and (max-width: 768px) {
            body {
                padding: 10px;
            }

            .form-container {
                padding: 20px 15px;
                border-radius: 12px;
                margin: 0;
            }

            .form-header h1 {
                font-size: 22px;
            }

            .form-section {
                padding: 15px;
                margin-bottom: 20px;
            }

            .form-section h2 {
                font-size: 16px;
            }

            .tenant-info {
                padding: 12px;
            }

            .tenant-info p {
                font-size: 13px;
                word-break: break-all;
            }

            .submit-btn {
                padding: 10px 20px;
                font-size: 14px;
                margin-top: 15px;
            }

            input[type="text"],
            input[type="email"],
            input[type="tel"],
            input[type="date"],
            select,
            textarea {
                padding: 10px;
                font-size: 13px;
            }

            input[type="file"] {
                padding: 8px;
                font-size: 12px;
            }
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .payment-summary {
            background: linear-gradient(135deg, #22c55e 0%, #166534 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .payment-summary h3 {
            margin: 0 0 15px 0;
            font-size: 16px;
        }
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        .payment-row:last-child {
            border-bottom: none;
        }
        .payment-row.total {
            font-weight: bold;
            font-size: 18px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid rgba(255,255,255,0.3);
        }
        .hidden {
            display: none !important;
        }
        /* Consistent green, white, blue styling */
        .business-type-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .requirements-container {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .file-upload-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="tenant_dashboard.php<?php echo $isExistingTenant ? '?page=renewal' : ''; ?>" class="back-link">
            <i class="fas fa-arrow-left mr-2"></i> Back
        </a>

        <div class="form-header">
            <h1>
                <i class="fas <?php echo $isExistingTenant ? 'fa-sync-alt' : 'fa-file-alt'; ?> mr-2"></i>
                <?php echo $isExistingTenant ? 'Renewal Request' : 'New Application'; ?>
            </h1>
            <p><?php echo $isExistingTenant ? 'Submit your renewal documents for admin review' : 'Apply for a stall at XentroMall'; ?></p>
            <span class="request-type-badge <?php echo $isExistingTenant ? 'renewal' : 'new-app'; ?>">
                <?php echo $isExistingTenant ? '🔄 Renewal - Monthly Payment' : '📝 New Application - 3-Month Advance'; ?>
            </span>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['warning_message'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['warning_message']); ?></span>
            </div>
            <?php unset($_SESSION['warning_message']); ?>
        <?php endif; ?>

        <form action="unified_renewal_form.php<?php echo isset($_GET['stall_id']) ? '?stall_id=' . htmlspecialchars($_GET['stall_id']) : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php if ($isExistingTenant): ?>
                <input type="hidden" name="stall_id" value="<?php echo $stallId; ?>">
                <input type="hidden" name="tenant_detail_id" value="<?php echo $tenantDetailId; ?>">
                <input type="hidden" name="tradename" value="<?php echo htmlspecialchars($tenantData['tradename']); ?>">
                <input type="hidden" name="company_name" value="<?php echo htmlspecialchars($tenantData['company_name'] ?? ''); ?>">
                <input type="hidden" name="business_address" value="<?php echo htmlspecialchars($tenantData['business_address'] ?? ''); ?>">
                <input type="hidden" name="contact_person" value="<?php echo htmlspecialchars($tenantData['contact_person'] ?? ''); ?>">
                <input type="hidden" name="mobile" value="<?php echo htmlspecialchars($tenantData['mobile'] ?? ''); ?>">
            <?php endif; ?>
            <!-- Tenant Information Section -->
            <div class="form-section">
                <h2><i class="fas fa-user"></i> Tenant Information</h2>
                
                <?php if ($isExistingTenant && $tenantData): ?>
                    <div class="tenant-info">
                        <p class="text-lg font-bold text-emerald-800 mb-2 border-b border-emerald-200 pb-1">
                            <i class="fas fa-store mr-2"></i>Stall <?php echo htmlspecialchars($stallNumber); ?>
                        </p>
                        <p><strong>Trade Name:</strong> <?php echo htmlspecialchars($tenantData['tradename']); ?></p>
                        <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($tenantData['contact_person'] ?? 'N/A'); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($userEmail); ?></p>
                        <p><strong>Mobile:</strong> <?php echo htmlspecialchars($tenantData['mobile'] ?? 'N/A'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="tradename">Trade Name *</label>
                            <input type="text" name="tradename" id="tradename" required placeholder="Your business trade name">
                        </div>
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" name="company_name" id="company_name" placeholder="Official company name">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="business_address">Business Address *</label>
                        <input type="text" name="business_address" id="business_address" required placeholder="Current business address">
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label for="contact_person">Contact Person *</label>
                            <input type="text" name="contact_person" id="contact_person" required placeholder="Full name">
                        </div>
                        <div class="form-group">
                            <label for="mobile">Mobile Number *</label>
                            <input type="tel" name="mobile" id="mobile" required placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stall Selection (New Applications Only) -->
            <?php if (!$isExistingTenant): ?>
            <div class="form-section">
                <h2><i class="fas fa-store"></i> Stall Selection</h2>
                
                <div class="form-group">
                    <label for="stall_id">Select Stall *</label>
                    <select name="stall_id" id="stall_id" required onchange="updatePaymentSummary()">
                        <option value="">Choose a stall</option>
                        <?php foreach ($availableStalls as $stall): ?>
                            <option value="<?php echo $stall['id']; ?>" data-rate="<?php echo $stall['monthly_rate']; ?>">
                                <?php echo htmlspecialchars($stall['stall_number']); ?> - ₱<?php echo number_format($stall['monthly_rate'], 2); ?>/month (<?php echo htmlspecialchars($stall['floor_area']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment Summary -->
            <div class="payment-summary">
                <h3><i class="fas fa-credit-card mr-2"></i>Payment Summary</h3>
                <div class="payment-row">
                    <span>Monthly Rate:</span>
                    <span>₱<span id="monthlyRate">0.00</span></span>
                </div>
                <?php if ($isExistingTenant): ?>
                    <div class="payment-row">
                        <span>Payment Type:</span>
                        <span>Monthly Only</span>
                    </div>
                    <?php if ($lateFee > 0): ?>
                    <div class="payment-row">
                        <span>Late Renewal Fee:</span>
                        <span>₱<?php echo number_format($lateFee, 2); ?></span>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="payment-row">
                        <span>Payment Type:</span>
                        <span>3-Month Advance</span>
                    </div>
                    <div class="payment-row">
                        <span>Months:</span>
                        <span>3</span>
                    </div>
                <?php endif; ?>
                <div class="payment-row total">
                    <span>Total Amount Due:</span>
                    <span>₱<span id="totalAmount">0.00</span></span>
                </div>
            </div>

            <!-- Document Submission Section -->
            <div class="form-section">
                <h2><i class="fas fa-file-upload"></i> Required Documents</h2>
                
                <?php 
                    $mappedType = 'unknown';
                    if ($isExistingTenant && isset($tenantData['business_type'])): 
                        $rawType = $tenantData['business_type'];
                        if (stripos($rawType, 'corp') !== false || stripos($rawType, 'inc') !== false) {
                            $mappedType = 'corporation';
                        } elseif (stripos($rawType, 'sole') !== false || stripos($rawType, 'prop') !== false) {
                            $mappedType = 'sole_proprietorship';
                        } elseif (stripos($rawType, 'partner') !== false) {
                            $mappedType = 'partnership';
                        }
                    endif;
                ?>

                <!-- Business Type Selection -->
                <div class="business-type-container <?php echo ($isExistingTenant && $mappedType !== 'unknown') ? 'hidden' : ''; ?>">
                    <label class="block text-green-700 font-semibold mb-2">
                        Business Type <span class="text-red-500">*</span>
                    </label>
                    <div class="flex flex-col md:flex-row md:space-x-8 space-y-3 md:space-y-0">
                        <label class="inline-flex items-center cursor-pointer text-blue-700 font-medium">
                            <input name="business_type" id="type_corp" onclick="toggleRequirements()" <?php echo (!$isExistingTenant || $mappedType === 'unknown') ? 'required' : ''; ?> type="radio" value="corporation"/>
                            <span>Corporation</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer text-blue-700 font-medium">
                            <input name="business_type" id="type_sole" onclick="toggleRequirements()" type="radio" value="sole_proprietorship"/>
                            <span>Sole Proprietorship</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer text-blue-700 font-medium">
                            <input name="business_type" id="type_partner" onclick="toggleRequirements()" type="radio" value="partnership"/>
                            <span>Partnership</span>
                        </label>
                    </div>
                    <?php if ($isExistingTenant && $mappedType === 'unknown'): ?>
                        <p class="text-xs text-amber-600 mt-2 italic"><i class="fas fa-info-circle mr-1"></i> We couldn't automatically determine your business type. Please select one to continue.</p>
                    <?php endif; ?>
                </div>

                <?php if ($isExistingTenant && $mappedType !== 'unknown'): ?>
                    <input type="hidden" name="business_type" id="hidden_business_type" value="<?php echo htmlspecialchars($mappedType); ?>">
                <?php endif; ?>
                
                <!-- Document Upload Sections -->
                <div id="document-upload-section">
                    <div id="corporation-files" class="hidden file-upload-container">
                        <h3 class="font-semibold text-green-700 mb-2">Upload Corporation Documents:</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Letter of Intent/Concept Papers <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="letter_of_intent" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Company Profile <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="business_profile" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">SEC Registration <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="business_registration" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Secretary's Certificate <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="secretary_certificate" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">BIR Form 2303 <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="bir_registration" type="file" accept=".pdf,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id" type="file" accept=".jpg,.jpeg,.png,.pdf"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_2" type="file" accept=".jpg,.jpeg,.png,.pdf"/>
                            </div>
                        </div>
                    </div>

                    <div id="sole_proprietorship-files" class="hidden file-upload-container">
                        <h3 class="font-semibold text-green-700 mb-2">Upload Sole Proprietorship Documents:</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Letter of Intent/Concept Papers <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="letter_of_intent_sole" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">DTI Permit <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="business_registration_sole" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">BIR Form 2303 <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="bir_registration_sole" type="file" accept=".pdf,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_sole_1" type="file" accept=".jpg,.jpeg,.png,.pdf"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_sole_2" type="file" accept=".jpg,.jpeg,.png,.pdf"/>
                            </div>
                        </div>
                    </div>

                    <div id="partnership-files" class="hidden file-upload-container">
                        <h3 class="font-semibold text-green-700 mb-2">Upload Partnership Documents:</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Letter of Intent/Concept Papers <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="letter_of_intent_partner" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">DTI Permit <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="business_registration_partner" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Partnership Agreement <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="financial_statement_partner" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">BIR Form 2303 <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="bir_registration_partner" type="file" accept=".pdf,.jpg,.jpeg,.png"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Valid ID #1 with Specimen Signatures <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_partner_1" type="file" accept=".jpg,.jpeg,.png,.pdf"/>
                            </div>
                            <div>
                                <label class="block text-green-700 font-semibold mb-1">Valid ID #2 with Specimen Signatures <span class="text-red-500">*</span></label>
                                <input class="w-full rounded-md border border-blue-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" name="valid_id_partner_2" type="file" accept=".jpg,.jpeg,.png,.pdf"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" name="submit_renewal_request" class="submit-btn">
                <i class="fas fa-paper-plane mr-2"></i> Submit <?php echo $isExistingTenant ? 'Renewal Request' : 'Application'; ?>
            </button>
        </form>
    </div>

    <script>
        function updatePaymentSummary() {
            const stallSelect = document.getElementById('stall_id');
            const selectedOption = stallSelect.options[stallSelect.selectedIndex];
            const monthlyRate = parseFloat(selectedOption.getAttribute('data-rate')) || 0;
            
            document.getElementById('monthlyRate').textContent = monthlyRate.toFixed(2);
            
            // For new applications: 3 months advance
            const totalAmount = monthlyRate * 3;
            document.getElementById('totalAmount').textContent = totalAmount.toFixed(2);
        }
        
        function toggleRequirements() {
            let type = document.querySelector('input[name="business_type"]:checked')?.value;
            
            // Check for hidden input if radio is not available (for renewals)
            if (!type) {
                type = document.getElementById('hidden_business_type')?.value;
            }

            const corpFiles = document.getElementById('corporation-files');
            const soleFiles = document.getElementById('sole_proprietorship-files');
            const partnerFiles = document.getElementById('partnership-files');

            // Hide all file upload sections first
            corpFiles.classList.add('hidden');
            soleFiles.classList.add('hidden');
            partnerFiles.classList.add('hidden');

            // Remove required attribute from all file inputs
            document.querySelectorAll('#document-upload-section input[type="file"]').forEach(input => {
                input.removeAttribute('required');
            });

            // Show the selected one and set required attributes
            if (type === 'corporation') {
                corpFiles.classList.remove('hidden');
                // Set required for corporation files
                corpFiles.querySelectorAll('input[type="file"]').forEach(input => {
                    input.setAttribute('required', 'required');
                });
            } else if (type === 'sole_proprietorship') {
                soleFiles.classList.remove('hidden');
                // Set required for sole proprietorship files
                soleFiles.querySelectorAll('input[type="file"]').forEach(input => {
                    input.setAttribute('required', 'required');
                });
            } else if (type === 'partnership') {
                partnerFiles.classList.remove('hidden');
                // Set required for partnership files
                partnerFiles.querySelectorAll('input[type="file"]').forEach(input => {
                    input.setAttribute('required', 'required');
                });
            }
        }
        
        // Also need to handle form submission to ensure validation
        document.querySelector('form').addEventListener('submit', function(e) {
            // Re-validate the visible file inputs
            let type = document.querySelector('input[name="business_type"]:checked')?.value;
            if (!type) {
                type = document.getElementById('hidden_business_type')?.value;
            }

            let fileInputs = [];
            
            if (type === 'corporation') {
                fileInputs = document.querySelectorAll('#corporation-files input[type="file"]');
            } else if (type === 'sole_proprietorship') {
                fileInputs = document.querySelectorAll('#sole_proprietorship-files input[type="file"]');
            } else if (type === 'partnership') {
                fileInputs = document.querySelectorAll('#partnership-files input[type="file"]');
            }
            
            let isValid = true;
            fileInputs.forEach(input => {
                if (!input.value) {
                    isValid = false;
                    input.classList.add('border-red-500');
                } else {
                    input.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please upload all required documents for your selected business type.');
            }
        });
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($isExistingTenant && $tenantData): ?>
                // For renewals: show monthly rate
                const monthlyRate = <?php echo $monthlyRate ?? 0; ?>;
                console.log('Setting monthly rate to:', monthlyRate);
                document.getElementById('monthlyRate').textContent = monthlyRate.toFixed(2);
                document.getElementById('totalAmount').textContent = monthlyRate.toFixed(2);
                
                // Automatically toggle requirements based on detected business type
                toggleRequirements();
            <?php else: ?>
                // For new applications: initialize with first stall
                updatePaymentSummary();
            <?php endif; ?>
        });
    </script>
</body>
</html>
