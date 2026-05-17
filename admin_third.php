<?php
session_start();
require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'contract_helper.php';
require_once 'sms_integration.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle unified renewal approval/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['renewal_request_id'])) {
    $renewalId = $_POST['renewal_request_id'];
    $action = $_POST['action'];
    $adminFeedback = $_POST['admin_feedback'] ?? '';
    $adminId = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // Get renewal request details
        $stmt = $pdo->prepare("
            SELECT urr.*, u.username, u.email as users_email, td.email as tenant_email, td.tradename, td.mobile
            FROM unified_renewal_requests urr
            JOIN users u ON urr.user_id = u.id
            LEFT JOIN tenant_details td ON urr.user_id = td.user_id
            WHERE urr.id = ?
        ");
        $stmt->execute([$renewalId]);
        $renewal = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use tenant_details email first, fallback to users email
        $recipientEmail = !empty($renewal['tenant_email']) ? $renewal['tenant_email'] : $renewal['users_email'];
        $renewal['user_email'] = $recipientEmail; // Set for email sending

        // Debug: Log which email is being used
        error_log("Email recipient for renewal request ID {$renewalId}: {$recipientEmail}");
        error_log("Tenant email: " . ($renewal['tenant_email'] ?? 'empty'));
        error_log("Users email: " . ($renewal['users_email'] ?? 'empty'));

        if ($renewal) {
            // Determine new status
            $newStatus = ($action === 'approve') ? 'approved' : 'declined';

            // Update renewal request status
            $stmtUpdate = $pdo->prepare("
                UPDATE unified_renewal_requests 
                SET status = ?, admin_feedback = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$newStatus, $adminFeedback, $adminId, $renewalId]);

            // Send email notification
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mallxentro5@gmail.com';
                $mail->Password = 'iwld cjlr kmcy bxab';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->SMTPDebug = 0; // Set to 2 for debugging

                // Fix SSL certificate verification issue
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
                $mail->addAddress($renewal['user_email'], $renewal['tradename'] ?? $renewal['username']);

                $mail->isHTML(true);

                if ($action === 'approve') {
                    $mail->Subject = "✅ Renewal Request Approved - " . ($renewal['tradename'] ?? $renewal['username']);
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <div style='background: #10b981; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                                <h1 style='margin: 0; font-size: 28px;'>Renewal Approved!</h1>
                                <p style='margin: 10px 0 0; font-size: 16px;'>Your renewal request has been approved</p>
                            </div>
                            <div style='background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px;'>
                                <h2 style='color: #1f2937; margin-bottom: 20px;'>Request Details:</h2>
                                <p style='color: #4b5563; margin-bottom: 10px;'><strong>Business Name:</strong> " . htmlspecialchars($renewal['tradename'] ?? $renewal['username']) . "</p>
                                <p style='color: #4b5563; margin-bottom: 10px;'><strong>Request Type:</strong> " . ucfirst($renewal['request_type']) . "</p>
                                <p style='color: #4b5563; margin-bottom: 10px;'><strong>Business Type:</strong> " . ucfirst(str_replace('_', ' ', $renewal['business_type'])) . "</p>
                                <p style='color: #4b5563; margin-bottom: 20px;'><strong>Submitted:</strong> " . date('F d, Y', strtotime($renewal['submitted_at'])) . "</p>
                                <div style='background: #10b981; color: white; padding: 20px; border-radius: 8px; text-align: center;'>
                                    <h3 style='margin: 0 0 10px;'>Next Steps:</h3>
                                    <p style='margin: 0;'>Please proceed with the payment process to complete your renewal.</p>
                                </div>
                            </div>
                        </div>
                    ";
                } else {
                    $mail->Subject = "❌ Renewal Request Declined - " . ($renewal['tradename'] ?? $renewal['username']);
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <div style='background: #f97316; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                                <h1 style='margin: 0; font-size: 28px;'>⚠️ Renewal Declined</h1>
                                <p style='margin: 10px 0 0; font-size: 16px;'>Your renewal request was not approved</p>
                            </div>
                            <div style='background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px;'>
                                <h2 style='color: #1f2937; margin-bottom: 20px;'>Request Details:</h2>
                                <p style='color: #4b5563; margin-bottom: 10px;'><strong>Business Name:</strong> " . htmlspecialchars($renewal['tradename'] ?? $renewal['username']) . "</p>
                                <p style='color: #4b5563; margin-bottom: 10px;'><strong>Request Type:</strong> " . ucfirst($renewal['request_type']) . "</p>
                                <p style='color: #4b5563; margin-bottom: 10px;'><strong>Business Type:</strong> " . ucfirst(str_replace('_', ' ', $renewal['business_type'])) . "</p>
                                <p style='color: #4b5563; margin-bottom: 20px;'><strong>Submitted:</strong> " . date('F d, Y', strtotime($renewal['submitted_at'])) . "</p>";

                    if ($adminFeedback) {
                        $mail->Body .= "
                                <div style='background: #fff7ed; border: 1px solid #ffedd5; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                                    <h3 style='color: #ea580c; margin: 0 0 10px;'>Reason for Decline:</h3>
                                    <p style='color: #9a3412; margin: 0;'>" . htmlspecialchars($adminFeedback) . "</p>
                                </div>";
                    }

                    $mail->Body .= "
                                <div style='background: #3b82f6; color: white; padding: 20px; border-radius: 8px; text-align: center;'>
                                    <h3 style='margin: 0 0 10px;'>What's Next?</h3>
                                    <p style='margin: 0;'>You may submit a new renewal request after addressing the issues mentioned above.</p>
                                </div>
                            </div>
                        </div>
                    ";
                }

                $mail->send();

                // Send SMS notification to tenant using new SMS integration
                try {
                    // Get tenant phone number from tenant_details
                    $stmtMobile = $pdo->prepare("SELECT mobile FROM tenant_details WHERE user_id = ?");
                    $stmtMobile->execute([$renewal['user_id']]);
                    $tenantPhone = $stmtMobile->fetchColumn();

                    if ($tenantPhone) {
                        $sms = new IPROG_SMS();
                        $tenantName = $renewal['tradename'] ?? $renewal['username'] ?? 'Tenant';

                        if ($action === 'approve') {
                            $smsResult = $sms->sendRenewalApprovalSMS($tenantName, $tenantPhone, 'approved', $renewal['total_amount'] ?? 0);
                            error_log("✅ Renewal approval SMS sent to {$tenantPhone} for user {$renewal['user_id']}");
                        } else {
                            $smsResult = $sms->sendRenewalApprovalSMS($tenantName, $tenantPhone, 'declined', 0, $adminFeedback);
                            error_log("✅ Renewal declined SMS sent to {$tenantPhone} for user {$renewal['user_id']} with remarks: {$adminFeedback}");
                        }

                        if (!$smsResult['success']) {
                            error_log("❌ Failed to send renewal SMS: " . $smsResult['response']);
                        }
                    } else {
                        error_log("⚠️ No mobile number found for user {$renewal['user_id']} - renewal SMS not sent");
                    }
                } catch (Exception $e) {
                    error_log("SMS sending error for renewal: " . $e->getMessage());
                }

                // Log successful email
                error_log("Email sent successfully to {$renewal['user_email']} for renewal request ID: {$renewalId}");

            } catch (Exception $e) {
                // Log detailed error
                error_log("Email sending failed for renewal request ID: {$renewalId}");
                error_log("PHPMailer Error: " . $mail->ErrorInfo);
                error_log("Exception: " . $e->getMessage());
                error_log("Recipient: {$renewal['user_email']}");

                // Don't fail the whole process if email fails
                // Just continue with the database update
            }

            $pdo->commit();

            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Renewal request processed successfully']);
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch admin username and email from database
try {
    $stmt = $pdo->prepare('SELECT username, email FROM admins WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch();
    if ($admin) {
        $admin_username = $admin['username'];
        $admin_email = $admin['email'];
    } else {
        $admin_username = 'Admin';
        $admin_email = 'admin@xentromall.com';
    }
} catch (Exception $e) {
    $admin_username = 'Admin';
    $admin_email = 'admin@xentromall.com';
}

// Count pending items for notification badges
try {
    // Count pending applications (not approved or declined)
    $stmtPendingApps = $pdo->prepare("SELECT COUNT(*) FROM tenant_details WHERE (status != 'approved' AND status != 'declined') OR status IS NULL OR status = ''");
    $stmtPendingApps->execute();
    $pendingApplications = $stmtPendingApps->fetchColumn();

    // Count pending payments
    $stmtPendingPayments = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
    $stmtPendingPayments->execute();
    $pendingPayments = $stmtPendingPayments->fetchColumn();

    // Count pending renewals
    $stmtPendingRenewals = $pdo->prepare("SELECT COUNT(*) FROM unified_renewal_requests WHERE status = 'pending'");
    $stmtPendingRenewals->execute();
    $pendingRenewals = $stmtPendingRenewals->fetchColumn();

    // Count pending work permits
    $stmtPendingWorkPermits = $pdo->prepare("SELECT COUNT(*) FROM work_permits WHERE status = 'pending'");
    $stmtPendingWorkPermits->execute();
    $pendingWorkPermits = $stmtPendingWorkPermits->fetchColumn();

    // Fetch recent pending payments for the dashboard widget
    $stmtRecentPayments = $pdo->prepare("
        SELECT 
            p.*, 
            COALESCE(td.tradename, u.username) as tenant_name,
            s.stall_number
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN tenant_details td ON p.user_id = td.user_id
        LEFT JOIN stalls s ON p.stall_id = s.id
        WHERE p.status = 'pending'
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $stmtRecentPayments->execute();
    $recentPendingPayments = $stmtRecentPayments->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $pendingApplications = 0;
    $pendingPayments = 0;
    $pendingRenewals = 0;
    $pendingWorkPermits = 0;
}

try {
    // Ensure admin_viewed column exists (runs silently if already present)
    $pdo->exec("ALTER TABLE tenant_details ADD COLUMN IF NOT EXISTS admin_viewed TINYINT(1) NOT NULL DEFAULT 0");
} catch (Exception $e) { /* ignore */
}

// Count new (unviewed) applications for the notification banner
$newApplicationsCount = 0;
$newApplicationsList = [];
try {
    $stmtNewApps = $pdo->prepare("
        SELECT td.id, td.tradename, td.company_name, td.email, td.created_at, td.business_type,
               s.stall_number
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        WHERE COALESCE(td.admin_viewed, 0) = 0
        AND ((td.status != 'approved' AND td.status != 'declined') OR td.status IS NULL)
        ORDER BY td.created_at DESC
        LIMIT 5
    ");
    $stmtNewApps->execute();
    $newApplicationsList = $stmtNewApps->fetchAll(PDO::FETCH_ASSOC);
    $newApplicationsCount = count($newApplicationsList);
} catch (Exception $e) {
    $newApplicationsCount = 0;
    $newApplicationsList = [];
}

try {
    // Fetch applications with stall info and count of user's other applications
    $stmtApps = $pdo->prepare("
        SELECT 
            td.id, td.user_id, td.tradename, td.store_premises, td.store_location, td.ownership, 
            td.company_name, td.business_address, td.tin, td.office_tel, td.tenant_representative, 
            td.contact_person, td.position, td.contact_tel, td.mobile, td.email, td.prepared_by, 
            td.business_type, td.documents, td.created_at, td.stall_id,
            COALESCE(td.admin_viewed, 0) as admin_viewed,
            COALESCE(td.submission_count, 1) as submission_count,
            s.stall_number, s.description as stall_location, s.monthly_rate,
            (SELECT COUNT(*) FROM tenant_details WHERE user_id = td.user_id AND status = 'approved') as approved_stalls_count,
            (SELECT COUNT(*) FROM tenant_details WHERE user_id = td.user_id) as total_applications_count
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        WHERE (td.status != 'approved' AND td.status != 'declined') OR td.status IS NULL 
        ORDER BY td.created_at DESC
    ");
    $stmtApps->execute();
    $applications = $stmtApps->fetchAll();

    // Fetch total tenants count
    $stmtTotalTenants = $pdo->prepare("SELECT COUNT(*) as total FROM tenant_details");
    $stmtTotalTenants->execute();
    $totalTenants = $stmtTotalTenants->fetchColumn();

    // Fetch unpaid tenants count
    $stmtUnpaidTenants = $pdo->prepare("SELECT COUNT(DISTINCT td.id) FROM tenant_details td LEFT JOIN payments p ON td.user_id = p.user_id AND p.status = 'approved' WHERE p.id IS NULL");
    $stmtUnpaidTenants->execute();
    $unpaidTenants = $stmtUnpaidTenants->fetchColumn();

    // Fetch paid tenants count
    $stmtPaidTenants = $pdo->prepare("SELECT COUNT(DISTINCT p.user_id) FROM payments p WHERE p.status = 'approved'");
    $stmtPaidTenants->execute();
    $paidTenants = $stmtPaidTenants->fetchColumn();

    // Fetch overall tenants paid count (total approved payments)
    $stmtOverallTenantsPaid = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status = 'approved'");
    $stmtOverallTenantsPaid->execute();
    $overallTenantsPaid = $stmtOverallTenantsPaid->fetchColumn();

    // Fetch all approved tenants with stall and contract info
    $stmtAllTenants = $pdo->prepare("
        SELECT 
            td.id, td.user_id, td.tradename, td.store_premises, td.store_location, 
            td.company_name, td.business_address, td.tin, td.office_tel, 
            td.contact_person, td.mobile, td.email, td.business_type, td.created_at,
            td.bir_expiry_date, td.documents as initial_docs_path,
            (SELECT bir_document FROM extended_bir_submissions WHERE user_id = td.user_id AND status = 'approved' ORDER BY submitted_at DESC LIMIT 1) as latest_extended_bir,
            s.id as stall_id, s.stall_number, s.monthly_rate,
            tld.lease_start_date, tld.lease_expiration_date, tld.status as contract_status
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        LEFT JOIN tenants t ON t.email = td.email
        LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
        WHERE td.status = 'approved' 
        ORDER BY td.created_at DESC
    ");
    $stmtAllTenants->execute();
    $allTenants = $stmtAllTenants->fetchAll();
} catch (Exception $e) {
    $applications = [];
    $totalTenants = 0;
    $unpaidTenants = 0;
    $paidTenants = 0;
    $overallTenantsPaid = 0;
    $allTenants = [];
}

// Calculate BIR expiration counts
$expiredBIRCount = 0;
$expiringSoonBIRCount = 0;
$todayDate = new DateTime();
foreach ($allTenants as $t) {
    if (!empty($t['bir_expiry_date'])) {
        $expiryDate = new DateTime($t['bir_expiry_date']);
        $interval = $todayDate->diff($expiryDate);
        $daysRem = (int) $interval->format('%r%a');
        if ($daysRem < 0) {
            $expiredBIRCount++;
        } elseif ($daysRem <= 30) {
            $expiringSoonBIRCount++;
        }
    }
}

// Handle manual BIR expiry update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_bir_expiry') {
    $tenantId = $_POST['tenant_id'];
    $newExpiryDate = $_POST['bir_expiry_date'];

    try {
        $stmt = $pdo->prepare("UPDATE tenant_details SET bir_expiry_date = ? WHERE id = ?");
        $stmt->execute([$newExpiryDate, $tenantId]);
        $_SESSION['success'] = "BIR Expiry Date updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating BIR Expiry Date: " . $e->getMessage();
    }
    header("Location: admin_dashboard.php");
    exit;
}

// Handle expiration notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_expiration_notifications') {
    header('Content-Type: application/json');

    try {
        $tenants = json_decode($_POST['tenants'], true);
        $emailCount = 0;
        $smsCount = 0;
        $errors = [];

        // Debug logging
        error_log("Starting notification process for " . count($tenants) . " tenants");

        foreach ($tenants as $tenant) {
            $tenantId = $tenant['tenant_id'];
            $email = $tenant['email'];
            $mobile = $tenant['mobile'];
            $company = $tenant['company'];
            $daysRemaining = $tenant['days_remaining'];

            error_log("Processing tenant: {$company} (ID: {$tenantId})");

            // Get tenant details for email
            $stmt = $pdo->prepare("
                SELECT td.*, tld.lease_expiration_date, s.stall_number, s.monthly_rate
                FROM tenant_details td
                LEFT JOIN tenants t ON t.email = td.email
                LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
                LEFT JOIN stalls s ON s.id = td.stall_id
                WHERE td.id = ?
            ");
            $stmt->execute([$tenantId]);
            $tenantDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tenantDetails) {
                // Use email and mobile from tenant_details for accuracy
                $tenantEmail = $tenantDetails['email'];
                $tenantMobile = $tenantDetails['mobile'];

                error_log("Using tenant_details - Email: {$tenantEmail}, Mobile: {$tenantMobile}");

                // Send Email Notification
                try {
                    error_log("🔍 EMAIL DEBUG: Starting email send process for {$company}");
                    error_log("🔍 EMAIL DEBUG: Target email: {$tenantEmail}");
                    error_log("🔍 EMAIL DEBUG: Tenant ID: {$tenantId}");

                    // Try multiple SMTP configurations
                    $emailSent = false;
                    $lastError = '';

                    $smtpConfigs = [
                        [
                            'name' => 'Gmail TLS',
                            'host' => 'smtp.gmail.com',
                            'port' => 587,
                            'secure' => 'tls',
                            'username' => 'mallxentro5@gmail.com',
                            'password' => 'iwld cjlr kmcy bxab'
                        ],
                        [
                            'name' => 'Gmail SSL',
                            'host' => 'smtp.gmail.com',
                            'port' => 465,
                            'secure' => 'ssl',
                            'username' => 'mallxentro5@gmail.com',
                            'password' => 'iwld cjlr kmcy bxab'
                        ]
                    ];

                    foreach ($smtpConfigs as $config) {
                        try {
                            error_log("🔍 EMAIL DEBUG: Trying {$config['name']}...");

                            $mail = new PHPMailer(true);
                            $mail->isSMTP();
                            $mail->Host = $config['host'];
                            $mail->SMTPAuth = true;
                            $mail->Username = $config['username'];
                            $mail->Password = $config['password'];
                            $mail->SMTPSecure = $config['secure'];
                            $mail->Port = $config['port'];

                            // SSL fix
                            $mail->SMTPOptions = array(
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true
                                )
                            );

                            $mail->setFrom('mallxentro5@gmail.com', 'XentroMall Administration');
                            $mail->addAddress($tenantEmail, $company);

                            $mail->isHTML(true);
                            $mail->Subject = 'Contract Expiration Reminder - XentroMall';

                            $expirationDate = date('F d, Y', strtotime($tenantDetails['lease_expiration_date']));
                            $stallNumber = $tenantDetails['stall_number'] ?? 'N/A';
                            $monthlyRate = number_format($tenantDetails['monthly_rate'] ?? 0, 2);

                            $mail->Body = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                                    <div style='background: #10b981; background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0;'>
                                        <h1 style='color: white; margin: 0; font-size: 28px;'>Contract Expiration Reminder</h1>
                                        <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0;'>XentroMall Administration</p>
                                    </div>
                                    
                                    <div style='background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 25px; border-radius: 4px;'>
                                            <p style='margin: 0; color: #856404;'><strong>⚠️ Important Notice:</strong> Your contract is expiring soon!</p>
                                        </div>
                                        
                                        <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>Dear {$company},</p>
                                        
                                        <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>
                                            This is a friendly reminder that your lease contract for <strong>Stall #{$stallNumber}</strong> 
                                            will expire on <strong style='color: #dc3545;'>{$expirationDate}</strong> ({$daysRemaining} days from now).
                                        </p>
                                        
                                        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                            <h3 style='color: #495057; margin-top: 0;'>Contract Details:</h3>
                                            <ul style='color: #6c757d; line-height: 1.8;'>
                                                <li><strong>Company:</strong> {$company}</li>
                                                <li><strong>Stall Number:</strong> {$stallNumber}</li>
                                                <li><strong>Monthly Rate:</strong> ₱{$monthlyRate}</li>
                                                <li><strong>Expiration Date:</strong> {$expirationDate}</li>
                                                <li><strong>Days Remaining:</strong> {$daysRemaining} days</li>
                                            </ul>
                                        </div>
                                        
                                        <div style='background: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 25px 0; border-radius: 4px;'>
                                            <p style='margin: 0; color: #004085;'><strong>📋 Next Steps:</strong></p>
                                            <p style='margin: 10px 0 0 0; color: #004085;'>Please visit your tenant dashboard to submit a renewal application to continue your lease with us.</p>
                                        </div>
                                        
                                        <div style='text-align: center; margin: 30px 0;'>
                                            <a href='" . BASE_URL . "tenant_dashboard.php' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>
                                                Go to Tenant Dashboard
                                            </a>
                                        </div>
                                        
                                        <p style='font-size: 14px; color: #6c757d; margin-top: 30px; text-align: center;'>
                                            If you have any questions, please contact the mall administration office.
                                        </p>
                                        
                                        <div style='border-top: 1px solid #dee2e6; margin-top: 30px; padding-top: 20px; text-align: center;'>
                                            <p style='margin: 0; color: #6c757d; font-size: 12px;'>This is an automated message. Please do not reply to this email.</p>
                                        </div>
                                    </div>
                                </div>
                            ";

                            $mail->send();
                            $emailSent = true;
                            error_log("✅ EMAIL DEBUG: Email sent successfully via {$config['name']} to {$tenantEmail}");
                            break;

                        } catch (Exception $e) {
                            $lastError = $mail->ErrorInfo;
                            error_log("❌ EMAIL DEBUG: {$config['name']} failed: " . $lastError);
                            continue;
                        }
                    }

                    if (!$emailSent) {
                        // Final fallback: Simple text email with different approach
                        error_log("🔍 EMAIL DEBUG: All SMTP failed, trying simplified approach...");

                        $subject = 'Contract Expiration Reminder - XentroMall';
                        $message = "Contract Expiration Reminder - XentroMall\n\nDear {$company},\n\nThis is a friendly reminder that your lease contract for Stall #{$stallNumber} will expire on {$expirationDate} ({$daysRemaining} days from now).\n\nPlease visit your tenant dashboard to submit a renewal application: " . BASE_URL . "tenant_dashboard.php\n\nThis is an automated message. Please do not reply to this email.";
                        $headers = 'From: mallxentro5@gmail.com' . "\r\n" . 'Reply-To: mallxentro5@gmail.com' . "\r\n" . 'X-Mailer: PHP/' . phpversion();

                        if (mail($tenantEmail, $subject, $message, $headers)) {
                            $emailSent = true;
                            error_log("✅ EMAIL DEBUG: PHP mail() fallback successful to {$tenantEmail}");
                        } else {
                            error_log("❌ EMAIL DEBUG: All email methods failed for {$tenantEmail}");
                        }
                    }

                    if ($emailSent) {
                        $emailCount++;
                    } else {
                        $errorMsg = "All email methods failed for {$company}: " . $lastError;
                        $errors[] = $errorMsg;
                        error_log("❌ EMAIL DEBUG: " . $errorMsg);
                    }

                } catch (Exception $e) {
                    $errorMsg = "Email sending error for {$company}: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    error_log("❌ EMAIL DEBUG: " . $errorMsg);
                }

                // Send SMS Notification
                try {
                    error_log("Attempting to send SMS to: {$tenantMobile}");

                    // Create SMS instance and send
                    $smsService = new IPROG_SMS();
                    $smsMessage = "XENTROMALL REMINDER: Hi {$company}, your lease contract for Stall #{$stallNumber} expires on {$expirationDate} ({$daysRemaining} days). Please renew at your tenant dashboard to avoid interruption. Thank you.";
                    $smsResult = $smsService->sendSMS($tenantMobile, $smsMessage);

                    if ($smsResult['success']) {
                        $smsCount++;
                        error_log("✅ SMS sent successfully to {$tenantMobile}");
                    } else {
                        $errorMsg = "SMS failed for {$company}: HTTP {$smsResult['http_code']} - {$smsResult['response']}";
                        $errors[] = $errorMsg;
                        error_log("❌ " . $errorMsg);
                    }
                } catch (Exception $e) {
                    $errorMsg = "SMS failed for {$company}: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    error_log("❌ " . $errorMsg);
                }
            } else {
                $errorMsg = "Tenant details not found for ID: {$tenantId}";
                $errors[] = $errorMsg;
                error_log("❌ " . $errorMsg);
            }
        }

        error_log("Notification process completed. Email: {$emailCount}, SMS: {$smsCount}, Errors: " . count($errors));

        echo json_encode([
            'success' => true,
            'email_count' => $emailCount,
            'sms_count' => $smsCount,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        $errorMsg = "General error: " . $e->getMessage();
        error_log("❌ " . $errorMsg);
        echo json_encode([
            'success' => false,
            'message' => $errorMsg
        ]);
    }
    exit;
}

// Handle payment balance notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_payment_balance_notification') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/payment_reminder_helper.php';

    try {
        $tenantDetailId = $_POST['tenant_detail_id'];
        $customMessage = $_POST['custom_message'] ?? '';

        // Get tenant and lease details
        $stmt = $pdo->prepare("
            SELECT td.*, tld.lease_expiration_date, s.stall_number, s.monthly_rate, u.id as user_id
            FROM tenant_details td
            JOIN users u ON u.email = td.email
            LEFT JOIN tenants t ON t.email = td.email
            LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
            LEFT JOIN stalls s ON s.id = td.stall_id
            WHERE td.id = ?
        ");
        $stmt->execute([$tenantDetailId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            throw new Exception("Tenant not found.");
        }

        $userId = $tenant['user_id'];
        $company = $tenant['tradename'] ?? $tenant['company_name'];
        $stallNumber = $tenant['stall_number'] ?? 'N/A';
        $monthlyRate = (float) ($tenant['monthly_rate'] ?? 0);
        $expirationDate = $tenant['lease_expiration_date'];

        // Calculate remaining months
        $remainingMonths = 0;
        if (!empty($expirationDate)) {
            $remainingMonths = (int) ((strtotime($expirationDate) - time()) / (30 * 24 * 60 * 60));
            $remainingMonths = max(0, $remainingMonths);
        }
        $totalBalance = $remainingMonths * $monthlyRate;
        $formattedBalance = number_format($totalBalance, 2);

        // Trigger Email/SMS via helper
        $dueDate = date('F j, Y', strtotime('next month first day +4 days'));
        $results = sendPaymentReminder($userId, $dueDate, $totalBalance, $customMessage);

        // Also insert into notifications table for in-app
        $stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $inAppMsg = "Payment Balance Notice: You have {$remainingMonths} months remaining on your lease. Estimated total balance: ₱{$formattedBalance}. " . $customMessage;
        $stmtNotify->execute([$userId, $inAppMsg]);

        echo json_encode([
            'success' => true,
            'message' => 'Notification sent successfully!',
            'email' => $results['email']['success'] ?? false,
            'sms' => $results['sms']['success'] ?? false,
            'months' => $remainingMonths,
            'balance' => $totalBalance
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle manual due date reminders from Unpaid Tab
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_due_date_reminder') {
    header('Content-Type: application/json');
    require_once __DIR__ . '/payment_reminder_helper.php';
    require_once __DIR__ . '/admin_settings_helper.php';

    try {
        $userId = $_POST['user_id'];
        $amount = (float) ($_POST['amount'] ?? 0);

        // Get due day from settings
        $dueDay = (int) getAdminSetting('payment_due_day', 5);
        $today = new DateTime();
        $nextDue = new DateTime();
        $nextDue->setDate($today->format('Y'), $today->format('m'), $dueDay);

        if ($nextDue <= $today) {
            $nextDue->modify('+1 month');
        }
        $formattedDueDate = $nextDue->format('F j, Y');

        $results = sendPaymentReminder($userId, $formattedDueDate, $amount);

        // In-app notification
        $stmtNotify = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $inAppMsg = "Payment Reminder: Your rent of ₱" . number_format($amount, 2) . " is due on " . $formattedDueDate . ".";
        $stmtNotify->execute([$userId, $inAppMsg]);

        echo json_encode([
            'success' => true,
            'message' => 'Reminder sent successfully!',
            'email' => $results['email']['success'],
            'sms' => $results['sms']['success']
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}


// Handle monthly reports AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_monthly_report' && isset($_GET['month'])) {
    $month = $_GET['month']; // format: YYYY-MM
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));

    try {
        // 1. Sales History
        $stmtSales = $pdo->prepare("
            SELECT p.*, u.username, td.tradename, s.stall_number
            FROM payments p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN tenant_details td ON td.user_id = p.user_id
            LEFT JOIN stalls s ON s.id = p.stall_id
            WHERE p.status = 'approved' 
            AND p.payment_date BETWEEN ? AND ?
            ORDER BY p.payment_date DESC
        ");
        $stmtSales->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
        $salesHistory = $stmtSales->fetchAll(PDO::FETCH_ASSOC);

        // 2. Total Sales
        $totalSales = 0;
        foreach ($salesHistory as $sale) {
            $totalSales += $sale['amount'];
        }

        // 3. List of Tenants (Approved as of that month)
        $stmtTenants = $pdo->prepare("
            SELECT td.*, s.stall_number, u.username
            FROM tenant_details td
            JOIN users u ON td.user_id = u.id
            LEFT JOIN stalls s ON s.id = td.stall_id
            WHERE td.status = 'approved'
            AND td.created_at <= ?
        ");
        $stmtTenants->execute([$monthEnd . ' 23:59:59']);
        $tenantsList = $stmtTenants->fetchAll(PDO::FETCH_ASSOC);

        // 4. Pending Lease Applications (Submitted in that month)
        $stmtPending = $pdo->prepare("
            SELECT td.*, s.stall_number, u.username
            FROM tenant_details td
            JOIN users u ON td.user_id = u.id
            LEFT JOIN stalls s ON s.id = td.stall_id
            WHERE td.status = 'pending'
            AND td.created_at BETWEEN ? AND ?
        ");
        $stmtPending->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
        $pendingApplications = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        // 5. Outstanding Balances
        // Tenants who are approved but have no 'approved' payment in the selected billing month
        $stmtOutstanding = $pdo->prepare("
            SELECT td.*, s.stall_number, s.monthly_rate, u.username
            FROM tenant_details td
            JOIN users u ON td.user_id = u.id
            LEFT JOIN stalls s ON s.id = td.stall_id
            WHERE td.status = 'approved'
            AND td.created_at <= ?
            AND td.user_id NOT IN (
                SELECT user_id 
                FROM payments 
                WHERE status = 'approved' 
                AND billing_month = ?
            )
        ");
        $stmtOutstanding->execute([$monthEnd . ' 23:59:59', $monthStart]);
        $outstandingBalances = $stmtOutstanding->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total outstanding
        $totalOutstanding = 0;
        foreach ($outstandingBalances as $o) {
            $totalOutstanding += (float) ($o['monthly_rate'] ?? 0);
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'sales_history' => $salesHistory,
                'total_sales' => $totalSales,
                'total_outstanding' => $totalOutstanding,
                'tenants_list' => $tenantsList,
                'pending_applications' => $pendingApplications,
                'outstanding_balances' => $outstandingBalances,
                'selected_month' => date('F Y', strtotime($monthStart))
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle daily payment trends AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_payment_trends_daily' && isset($_GET['month'])) {
    $month = $_GET['month']; // format: YYYY-MM
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $daysInMonth = (int) date('t', strtotime($monthStart));

    try {
        $stmt = $pdo->prepare("
            SELECT 
                DAY(payment_date) as day, 
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as approved_amount,
                SUM(CASE WHEN status != 'approved' THEN amount ELSE 0 END) as other_amount
            FROM payments
            WHERE payment_date BETWEEN ? AND ?
            GROUP BY DAY(payment_date)
        ");
        $stmt->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
        $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $approvedSeries = array_fill(1, $daysInMonth, 0);
        $otherSeries = array_fill(1, $daysInMonth, 0);

        foreach ($dailyData as $row) {
            $day = (int) $row['day'];
            $approvedSeries[$day] = (float) $row['approved_amount'];
            $otherSeries[$day] = (float) $row['other_amount'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'days' => range(1, $daysInMonth),
                'approved' => array_values($approvedSeries),
                'other' => array_values($otherSeries)
            ]
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle applicant history AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_applicant_history') {
    $year = isset($_GET['year']) ? $_GET['year'] : 'all';

    try {
        $query = "
            SELECT 
                td.id,
                td.user_id,
                u.username,
                u.email as user_email,
                td.tradename,
                td.business_type,
                td.status,
                COALESCE(td.submission_count, 1) as submission_count,
                CASE WHEN td.status = 'approved' THEN 1 ELSE 0 END as approved_count,
                CASE WHEN td.status = 'declined' THEN 1 ELSE 0 END as declined_count,
                CASE WHEN (td.status IS NULL OR (td.status != 'approved' AND td.status != 'declined')) THEN 1 ELSE 0 END as pending_count,
                td.created_at as first_application_date,
                td.created_at as latest_application_date
            FROM tenant_details td
            LEFT JOIN users u ON u.id = td.user_id
        ";

        $params = [];
        if ($year !== 'all') {
            $query .= " WHERE YEAR(td.created_at) = ? ";
            $params[] = $year;
        }

        $query .= " ORDER BY COALESCE(td.submission_count, 1) DESC, td.created_at DESC ";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $history
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle per-applicant detailed history AJAX request
if (isset($_GET['action']) && $_GET['action'] === 'get_applicant_detail_history') {
    $tenantDetailId = isset($_GET['tenant_detail_id']) ? (int) $_GET['tenant_detail_id'] : 0;

    if (!$tenantDetailId) {
        echo json_encode(['success' => false, 'message' => 'Missing tenant_detail_id']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT status, feedback, submission_count, created_at
            FROM application_status_history
            WHERE tenant_detail_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$tenantDetailId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $logs
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch active contracts
try {
    $stmtActiveContracts = $pdo->prepare("
        SELECT 
            td.id as tenant_detail_id,
            td.tradename as company_name,
            td.email,
            tld.lease_start_date,
            tld.lease_expiration_date as lease_end_date,
            tld.status as contract_status,
            s.stall_number,
            s.monthly_rate,
            td.mobile,
            td.user_id,
            t.id as tenant_id
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        LEFT JOIN tenants t ON t.email = td.email
        INNER JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
        WHERE tld.lease_expiration_date >= CURDATE()
        AND td.status = 'approved'
        ORDER BY tld.lease_expiration_date ASC
    ");
    $stmtActiveContracts->execute();
    $activeContracts = $stmtActiveContracts->fetchAll();
} catch (Exception $e) {
    $activeContracts = [];
}

ensureTenantContractsTable($pdo);
$latestContractsByTenantDetail = [];
try {
    $stmtLatestContracts = $pdo->query("
        SELECT tenant_detail_id, id, contract_status, created_at, version
        FROM tenant_contracts
        ORDER BY created_at DESC
    ");
    while ($row = $stmtLatestContracts->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($latestContractsByTenantDetail[$row['tenant_detail_id']])) {
            $latestContractsByTenantDetail[$row['tenant_detail_id']] = $row;
        }
    }
} catch (Exception $e) {
    $latestContractsByTenantDetail = [];
}

try {
    $stmtMaint = $pdo->prepare("SELECT permit_no, date_filed, tenant_name, scope_of_work, security_posting, rate_security, charge_security, janitorial_deployment, rate_janitorial, charge_janitorial, maintenance, rate_maintenance, charge_maintenance, personnel, created_at FROM work_permits ORDER BY created_at DESC");
    $stmtMaint->execute();
    $maintenanceRequests = $stmtMaint->fetchAll();
} catch (Exception $e) {
    $maintenanceRequests = [];
}

// Fetch contract renewal requests (new system)
try {
    $stmtContractRenewals = $pdo->prepare("SELECT cr.*, td.tradename, td.email, td.mobile, s.stall_number, s.monthly_rate,
                                    tld.lease_expiration_date as old_expiration
                                    FROM contract_renewals cr
                                    INNER JOIN tenant_details td ON td.user_id = cr.user_id
                                    LEFT JOIN stalls s ON s.id = td.stall_id
                                    LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = cr.tenant_id
                                    WHERE cr.status = 'pending'
                                    ORDER BY cr.submitted_at DESC");
    $stmtContractRenewals->execute();
    $contractRenewals = $stmtContractRenewals->fetchAll();

    // Fetch expiring contracts (within 30 days)
    $stmtExpiring = $pdo->prepare("SELECT tld.*, td.tradename, td.email, td.mobile, td.user_id, s.stall_number,
                                    DATEDIFF(tld.lease_expiration_date, CURDATE()) as days_remaining
                                    FROM tenant_lease_dates tld
                                    INNER JOIN tenants t ON t.id = tld.tenant_id
                                    INNER JOIN tenant_details td ON td.user_id IN (
                                        SELECT id FROM users WHERE email = t.email
                                    )
                                    LEFT JOIN stalls s ON s.id = td.stall_id
                                    WHERE tld.status IN ('active', 'expiring_soon', 'grace_period')
                                    AND DATEDIFF(tld.lease_expiration_date, CURDATE()) <= 30
                                    ORDER BY tld.lease_expiration_date ASC");
    $stmtExpiring->execute();
    $expiringContracts = $stmtExpiring->fetchAll();
} catch (Exception $e) {
    $contractRenewals = [];
    $expiringContracts = [];
}

// Fetch unified renewal requests
try {
    $stmtUnifiedRenewals = $pdo->prepare("
        SELECT urr.*, u.username, u.email as user_email, td.tradename, s.stall_number
        FROM unified_renewal_requests urr
        JOIN users u ON urr.user_id = u.id
        LEFT JOIN tenant_details td ON urr.user_id = td.user_id
        LEFT JOIN stalls s ON urr.stall_id = s.id
        WHERE urr.status = 'pending'
        ORDER BY urr.submitted_at DESC
    ");
    $stmtUnifiedRenewals->execute();
    $unifiedRenewals = $stmtUnifiedRenewals->fetchAll();
} catch (Exception $e) {
    $unifiedRenewals = [];
}

// Fetch old renewal requests (for backward compatibility)
try {
    $stmtRenewal = $pdo->prepare("SELECT rr.id, rr.tenant_id, rr.renewal_date, rr.submitted_at, u.username FROM renewal_requests rr JOIN users u ON rr.tenant_id = u.id ORDER BY rr.submitted_at DESC");
    $stmtRenewal->execute();
    $renewalRequests = $stmtRenewal->fetchAll();
} catch (Exception $e) {
    $renewalRequests = [];
}

// Fetch payments with username
try {
    $stmtPayments = $pdo->prepare("
        SELECT 
            p.id,
            p.user_id,
            p.payment_image,
            p.payment_date,
            p.status,
            p.amount,
            p.payment_method,
            p.billing_month,
            p.payment_type,
            p.rent_amount,
            p.utilities_amount,
            p.rent_due,
            p.rent_balance,
            p.stall_id,
            u.username,
            u.email,
            td.tradename,
            td.tenant_representative,
            s.stall_number,
            s.description AS stall_description
        FROM payments p
        JOIN users u ON p.user_id = u.id
        LEFT JOIN stalls s ON p.stall_id = s.id
        LEFT JOIN tenant_details td ON td.user_id = p.user_id AND td.stall_id = p.stall_id
        WHERE p.status NOT IN ('approved', 'declined')
        ORDER BY p.payment_date DESC
    ");
    $stmtPayments->execute();
    $payments = $stmtPayments->fetchAll();
} catch (Exception $e) {
    $payments = [];
}

// Fetch Extended BIR submissions
try {
    $stmtBIR = $pdo->prepare("SELECT eb.*, td.tradename, td.email, td.mobile, cr.total_amount, cr.old_contract_end, cr.payment_proof
                              FROM extended_bir_submissions eb
                              LEFT JOIN contract_renewals cr ON cr.id = eb.renewal_id
                              INNER JOIN tenant_details td ON td.user_id = eb.user_id
                              ORDER BY eb.submitted_at DESC");
    $stmtBIR->execute();
    $birSubmissions = $stmtBIR->fetchAll();
} catch (Exception $e) {
    $birSubmissions = [];
}

// Fetch History Data - Declined Applications
try {
    $stmtDeclined = $pdo->prepare("SELECT td.*, td.created_at as declined_date
                                   FROM tenant_details td
                                   WHERE td.status NOT IN ('approved', 'declined')
                                   ORDER BY td.created_at DESC");
    $stmtDeclined->execute();
    $declinedApplications = $stmtDeclined->fetchAll();
} catch (Exception $e) {
    $declinedApplications = [];
}

// Fetch History Data - Paid Tenants
try {
    $stmtPaidHistory = $pdo->prepare("SELECT p.*, td.tradename, td.company_name, td.email, td.mobile, s.stall_number, u.username
                                      FROM payments p
                                      INNER JOIN users u ON p.user_id = u.id
                                      LEFT JOIN tenant_details td ON td.user_id = p.user_id
                                      LEFT JOIN stalls s ON s.id = td.stall_id
                                      WHERE p.status = 'approved'
                                      ORDER BY p.payment_date DESC");
    $stmtPaidHistory->execute();
    $paidHistory = $stmtPaidHistory->fetchAll();
} catch (Exception $e) {
    $paidHistory = [];
}

// Fetch History Data - Unpaid Tenants
try {
    $stmtUnpaidHistory = $pdo->prepare("SELECT td.*, s.stall_number, s.monthly_rate
                                        FROM tenant_details td
                                        LEFT JOIN stalls s ON s.id = td.stall_id
                                        LEFT JOIN payments p ON td.user_id = p.user_id AND p.status = 'approved'
                                        WHERE td.status = 'approved' AND p.id IS NULL
                                        ORDER BY td.created_at DESC");
    $stmtUnpaidHistory->execute();
    $unpaidHistory = $stmtUnpaidHistory->fetchAll();
} catch (Exception $e) {
    $unpaidHistory = [];
}

// ===== COMPREHENSIVE REPORTS DATA FETCHING =====

// Fetch Unified Applications History (Approved + Declined)
try {
    $stmtUnifiedApplications = $pdo->prepare("
        SELECT 
            td.id, td.user_id, td.tradename, td.store_premises, td.store_location, td.ownership, 
            td.company_name, td.business_address, td.tin, td.office_tel, td.tenant_representative, 
            td.contact_person, td.position, td.contact_tel, td.mobile, td.email, td.prepared_by, 
            td.business_type, td.documents, td.created_at, td.stall_id, td.status,
            s.stall_number, s.description as stall_location, s.monthly_rate,
            u.username,
            CASE 
                WHEN td.status = 'approved' THEN 'Approved'
                WHEN td.status = 'declined' THEN 'Declined'
                ELSE 'Pending'
            END as application_status,
            CASE 
                WHEN td.status = 'approved' THEN 'success'
                WHEN td.status = 'declined' THEN 'error'
                ELSE 'warning'
            END as status_color
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        LEFT JOIN users u ON td.user_id = u.id
        WHERE td.status IN ('approved', 'declined')
        ORDER BY td.created_at DESC
    ");
    $stmtUnifiedApplications->execute();
    $unifiedApplications = $stmtUnifiedApplications->fetchAll();
} catch (Exception $e) {
    $unifiedApplications = [];
}

// Fetch Unified Payments History (Paid + Unpaid)
try {
    $stmtUnifiedPayments = $pdo->prepare("
        SELECT 
            td.id as tenant_detail_id, td.user_id, td.tradename, td.company_name, td.email, td.mobile,
            s.stall_number, s.monthly_rate,
            u.username,
            p.id as payment_id, p.payment_image, p.payment_date, p.status as payment_status, 
            p.amount, p.payment_method, p.billing_month, p.payment_type, p.rent_amount, 
            p.utilities_amount, p.rent_due, p.rent_balance,
            CASE 
                WHEN p.status = 'approved' THEN 'Paid'
                WHEN p.status = 'pending' THEN 'Pending'
                WHEN p.status = 'declined' THEN 'Declined'
                ELSE 'Unpaid'
            END as payment_status_text,
            CASE 
                WHEN p.status = 'approved' THEN 'success'
                WHEN p.status = 'pending' THEN 'warning'
                WHEN p.status = 'declined' THEN 'error'
                ELSE 'secondary'
            END as payment_color
        FROM tenant_details td
        LEFT JOIN stalls s ON s.id = td.stall_id
        LEFT JOIN users u ON td.user_id = u.id
        LEFT JOIN payments p ON td.user_id = p.user_id AND s.id = p.stall_id
        WHERE td.status = 'approved'
        ORDER BY 
            CASE 
                WHEN p.payment_date IS NOT NULL THEN p.payment_date
                ELSE td.created_at
            END DESC
    ");
    $stmtUnifiedPayments->execute();
    $unifiedPayments = $stmtUnifiedPayments->fetchAll();
} catch (Exception $e) {
    $unifiedPayments = [];
}

// Fetch Work Permit Reports
try {
    $stmtWorkPermitReports = $pdo->prepare("
        SELECT 
            permit_no, work_permits.date_filed, tenant_name, scope_of_work, 
            security_posting, rate_security, charge_security, 
            janitorial_deployment, rate_janitorial, charge_janitorial, 
            maintenance, rate_maintenance, charge_maintenance, 
            personnel, work_permits.status, work_permits.created_at,
            td.tradename, td.email, td.mobile, s.stall_number,
            CASE 
                WHEN work_permits.status = 'approved' THEN 'Approved'
                WHEN work_permits.status = 'pending' THEN 'Pending'
                WHEN work_permits.status = 'declined' THEN 'Declined'
                ELSE work_permits.status
            END as permit_status_text,
            CASE 
                WHEN work_permits.status = 'approved' THEN 'success'
                WHEN work_permits.status = 'pending' THEN 'warning'
                WHEN work_permits.status = 'declined' THEN 'error'
                ELSE 'secondary'
            END as permit_color,
            (charge_security + charge_janitorial + charge_maintenance) as total_charges
        FROM work_permits
        LEFT JOIN tenant_details td ON tenant_name = td.tradename
        LEFT JOIN stalls s ON td.stall_id = s.id
        ORDER BY work_permits.date_filed DESC
    ");
    $stmtWorkPermitReports->execute();
    $workPermitReports = $stmtWorkPermitReports->fetchAll();
} catch (Exception $e) {
    $workPermitReports = [];
}

// Fetch Renewal Reports
try {
    $stmtRenewalReports = $pdo->prepare("
        SELECT 
            urr.id, urr.user_id, urr.request_type, urr.business_type, urr.total_amount, 
            urr.submitted_at, urr.status, urr.admin_reviewed_at, urr.admin_reviewed_by, urr.admin_feedback,
            u.username, u.email as user_email,
            td.tradename, td.email as tenant_email, td.mobile,
            s.stall_number, s.monthly_rate,
            admin.username as admin_username,
            p.id as payment_id, p.payment_date, p.status as payment_status, p.amount as payment_amount,
            CASE 
                WHEN urr.status = 'approved' THEN 'Approved'
                WHEN urr.status = 'pending' THEN 'Pending'
                WHEN urr.status = 'declined' THEN 'Declined'
                ELSE urr.status
            END as renewal_status_text,
            CASE 
                WHEN urr.status = 'approved' THEN 'success'
                WHEN urr.status = 'pending' THEN 'warning'
                WHEN urr.status = 'declined' THEN 'error'
                ELSE 'secondary'
            END as renewal_color,
            CASE 
                WHEN p.status = 'approved' THEN 'Paid'
                WHEN p.status = 'pending' THEN 'Pending'
                WHEN p.status = 'declined' THEN 'Declined'
                ELSE 'Unpaid'
            END as payment_status_text
        FROM unified_renewal_requests urr
        LEFT JOIN users u ON urr.user_id = u.id
        LEFT JOIN tenant_details td ON urr.user_id = td.user_id
        LEFT JOIN stalls s ON urr.stall_id = s.id
        LEFT JOIN users admin ON urr.admin_reviewed_by = admin.id
        LEFT JOIN payments p ON urr.user_id = p.user_id AND p.payment_type = 'renewal'
        ORDER BY urr.submitted_at DESC
    ");
    $stmtRenewalReports->execute();
    $renewalReports = $stmtRenewalReports->fetchAll();
} catch (Exception $e) {
    $renewalReports = [];
}

// Fetch unique years for filtering
$applicantYears = [];
try {
    $stmtYears = $pdo->query("SELECT DISTINCT YEAR(created_at) as year FROM tenant_details WHERE created_at IS NOT NULL ORDER BY year DESC");
    $applicantYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // silence
}

// Fetch Applicant History (aggregated by user)
$applicantHistoryError = null;
try {
    // Auto-create column if absent
    try {
        $pdo->exec("ALTER TABLE tenant_details ADD COLUMN IF NOT EXISTS submission_count INT NOT NULL DEFAULT 1");
    } catch (Exception $ex) { /* column may already exist */
    }

    $stmtApplicantHistory = $pdo->prepare("
        SELECT 
            td.id,
            td.user_id,
            u.username,
            u.email as user_email,
            td.tradename,
            td.business_type,
            td.status,
            COALESCE(td.submission_count, 1) as submission_count,
            CASE WHEN td.status = 'approved' THEN 1 ELSE 0 END as approved_count,
            CASE WHEN td.status = 'declined' THEN 1 ELSE 0 END as declined_count,
            CASE WHEN (td.status IS NULL OR (td.status != 'approved' AND td.status != 'declined')) THEN 1 ELSE 0 END as pending_count,
            td.created_at as first_application_date,
            td.created_at as latest_application_date
        FROM tenant_details td
        LEFT JOIN users u ON u.id = td.user_id
        ORDER BY COALESCE(td.submission_count, 1) DESC, td.created_at DESC
    ");
    $stmtApplicantHistory->execute();
    $applicantHistory = $stmtApplicantHistory->fetchAll();
} catch (Exception $e) {
    $applicantHistory = [];
    $applicantHistoryError = $e->getMessage();
}

// Dashboard analytics datasets
$monthsKeys = [];
$monthsLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . " -$i months");
    $key = date('Y-m', $ts);
    $monthsKeys[] = $key;
    $monthsLabels[] = date('M', $ts);
}
$paymentsApprovedSeries = array_fill(0, count($monthsKeys), 0);
$paymentsOtherSeries = array_fill(0, count($monthsKeys), 0);
foreach ($payments as $p) {
    $key = date('Y-m', strtotime($p['payment_date']));
    $idx = array_search($key, $monthsKeys);
    if ($idx !== false) {
        if (strtolower($p['status']) === 'approved') {
            $paymentsApprovedSeries[$idx]++;
        } else {
            $paymentsOtherSeries[$idx]++;
        }
    }
}
$applicationsSeries = array_fill(0, count($monthsKeys), 0);
foreach ($applications as $a) {
    $key = date('Y-m', strtotime($a['created_at']));
    $idx = array_search($key, $monthsKeys);
    if ($idx !== false) {
        $applicationsSeries[$idx]++;
    }
}
$renewalsSeries = array_fill(0, count($monthsKeys), 0);
foreach ($renewalRequests as $r) {
    $key = date('Y-m', strtotime($r['submitted_at']));
    $idx = array_search($key, $monthsKeys);
    if ($idx !== false) {
        $renewalsSeries[$idx]++;
    }
}

// Fetch Lease Timelines for Gantt Chart
$leaseTimelines = [];
try {
    $stmtGantt = $pdo->prepare("
        SELECT 
            td.tradename,
            tld.lease_start_date,
            tld.lease_expiration_date
        FROM tenant_details td
        INNER JOIN tenants t ON td.email = t.email
        INNER JOIN tenant_lease_dates tld ON t.id = tld.tenant_id
        WHERE td.status = 'approved' 
        AND tld.status IN ('active', 'expiring_soon')
        ORDER BY tld.lease_expiration_date ASC
    ");
    $stmtGantt->execute();
    while ($row = $stmtGantt->fetch(PDO::FETCH_ASSOC)) {
        $leaseTimelines[] = [
            'x' => $row['tradename'],
            'y' => [
                strtotime($row['lease_start_date']) * 1000,
                strtotime($row['lease_expiration_date']) * 1000
            ]
        ];
    }
} catch (Exception $e) {
    error_log("Gantt Chart data error: " . $e->getMessage());
}

// Billing: handle per-tenant bill updates and fetch list
$billingSuccessMessage = '';
$billingErrorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_bills'])) {
    $billingMonthRaw = trim($_POST['billing_month'] ?? '');
    if ($billingMonthRaw !== '' && preg_match('/^\d{4}-\d{2}$/', $billingMonthRaw)) {
        $billingMonth = $billingMonthRaw . '-01';

        // Debug: Log received data
        $debugLog = [];
        $debugLog[] = "=== RECEIVED POST DATA (IMAGE ONLY) ===";
        $debugLog[] = "Billing Month: {$billingMonth}";

        // Ensure columns exist
        try {
            $pdo->exec("ALTER TABLE tenant_expenses ADD COLUMN IF NOT EXISTS electric_bill_image VARCHAR(255) NULL AFTER bill_image");
        } catch (Exception $e) { /* silent fallback */
        }

        // Ensure upload directory exists

        try {
            $pdo->beginTransaction();
            $sql = "INSERT INTO tenant_expenses (user_id, stall_id, water_bill, electric_bill, billing_month, bill_image, electric_bill_image)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        water_bill = VALUES(water_bill),
                        electric_bill = VALUES(electric_bill),
                        bill_image = IFNULL(VALUES(bill_image), bill_image),
                        electric_bill_image = IFNULL(VALUES(electric_bill_image), electric_bill_image)";
            $stmt = $pdo->prepare($sql);
            $updatedCount = 0;
            $notifiedTenants = [];

            // Collect all unique keys from uploads and amounts
            $uploadKeys = [];
            if (!empty($_FILES['bill_image']['name'])) {
                $uploadKeys = array_merge($uploadKeys, array_keys($_FILES['bill_image']['name']));
            }
            if (!empty($_FILES['electric_bill_image']['name'])) {
                $uploadKeys = array_merge($uploadKeys, array_keys($_FILES['electric_bill_image']['name']));
            }
            if (!empty($_POST['water_bill'])) {
                $uploadKeys = array_merge($uploadKeys, array_keys($_POST['water_bill']));
            }
            $uploadKeys = array_unique($uploadKeys);

            foreach ($uploadKeys as $key) {
                // Key format: user_id_stall_id
                $parts = explode('_', $key);
                if (count($parts) != 2) {
                    $debugLog[] = "Skipped key '{$key}' - invalid format";
                    continue;
                }

                $uid = (int) $parts[0];
                $stallId = (int) $parts[1];

                $hasWaterFile = !empty($_FILES['bill_image']['name'][$key]);
                $hasElectricFile = !empty($_FILES['electric_bill_image']['name'][$key]);

                // Handle Water Bill Image
                $waterImagePath = null;
                if ($hasWaterFile) {
                    $tmpName = $_FILES['bill_image']['tmp_name'][$key];
                    $fileName = time() . '_water_' . $uid . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['bill_image']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $waterImagePath = $targetPath;
                        $debugLog[] = "Uploaded water photo for user_id:{$uid}";
                    }
                }

                // Handle Electric Bill Image
                $electricImagePath = null;
                if ($hasElectricFile) {
                    $tmpName = $_FILES['electric_bill_image']['tmp_name'][$key];
                    $fileName = time() . '_electric_' . $uid . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['electric_bill_image']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $electricImagePath = $targetPath;
                        $debugLog[] = "Uploaded electric photo for user_id:{$uid}";
                    }
                }

                $waterAmount = floatval($_POST['water_bill'][$key] ?? 0);
                $electricAmount = floatval($_POST['electric_bill'][$key] ?? 0);

                if (!$hasWaterFile && !$hasElectricFile && $waterAmount <= 0 && $electricAmount <= 0) {
                    continue;
                }

                try {
                    $stmt->execute([$uid, $stallId, $waterAmount, $electricAmount, $billingMonth, $waterImagePath, $electricImagePath]);
                    $updatedCount++;
                    $debugLog[] = "✅ Saved billing (W:₱{$waterAmount}, E:₱{$electricAmount}) for user_id:{$uid} stall_id:{$stallId}";

                    // Store tenant info for notifications
                    $notifiedTenants[] = [
                        'user_id' => $uid,
                        'stall_id' => $stallId
                    ];
                } catch (PDOException $e) {
                    $debugLog[] = "❌ Error saving user_id:{$uid} stall_id:{$stallId} - " . $e->getMessage();
                }
            }
            $pdo->commit();

            // Store debug log in session for display
            $_SESSION['billing_debug_log'] = $debugLog;

            // Send notifications and emails to updated tenants
            foreach ($notifiedTenants as $tenant) {
                $userId = $tenant['user_id'];

                // Get tenant email and username
                $stmtUser = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
                $stmtUser->execute([$userId]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Insert in-app notification
                    $notificationMessage = "Your utility bill photos for " . date('F Y', strtotime($billingMonth)) . " have been uploaded. Please check your dashboard to verify and process your payment.";
                    $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                    $stmtNotif->execute([$userId, $notificationMessage]);

                    // Send email notification
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'mallxentro5@gmail.com';
                        $mail->Password = 'iwld cjlr kmcy bxab';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;

                        // Fix SSL certificate verification issue in XAMPP
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );

                        $mail->setFrom('mallxentro5@gmail.com', 'XentroMall');
                        $mail->addAddress($user['email']);

                        $mail->isHTML(true);
                        $mail->Subject = '📄 Utility Bill Photos Uploaded - ' . date('F Y', strtotime($billingMonth)) . ' | XentroMall';
                        $mail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        </head>
                        <body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8fafc; padding: 40px 20px;'>
                                <tr>
                                    <td align='center'>
                                        <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 24px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); overflow: hidden;'>
                                            <!-- Header -->
                                            <tr>
                                                <td style='background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%); padding: 40px 30px; text-align: center;'>
                                                    <div style='display: inline-block; padding: 12px; background: rgba(255, 255, 255, 0.2); border-radius: 16px; margin-bottom: 20px;'>
                                                        <span style='font-size: 32px;'>📄</span>
                                                    </div>
                                                    <h1 style='color: #ffffff; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;'>
                                                        Utility Bills Available
                                                    </h1>
                                                    <p style='color: rgba(255, 255, 255, 0.9); margin: 10px 0 0 0; font-size: 16px; font-weight: 500;'>
                                                        " . date('F Y', strtotime($billingMonth)) . "
                                                    </p>
                                                </td>
                                            </tr>
                                            
                                            <!-- Content -->
                                            <tr>
                                                <td style='padding: 40px 30px;'>
                                                    <p style='color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;'>
                                                        Hello <strong>{$user['username']}</strong>,
                                                    </p>
                                                    
                                                    <p style='color: #475569; font-size: 15px; line-height: 1.6; margin: 0 0 30px 0;'>
                                                        The admin has uploaded your latest utility bill photos for <strong>" . date('F Y', strtotime($billingMonth)) . "</strong>. You can now view them directly in your dashboard to verify your charges.
                                                    </p>
                                                    
                                                    <div style='background: #f1f5f9; border-radius: 16px; padding: 25px; margin-bottom: 30px; border: 1px solid #e2e8f0;'>
                                                        <table width='100%' cellpadding='0' cellspacing='0'>
                                                            <tr>
                                                                <td style='vertical-align: middle;'>
                                                                    <p style='margin: 0; color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;'>Uploaded Documents</p>
                                                                    <p style='margin: 5px 0 0 0; color: #0f172a; font-size: 18px; font-weight: 700;'>Water & Electricity Bill Photos</p>
                                                                </td>
                                                                <td style='text-align: right; vertical-align: middle;'>
                                                                    <span style='background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;'>READY</span>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                    
                                                    <table width='100%' cellpadding='0' cellspacing='0'>
                                                        <tr>
                                                            <td align='center'>
                                                                <a href='" . BASE_URL . "tenant_payment.php' style='display: inline-block; background: #0ea5e9; color: #ffffff; text-decoration: none; padding: 18px 40px; border-radius: 16px; font-weight: 700; font-size: 16px; box-shadow: 0 10px 15px -3px rgba(14, 165, 233, 0.3); transition: all 0.3s ease;'>
                                                                    View Bills in Dashboard
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <!-- Footer -->
                                            <tr>
                                                <td style='background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #f1f5f9;'>
                                                    <p style='margin: 0; color: #94a3b8; font-size: 12px;'>
                                                        XentroMall Management System<br>
                                                        This is an automated notification. Please do not reply.
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </body>
                        </html>
                        ";

                        $mail->send();

                        // Send SMS notification for billing update
                        try {
                            // Get tenant mobile number
                            $stmtMobile = $pdo->prepare("SELECT mobile FROM tenant_details WHERE user_id = ?");
                            $stmtMobile->execute([$userId]);
                            $tenantMobile = $stmtMobile->fetchColumn();

                            if ($tenantMobile) {
                                $sms = new IPROG_SMS();
                                $billingMonthFormatted = date('F Y', strtotime($billingMonth));
                                // Call without amount as it's 0 now
                                $smsResult = $sms->sendBillingUpdateSMS($user['username'], $tenantMobile, $billingMonthFormatted);

                                if ($smsResult['success']) {
                                    error_log("✅ Billing update SMS sent to {$tenantMobile} for user {$userId}");
                                } else {
                                    error_log("❌ Failed to send billing update SMS: " . $smsResult['response']);
                                }
                            } else {
                                error_log("⚠️ No mobile number found for user {$userId} - SMS not sent");
                            }
                        } catch (Exception $e) {
                            error_log("SMS sending error for billing update: " . $e->getMessage());
                        }
                    } catch (Exception $e) {
                        error_log("Failed to send email to user {$userId}: " . $mail->ErrorInfo);
                    }
                }
            }

            $_SESSION['billing_success_data'] = [
                'count' => $updatedCount,
                'month' => date('F Y', strtotime($billingMonth))
            ];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['billing_error'] = 'Database error: ' . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } else {
        $_SESSION['billing_error'] = 'Invalid billing month. Use YYYY-MM.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Check for billing session messages
$billingSuccessScript = '';
$billingErrorScript = '';

if (isset($_SESSION['billing_success_data'])) {
    $data = $_SESSION['billing_success_data'];
    $billingSuccessScript = "<script>document.addEventListener('DOMContentLoaded', function() { 
        if (typeof showBillingSuccessModal === 'function') {
            showBillingSuccessModal({$data['count']}, '{$data['month']}'); 
        }
    });</script>";
    unset($_SESSION['billing_success_data']);
}

if (isset($_SESSION['billing_error'])) {
    $error = $_SESSION['billing_error'];
    $billingErrorScript = "<script>document.addEventListener('DOMContentLoaded', function() { 
        if (typeof showBillingErrorModal === 'function') {
            showBillingErrorModal('" . addslashes($error) . "'); 
        }
    });</script>";
    unset($_SESSION['billing_error']);
}

// Fetch tenants with latest bills (if table exists)
$billingTenants = [];
try {
    // Fetch all tenants with their stalls and bills
    $sql = "
      SELECT 
        u.id as user_id, 
        u.username,
        td.id as tenant_detail_id,
        s.id as stall_id,
        s.stall_number,
        s.description as stall_location,
        te.water_bill,
        te.electric_bill
      FROM users u
      INNER JOIN tenant_details td ON u.id = td.user_id
      INNER JOIN stalls s ON td.stall_id = s.id
      LEFT JOIN tenant_expenses te ON te.user_id = u.id AND te.stall_id = s.id
      WHERE u.role = 'tenant' AND td.status = 'approved'
      ORDER BY u.username ASC, s.stall_number ASC
    ";
    $stmt = $pdo->query($sql);
    $billingTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // fallback if tenant_expenses not present yet
    $stmt = $pdo->prepare("
        SELECT 
          u.id as user_id, 
          u.username,
          td.id as tenant_detail_id,
          s.id as stall_id,
          s.stall_number,
          s.description as stall_location,
          NULL as water_bill,
          NULL as electric_bill
        FROM users u
        INNER JOIN tenant_details td ON u.id = td.user_id
        INNER JOIN stalls s ON td.stall_id = s.id
        WHERE u.role = 'tenant' AND td.status = 'approved'
        ORDER BY u.username ASC, s.stall_number ASC
    ");
    $stmt->execute();
    $billingTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Admin Dashboard - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --secondary: #0ea5e9;
            --accent: #f59e0b;
            --surface: #ffffff;
            --background: #f8fafc;
        }

        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .gradient-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .gradient-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .nav-item {
            position: relative;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            letter-spacing: 0.3px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .nav-item:hover {
            transform: translateX(10px);
            background: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .nav-item:hover::before {
            left: 100%;
        }

        .nav-item span {
            font-size: 15px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .nav-item i {
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .nav-item:hover i {
            transform: scale(1.2) rotate(10deg);
            color: #fff;
        }

        /* Staggered entry animation for sidebar items */
        @keyframes navItemSlideIn {
            from {
                opacity: 0;
                transform: translateX(-25px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-item {
            animation: navItemSlideIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) backwards;
        }

        .nav-item:nth-child(1) {
            animation-delay: 0.1s;
        }

        .nav-item:nth-child(2) {
            animation-delay: 0.15s;
        }

        .nav-item:nth-child(3) {
            animation-delay: 0.2s;
        }

        .nav-item:nth-child(4) {
            animation-delay: 0.25s;
        }

        .nav-item:nth-child(5) {
            animation-delay: 0.3s;
        }

        .nav-item:nth-child(6) {
            animation-delay: 0.35s;
        }

        .nav-item:nth-child(7) {
            animation-delay: 0.4s;
        }

        .nav-item:nth-child(8) {
            animation-delay: 0.45s;
        }

        .nav-item:nth-child(9) {
            animation-delay: 0.5s;
        }

        .nav-item:nth-child(10) {
            animation-delay: 0.55s;
        }

        .nav-item:nth-child(11) {
            animation-delay: 0.6s;
        }

        .nav-item:nth-child(12) {
            animation-delay: 0.65s;
        }

        aside {
            font-family: 'Poppins', sans-serif;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        aside:hover {
            box-shadow: 0 25px 50px -12px rgba(5, 150, 105, 0.25);
        }

        .scrollbar-custom::-webkit-scrollbar {
            width: 6px;
        }

        .scrollbar-custom::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }

        .scrollbar-custom::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 3px;
        }

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

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .floating-action {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 50;
        }

        /* Unread notification row highlight (Additional Stall applications) */
        .unread-row {
            border-left: 8px solid #10b981 !important;
            background: linear-gradient(90deg, #f0fdf4 0%, #ffffff 100%) !important;
            animation: unreadRowPulse 2s ease-in-out infinite alternate !important;
            position: relative;
            box-shadow: inset 0 0 10px rgba(16, 185, 129, 0.05) !important;
        }

        .unread-row:hover {
            background: linear-gradient(90deg, #dcfce7 0%, #f9fafb 100%) !important;
        }

        @keyframes unreadRowPulse {
            0% {
                background: linear-gradient(90deg, #f0fdf4 0%, #ffffff 100%);
            }

            100% {
                background: linear-gradient(90deg, #dcfce7 0%, #ffffff 100%);
            }
        }

        .unread-notif-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #065f46;
            border: 1px solid #b9f6ca;
            padding: 3px 12px 3px 10px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 900;
            letter-spacing: 0.05em;
            vertical-align: middle;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.15);
            text-transform: uppercase;
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
            animation: unreadDotPulse 0.8s ease-in-out infinite;
            box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        }

        @keyframes unreadDotPulse {
            0% {
                transform: scale(0.9);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }

            70% {
                transform: scale(1.1);
                box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
            }

            100% {
                transform: scale(0.9);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }

        .marquee-container {
            overflow: hidden;
            white-space: nowrap;
            width: 100%;
        }

        .marquee-content {
            display: inline-block;
            animation: marquee-fade 15s linear infinite;
            padding-left: 20px;
        }

        .marquee-container:hover .marquee-content {
            animation-play-state: paused;
        }

        @keyframes marquee-fade {
            0% {
                transform: translateX(100%);
            }

            100% {
                transform: translateX(-100%);
            }
        }

        /* Sidebar Dropdown Styles */
        .nav-dropdown {
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin: 0 4px;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nav-dropdown.active {
            max-height: 500px;
            padding: 8px 0;
            margin-bottom: 8px;
            background: rgba(0, 0, 0, 0.1);
        }

        .dropdown-trigger .fa-chevron-right {
            transition: transform 0.3s ease;
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .dropdown-trigger.active .fa-chevron-right {
            transform: rotate(90deg);
        }

        .nav-item-sub {
            padding-left: 3.5rem !important;
            font-size: 0.9rem !important;
            opacity: 0.8;
            height: 40px;
            display: flex;
            align-items: center;
        }

        .category-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255, 255, 255, 0.4);
            margin: 15px 0 5px 12px;
            font-weight: 700;
            display: block;
        }

        /* ====== MOBILE RESPONSIVE ====== */
        #mobileMenuBtn {
            display: none;
        }

        #sidebarOverlay {
            display: none;
        }

        @media (max-width: 1024px) {

            /* Header adjustments */
            header .max-w-\[1400px\] {
                padding-left: 12px;
                padding-right: 12px;
            }

            /* Show hamburger */
            #mobileMenuBtn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 44px;
                height: 44px;
                border-radius: 14px;
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(8px);
                color: white;
                font-size: 1.25rem;
                border: 1px solid rgba(255, 255, 255, 0.2);
                cursor: pointer;
                transition: all 0.3s ease;
                flex-shrink: 0;
            }

            #mobileMenuBtn:hover {
                background: rgba(255, 255, 255, 0.25);
            }

            #mobileMenuBtn:active {
                transform: scale(0.92);
            }

            /* Sidebar overlay */
            #sidebarOverlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 90;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            #sidebarOverlay.active {
                display: block;
                opacity: 1;
            }

            /* Sidebar off-canvas */
            aside#adminSidebar {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                bottom: 0 !important;
                z-index: 100 !important;
                width: 280px !important;
                max-width: 85vw !important;
                border-radius: 0 24px 24px 0 !important;
                transform: translateX(-110%);
                transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
                overflow-y: auto;
                height: 100vh !important;
                max-height: 100vh !important;
                padding-top: 20px !important;
            }

            aside#adminSidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 8px 0 30px rgba(0, 0, 0, 0.3);
            }

            /* Flex layout stacking */
            .admin-layout-wrapper {
                flex-direction: column !important;
            }

            /* Main content full width */
            #mainContent {
                max-height: none !important;
                width: 100% !important;
            }

            /* Header admin info - hide text on small */
            #adminInfoDesktop {
                display: none !important;
            }
        }

        @media (max-width: 768px) {

            /* Stats grid */
            .grid.grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-4 {
                grid-template-columns: 1fr 1fr !important;
                gap: 12px !important;
            }

            /* Chart grids */
            .grid.grid-cols-1.lg\:grid-cols-2 {
                grid-template-columns: 1fr !important;
            }

            /* Section title */
            #sectionTitle {
                font-size: 1.5rem !important;
            }

            /* Cards padding */
            .gradient-card {
                padding: 16px !important;
            }

            .gradient-card .text-3xl {
                font-size: 1.5rem !important;
            }

            .gradient-card .w-14 {
                width: 40px !important;
                height: 40px !important;
            }

            /* Tables horizontal scroll */
            .table-responsive-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .table-modern {
                min-width: 700px;
            }

            /* Header date hide */
            #headerDateBox {
                display: none !important;
            }

            /* Floating action reposition */
            .floating-action {
                bottom: 1rem;
                right: 1rem;
            }

            /* ====== GLOBAL MODAL RESPONSIVE ====== */
            /* Target any container inside a fixed inset-0 (standard modal pattern in this file) */
            .fixed.inset-0>div,
            div[id*="Modal"]>div,
            div[id*="modal"]>div {
                width: 94% !important;
                max-width: 450px !important;
                /* Cap width for better look on small screens */
                max-height: 92vh !important;
                overflow-y: auto !important;
                padding: 0 !important;
                /* Let internal sections handle padding */
                margin: auto !important;
                border-radius: 24px !important;
            }

            /* On very small screens, allow full width */
            @media (max-width: 480px) {

                .fixed.inset-0>div,
                div[id*="Modal"]>div {
                    width: 96% !important;
                }
            }

            /* Override excessive paddings */
            .fixed.inset-0 .px-8,
            div[id*="Modal"] .px-8 {
                padding-left: 1.25rem !important;
                padding-right: 1.25rem !important;
            }

            .fixed.inset-0 .py-6,
            div[id*="Modal"] .py-6 {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }

            /* Stack grid layouts (usually side-by-side forms) */
            .fixed.inset-0 .grid-cols-2,
            .fixed.inset-0 .grid-cols-3,
            div[id*="Modal"] .grid-cols-2,
            div[id*="Modal"] .grid-cols-3 {
                grid-template-columns: 1fr !important;
                gap: 0.75rem !important;
            }

            /* Stack action buttons in footer */
            .fixed.inset-0 .flex.gap-4,
            div[id*="Modal"] .flex.gap-4 {
                flex-direction: column !important;
                gap: 0.75rem !important;
            }

            .fixed.inset-0 .flex.gap-4 button,
            div[id*="Modal"] .flex.gap-4 button {
                width: 100% !important;
                padding-top: 0.875rem !important;
                padding-bottom: 0.875rem !important;
            }

            /* Adjust modal text sizes */
            .fixed.inset-0 h2,
            div[id*="Modal"] h2 {
                font-size: 1.25rem !important;
            }
        }

        @media (max-width: 480px) {

            /* Stats single column */
            .grid.grid-cols-1.sm\:grid-cols-2.lg\:grid-cols-4 {
                grid-template-columns: 1fr !important;
            }

            /* Even smaller padding */
            .admin-layout-wrapper {
                padding: 12px !important;
            }

            header .max-w-\[1400px\] {
                padding-left: 8px;
                padding-right: 8px;
            }

            /* Brand text smaller */
            header h1 {
                font-size: 1rem !important;
            }

            header h1+p {
                display: none;
            }
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Top Header -->
    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

    <header class="gradient-primary shadow-2xl sticky top-0 z-50">
        <div class="max-w-[1400px] mx-auto px-4 lg:px-6 py-2">
            <div class="flex items-center justify-between">
                <!-- Hamburger + Logo -->
                <div class="flex items-center gap-3">
                    <button id="mobileMenuBtn" onclick="toggleMobileSidebar()" aria-label="Open menu">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="bg-white/20 p-1 rounded-2xl backdrop-blur-sm">
                        <img src="img/logo.jpg" alt="XentroMall Logo" class="w-10 h-10 object-contain rounded-lg" />
                    </div>
                    <div>
                        <h1 class="font-bold text-xl text-white select-none">XentroMall</h1>
                        <p class="text-white/80 text-xs">Admin Management System</p>
                    </div>
                </div>

                <!-- Admin Info and Menu -->
                <div class="flex items-center gap-4">
                    <div id="adminInfoDesktop"
                        class="bg-white rounded-2xl px-4 py-2 flex items-center gap-3 shadow-lg border-2 border-emerald-100">
                        <div
                            class="w-9 h-9 rounded-full bg-gradient-to-br from-emerald-500 to-blue-500 flex items-center justify-center shadow-md">
                            <i class="fas fa-user-shield text-white text-base"></i>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[10px] text-gray-500 font-medium uppercase tracking-wide">Logged in as
                                Admin</span>
                            <p class="font-bold text-gray-900 text-sm leading-tight">
                                <?php echo htmlspecialchars($admin_username); ?>
                            </p>
                        </div>
                    </div>

                    <div class="relative">
                        <button aria-label="Toggle menu"
                            class="glass-effect text-white hover:bg-white/20 transition-colors p-3 rounded-xl"
                            id="adminSettingsToggle">
                            <i class="fas fa-ellipsis-v text-lg"></i>
                        </button>
                        <div id="adminSettingsMenu"
                            class="hidden absolute right-0 top-14 bg-white rounded-2xl shadow-2xl w-56 z-50 overflow-hidden border border-gray-100">
                            <a href="admin_settings.php"
                                class="block px-4 py-3 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition-colors border-b border-gray-50">
                                <i class="fas fa-cog mr-3"></i>Settings & Profile
                            </a>
                            <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 transition-colors">
                                <i class="fas fa-sign-out-alt mr-3"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex gap-6 max-w-[1400px] mx-auto px-4 lg:px-6 py-6 admin-layout-wrapper">
        <!-- Sidebar -->
        <aside id="adminSidebar"
            class="gradient-primary rounded-2xl w-72 p-4 flex flex-col gap-3 shadow-2xl text-white relative overflow-hidden">
            <!-- Background decoration -->
            <div class="absolute -top-20 -right-20 w-40 h-40 bg-white/10 rounded-full"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-white/5 rounded-full"></div>

            <!-- Mobile close button -->
            <button onclick="closeMobileSidebar()"
                class="lg:hidden absolute top-4 right-4 z-20 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-all border border-white/20">
                <i class="fas fa-times text-lg"></i>
            </button>

            <!-- Navigation -->
            <nav class="flex flex-col gap-2 relative z-10">
                <a class="nav-item flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                    id="viewDashboardLink">
                    <i class="fas fa-chart-pie text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Dashboard</span>
                </a>

                <!-- Management Group -->
                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                        onclick="toggleDropdown(this)">
                        <i class="fas fa-tasks text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">Management</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown">
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewTenantsLink">
                            <i class="fas fa-users text-lg"></i>
                            <span class="font-semibold">Tenants</span>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewApplicationsLink">
                            <i class="fas fa-file-alt text-lg"></i>
                            <span class="font-semibold">Applications</span>
                            <?php if ($pendingApplications > 0): ?>
                                <span
                                    class="ml-auto bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full animate-pulse"><?php echo $pendingApplications; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group"
                            href="stall_page.php">
                            <i class="fas fa-store text-lg"></i>
                            <span class="font-semibold">Stall Management</span>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group"
                            href="admin_work_permits.php">
                            <i class="fas fa-hard-hat text-lg"></i>
                            <span class="font-semibold">Work Permits</span>
                            <?php if ($pendingWorkPermits > 0): ?>
                                <span
                                    class="ml-auto bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full animate-pulse"><?php echo $pendingWorkPermits; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>

                <!-- Operations Group -->
                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                        onclick="toggleDropdown(this)">
                        <i class="fas fa-cogs text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">Operations</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown">
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewRenewalLink">
                            <i class="fas fa-sync-alt text-lg"></i>
                            <span class="font-semibold">Renewal</span>
                            <?php if ($pendingRenewals > 0): ?>
                                <span
                                    class="ml-auto bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full animate-pulse"><?php echo $pendingRenewals; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewPaymentsLink">
                            <i class="fas fa-credit-card text-lg"></i>
                            <span class="font-semibold">Payments</span>
                            <?php if ($pendingPayments > 0): ?>
                                <span
                                    class="ml-auto bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full animate-pulse"><?php echo $pendingPayments; ?></span>
                            <?php endif; ?>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewBillingLink">
                            <i class="fas fa-file-invoice-dollar text-lg"></i>
                            <span class="font-semibold">Billing</span>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewSOALink">
                            <i class="fas fa-file-invoice text-lg"></i>
                            <span class="font-semibold">Statement of Account</span>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewContractsLink">
                            <i class="fas fa-file-contract text-lg"></i>
                            <span class="font-semibold">Active Contracts</span>
                        </a>
                    </div>
                </div>

                <!-- Analysis Group -->
                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                        onclick="toggleDropdown(this)">
                        <i class="fas fa-chart-line text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">Analysis</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown">
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewHistoryLink">
                            <i class="fas fa-chart-bar text-lg"></i>
                            <span class="font-semibold">Revenue Reports</span>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                            id="viewApplicantHistoryLink">
                            <i class="fas fa-history text-lg"></i>
                            <span class="font-semibold">Applicant History</span>
                        </a>
                    </div>
                </div>

                <div class="h-px bg-white/30 my-2 mx-4"></div>

                <!-- System Group -->
                <div class="nav-group mb-1">
                    <a class="nav-item dropdown-trigger flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group"
                        onclick="toggleDropdown(this)">
                        <i class="fas fa-shield-alt text-xl group-hover:scale-110 transition-transform"></i>
                        <span class="font-semibold">System</span>
                        <i class="fas fa-chevron-right ml-auto transition-transform"></i>
                    </a>
                    <div class="nav-dropdown">
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group"
                            href="posting.php">
                            <i class="fas fa-bullhorn text-lg"></i>
                            <span class="font-semibold">Announcements</span>
                        </a>
                        <a class="nav-item nav-item-sub flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-white/15 transition-all group"
                            href="admin_register.php">
                            <i class="fas fa-user-plus text-lg"></i>
                            <span class="font-semibold">Register Admin</span>
                        </a>
                    </div>
                </div>

                <div class="mt-2"></div>

                <a class="nav-item flex items-center gap-2 px-2 py-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-50 hover:text-white transition-all group"
                    href="logout.php">
                    <i class="fas fa-sign-out-alt text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Logout</span>
                </a>
            </nav>
        </aside>
        <!-- Main content -->
        <main id="mainContent" class="flex-1 flex flex-col gap-6 overflow-auto scrollbar-custom"
            style="max-height: 90vh;">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div
                    class="bg-gradient-to-r from-emerald-50 to-green-50 border-l-4 border-emerald-500 text-emerald-700 px-6 py-4 rounded-2xl shadow-md animate-fade-in flex items-center gap-3 mb-6">
                    <i class="fas fa-check-circle text-2xl flex-shrink-0"></i>
                    <span class="font-medium text-lg"><?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['warning'])): ?>
                <div
                    class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-500 text-amber-700 px-6 py-4 rounded-2xl shadow-md animate-fade-in flex items-center gap-3 mb-6">
                    <i class="fas fa-exclamation-triangle text-2xl flex-shrink-0"></i>
                    <span class="font-medium text-lg"><?php echo $_SESSION['warning'];
                    unset($_SESSION['warning']); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div
                    class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-2xl shadow-md animate-fade-in flex items-center gap-3 mb-6">
                    <i class="fas fa-exclamation-circle text-2xl flex-shrink-0"></i>
                    <span class="font-medium text-lg"><?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <header class="animate-fade-in">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-2 gap-3">
                    <div>
                        <h2 class="font-bold text-2xl sm:text-3xl text-gray-900" id="sectionTitle">Dashboard</h2>
                        <p class="text-gray-600 mt-1 text-sm sm:text-base">Welcome back! Here's what's happening with
                            your mall today.</p>
                    </div>
                    <div id="headerDateBox" class="flex items-center gap-3">
                        <div class="bg-white rounded-2xl px-4 py-2 shadow-sm border border-gray-100">
                            <span class="text-sm text-gray-600">Today: </span>
                            <span class="font-semibold text-gray-900" id="currentDate"></span>
                        </div>
                    </div>
                </div>
            </header>

            <section id="dashboardSection" class="space-y-6 animate-fade-in">
                <!-- New Application Submitted Alert -->
                <?php if ($newApplicationsCount > 0): ?>
                    <div id="newAppAlert" class="relative overflow-hidden rounded-2xl shadow-lg mb-6 animate-fade-in"
                        style="background: linear-gradient(135deg, #059669 0%, #0ea5e9 50%, #6366f1 100%);">
                        <!-- Decorative elements -->
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full"></div>
                        <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white/5 rounded-full"></div>
                        <div class="absolute top-1/2 right-1/4 w-20 h-20 bg-white/5 rounded-full"></div>

                        <div class="relative z-10 p-5 sm:p-6">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                                <div class="flex items-start gap-4">
                                    <div
                                        class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center flex-shrink-0 shadow-lg border border-white/20">
                                        <i class="fas fa-file-circle-check text-white text-2xl"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <h4 class="text-white font-bold text-lg">New
                                                Application<?php echo $newApplicationsCount > 1 ? 's' : ''; ?> Submitted!
                                            </h4>
                                            <span
                                                class="bg-white/25 backdrop-blur-sm text-white text-xs font-black px-3 py-1 rounded-full border border-white/30 animate-pulse shadow-lg">
                                                <?php echo $newApplicationsCount; ?> NEW
                                            </span>
                                        </div>
                                        <p class="text-white/90 text-sm">
                                            <?php if ($newApplicationsCount == 1): ?>
                                                <strong><?php echo htmlspecialchars($newApplicationsList[0]['tradename'] ?? $newApplicationsList[0]['company_name'] ?? 'New Applicant'); ?></strong>
                                                has submitted a new
                                                application<?php echo !empty($newApplicationsList[0]['stall_number']) ? ' for Stall #' . htmlspecialchars($newApplicationsList[0]['stall_number']) : ''; ?>.
                                            <?php else: ?>
                                                <strong><?php echo htmlspecialchars($newApplicationsList[0]['tradename'] ?? $newApplicationsList[0]['company_name'] ?? 'New Applicant'); ?></strong>
                                                and <strong><?php echo ($newApplicationsCount - 1); ?>
                                                    other<?php echo ($newApplicationsCount - 1) > 1 ? 's' : ''; ?></strong>
                                                have submitted new applications.
                                            <?php endif; ?>
                                            &mdash; <span class="text-white/70">Submitted
                                                <?php echo date('M d, Y g:i A', strtotime($newApplicationsList[0]['created_at'])); ?></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 w-full sm:w-auto flex-shrink-0">
                                    <button onclick="document.getElementById('viewApplicationsLink').click();"
                                        class="flex-1 sm:flex-none bg-white hover:bg-gray-50 text-emerald-700 px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-lg flex items-center justify-center gap-2 border border-white/50">
                                        <i class="fas fa-eye"></i> Review Now
                                    </button>
                                    <button onclick="dismissNewAppAlert()"
                                        class="w-10 h-10 flex items-center justify-center bg-white/15 hover:bg-white/25 text-white rounded-xl transition-all border border-white/20"
                                        title="Dismiss">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if ($newApplicationsCount > 1): ?>
                                <!-- Mini preview of applicants -->
                                <div class="mt-4 pt-4 border-t border-white/20">
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($newApplicationsList as $newApp): ?>
                                            <div
                                                class="bg-white/15 backdrop-blur-sm rounded-lg px-3 py-1.5 text-white text-xs font-medium border border-white/10 flex items-center gap-2">
                                                <i class="fas fa-user-circle"></i>
                                                <?php echo htmlspecialchars($newApp['tradename'] ?? $newApp['company_name'] ?? 'Applicant'); ?>
                                                <?php if (!empty($newApp['stall_number'])): ?>
                                                    <span class="bg-white/20 px-1.5 py-0.5 rounded text-[10px] font-black">Stall
                                                        #<?php echo htmlspecialchars($newApp['stall_number']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <script>
                        function dismissNewAppAlert() {
                            const alert = document.getElementById('newAppAlert');
                            alert.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                            alert.style.opacity = '0';
                            alert.style.transform = 'translateY(-20px)';
                            alert.style.maxHeight = alert.offsetHeight + 'px';
                            setTimeout(() => {
                                alert.style.maxHeight = '0';
                                alert.style.padding = '0';
                                alert.style.margin = '0';
                                alert.style.overflow = 'hidden';
                            }, 200);
                            setTimeout(() => alert.remove(), 700);

                            // Mark as viewed via AJAX
                            fetch('mark_applications_viewed.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'mark_viewed' })
                            }).catch(() => { });
                        }
                    </script>
                <?php endif; ?>

                <!-- BIR Expiration Alerts -->
                <?php
                $birUrgentCount = $expiredBIRCount + $expiringSoonBIRCount;
                if ($birUrgentCount > 0):
                    ?>
                    <div class="grid grid-cols-1 gap-4 mb-6">
                        <div
                            class="bg-amber-50 border-l-4 border-amber-500 p-4 sm:p-5 rounded-2xl shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between animate-fade-in ring-1 ring-amber-100 marquee-container gap-4">
                            <div class="flex items-center gap-3 marquee-content w-full">
                                <div class="flex items-center gap-3 shrink-0">
                                    <div
                                        class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center text-amber-600 shadow-inner">
                                        <i class="fas fa-clock text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-amber-800 font-bold text-lg leading-tight">BIR Expiration
                                            Approaching</h4>
                                        <p class="text-amber-700 text-sm"><?php echo $birUrgentCount; ?> tenant(s) have BIR
                                            registrations expiring within 30 days.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 relative z-10 shrink-0 w-full md:w-auto">
                                <button onclick="viewTenantsLink.click()"
                                    class="flex-1 md:flex-none justify-center bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-xl transition-all font-semibold shadow-md flex items-center gap-2 text-sm">
                                    <i class="fas fa-eye"></i> View Tenants
                                </button>
                                <button onclick="notifyAllExpiringBIR(event)"
                                    class="flex-1 md:flex-none justify-center bg-white hover:bg-gray-100 text-amber-700 border border-amber-200 px-4 py-2.5 rounded-xl transition-all font-bold shadow-sm flex items-center gap-2 text-sm">
                                    <i class="fas fa-paper-plane"></i> Notify All
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div id="totalTenantsBox"
                        class="cursor-pointer hover-lift gradient-card rounded-3xl p-6 shadow-lg border border-gray-100 relative overflow-hidden group">
                        <div
                            class="absolute -top-4 -right-4 w-24 h-24 bg-emerald-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500">
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <div
                                    class="w-14 h-14 bg-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-users text-white text-xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($totalTenants); ?>
                                    </div>
                                    <div class="text-emerald-600 text-sm font-medium">+12% from last month</div>
                                </div>
                            </div>
                            <h3 class="text-gray-700 font-semibold">Total Tenants</h3>
                            <p class="text-gray-500 text-sm mt-1">Active tenant accounts</p>
                        </div>
                    </div>

                    <div
                        class="hover-lift gradient-card rounded-3xl p-6 shadow-lg border border-gray-100 relative overflow-hidden group">
                        <div
                            class="absolute -top-4 -right-4 w-24 h-24 bg-blue-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500">
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <div
                                    class="w-14 h-14 bg-blue-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-check-circle text-white text-xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($paidTenants); ?>
                                    </div>
                                    <div class="text-blue-600 text-sm font-medium">+8% from last month</div>
                                </div>
                            </div>
                            <h3 class="text-gray-700 font-semibold">Paid Tenants</h3>
                            <p class="text-gray-500 text-sm mt-1">Up-to-date payments</p>
                        </div>
                    </div>

                    <div
                        class="hover-lift gradient-card rounded-3xl p-6 shadow-lg border border-gray-100 relative overflow-hidden group">
                        <div
                            class="absolute -top-4 -right-4 w-24 h-24 bg-red-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500">
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <div
                                    class="w-14 h-14 bg-red-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($unpaidTenants); ?>
                                    </div>
                                    <div class="text-red-600 text-sm font-medium">-5% from last month</div>
                                </div>
                            </div>
                            <h3 class="text-gray-700 font-semibold">Unpaid Tenants</h3>
                            <p class="text-gray-500 text-sm mt-1">Pending payments</p>
                        </div>
                    </div>

                    <div
                        class="hover-lift gradient-card rounded-3xl p-6 shadow-lg border border-gray-100 relative overflow-hidden group">
                        <div
                            class="absolute -top-4 -right-4 w-24 h-24 bg-amber-100 rounded-full opacity-50 group-hover:scale-110 transition-transform duration-500">
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-4">
                                <div
                                    class="w-14 h-14 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                                    <i class="fas fa-chart-line text-white text-xl"></i>
                                </div>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-gray-900 mb-1">
                                        <?php echo htmlspecialchars($overallTenantsPaid); ?>
                                    </div>
                                    <div class="text-amber-600 text-sm font-medium">+15% from last month</div>
                                </div>
                            </div>
                            <h3 class="text-gray-700 font-semibold">Total Payments</h3>
                            <p class="text-gray-500 text-sm mt-1">Overall transactions</p>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="gradient-card rounded-3xl p-6 shadow-lg border border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Tenant Analytics Status</h3>
                                <p class="text-gray-500 text-sm">Professional status analytics breakdown</p>
                            </div>
                            <div class="w-5 h-5 bg-emerald-500 rounded-full animate-pulse -mt-1"></div>
                        </div>
                        <div class="h-64 flex items-center justify-center">
                            <div id="tenantsDoughnut"></div>
                        </div>
                    </div>

                    <div class="gradient-card rounded-3xl p-6 shadow-lg border border-gray-100">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Payment Trends</h3>
                                <p class="text-gray-500 text-sm" id="paymentTrendsSubtitle">Daily payment collections
                                </p>
                            </div>
                            <div class="flex items-center gap-4 -mt-1">
                                <input type="month" id="paymentTrendsMonthSelector" value="<?php echo date('Y-m'); ?>"
                                    class="text-sm px-2 py-1 border border-gray-200 rounded-lg text-gray-600 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                    onchange="fetchPaymentTrends()">
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 bg-emerald-500 rounded-full"></div>
                                    <span class="text-sm text-gray-600">Approved</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 bg-amber-500 rounded-full"></div>
                                    <span class="text-sm text-gray-600">Pending</span>
                                </div>
                            </div>
                        </div>
                        <div class="h-64 relative">
                            <div id="paymentsBarLoading"
                                class="absolute inset-0 bg-white/50 backdrop-blur-sm z-10 flex items-center justify-center hidden rounded-lg">
                                <i class="fas fa-spinner fa-spin text-3xl text-emerald-500"></i>
                            </div>
                            <div id="paymentsBar"></div>
                        </div>
                    </div>

                    <!-- Lease Timeline Gantt Chart -->
                    <div class="md:col-span-2 gradient-card rounded-3xl p-6 shadow-lg border border-gray-100 mt-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 capitalize">Lease Schedule Roadmap</h3>
                                <p class="text-gray-500 text-sm">Tenant contract durations & expiration timeline</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-emerald-500 rounded-full"></div>
                                <span class="text-xs font-medium text-gray-600">Active Lease</span>
                            </div>
                        </div>
                        <div id="leaseGantt" class="min-h-[350px]"></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="gradient-card rounded-3xl p-6 shadow-lg border border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
                        <button
                            class="flex flex-col items-center gap-2 sm:gap-3 p-3 sm:p-4 rounded-2xl bg-emerald-50 hover:bg-emerald-100 transition-colors group text-center"
                            id="quickApplications">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-emerald-500 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                                <i class="fas fa-file-alt text-white text-sm sm:text-base"></i>
                            </div>
                            <span class="text-[11px] sm:text-sm font-medium text-gray-700 leading-tight">View
                                Applications</span>
                        </button>
                        <button
                            class="flex flex-col items-center gap-2 sm:gap-3 p-3 sm:p-4 rounded-2xl bg-blue-50 hover:bg-blue-100 transition-colors group text-center"
                            id="quickPayments">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-500 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                                <i class="fas fa-credit-card text-white text-sm sm:text-base"></i>
                            </div>
                            <span class="text-[11px] sm:text-sm font-medium text-gray-700 leading-tight">Manage
                                Payments</span>
                        </button>
                        <button
                            class="flex flex-col items-center gap-2 sm:gap-3 p-3 sm:p-4 rounded-2xl bg-purple-50 hover:bg-purple-100 transition-colors group text-center"
                            id="quickMaintenance">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                                <i class="fas fa-tools text-white text-sm sm:text-base"></i>
                            </div>
                            <span
                                class="text-[11px] sm:text-sm font-medium text-gray-700 leading-tight">Maintenance</span>
                        </button>
                        <button
                            class="flex flex-col items-center gap-2 sm:gap-3 p-3 sm:p-4 rounded-2xl bg-amber-50 hover:bg-amber-100 transition-colors group text-center"
                            id="quickBilling">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-amber-500 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                                <i class="fas fa-file-invoice-dollar text-white text-sm sm:text-base"></i>
                            </div>
                            <span class="text-[11px] sm:text-sm font-medium text-gray-700 leading-tight">Billing</span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Tenants Section -->
            <section id="tenantsSection" class="hidden space-y-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-gray-600 mt-1">View and manage all approved tenants</p>
                    </div>
                    <div
                        class="bg-gradient-to-br from-emerald-100 to-blue-100 text-emerald-700 px-5 py-3 rounded-2xl font-semibold shadow-md border border-emerald-200">
                        <i class="fas fa-users mr-2"></i>
                        <?php echo count($allTenants); ?> Tenants
                    </div>
                </div>

                <?php if ($allTenants): ?>
                    <div class="grid grid-cols-1 gap-3">
                        <?php foreach ($allTenants as $index => $tenant): ?>
                            <?php
                            $birExpiry = $tenant['bir_expiry_date'];
                            $birStatus = 'not_set';
                            $birBadgeClass = 'bg-gray-100 text-gray-700';
                            $birStatusText = 'BIR Info Not Set';
                            $birIcon = 'fa-info-circle';

                            if ($birExpiry) {
                                $today = new DateTime();
                                $expiryDate = new DateTime($birExpiry);
                                $diff = $today->diff($expiryDate);
                                $daysRemaining = $diff->days * ($diff->invert ? -1 : 1);

                                if ($daysRemaining < 0) {
                                    $birStatus = 'expired';
                                    $birBadgeClass = 'bg-red-100 text-red-700 font-bold animate-pulse';
                                    $birStatusText = 'BIR Expired (' . abs($daysRemaining) . ' days ago)';
                                    $birIcon = 'fa-exclamation-triangle';
                                } elseif ($daysRemaining <= 30) {
                                    $birStatus = 'expiring_soon';
                                    $birBadgeClass = ($daysRemaining <= 7) ? 'bg-amber-100 text-amber-700 font-bold' : 'bg-amber-50 text-amber-600';
                                    $formattedDate = date('M d, Y', strtotime($birExpiry));
                                    $birStatusText = "BIR Expires: $formattedDate ($daysRemaining days)";
                                    $birIcon = 'fa-clock';
                                } else {
                                    $birStatus = 'valid';
                                    $birBadgeClass = 'bg-emerald-100 text-emerald-700';
                                    $birStatusText = 'BIR Valid';
                                    $birIcon = 'fa-check-circle';
                                }
                            }

                            // Logic to find the actual BIR document file
                            $birDocumentUrl = '';
                            if (!empty($tenant['latest_extended_bir'])) {
                                $birDocumentUrl = $tenant['latest_extended_bir'];
                            } elseif (!empty($tenant['initial_docs_path'])) {
                                $dir = $tenant['initial_docs_path'];
                                $fullDir = __DIR__ . '/' . $dir;
                                if (is_dir($fullDir)) {
                                    $files = @scandir($fullDir);
                                    if ($files) {
                                        foreach ($files as $file) {
                                            if ($file !== '.' && $file !== '..' && stripos($file, 'BIR') !== false) {
                                                $birDocumentUrl = $dir . $file;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            ?>
                            <div class="gradient-card rounded-2xl shadow-lg overflow-hidden border border-gray-100">
                                <!-- Collapsed View - Name Only -->
                                <div class="p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between cursor-pointer hover:bg-gray-50 transition-colors gap-4"
                                    onclick="toggleTenantDetails(<?php echo $index; ?>)">
                                    <div class="flex items-center gap-4 w-full sm:w-auto">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-lg shrink-0">
                                            <?php echo strtoupper(substr($tenant['tradename'], 0, 2)); ?>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="text-lg font-bold text-gray-900">
                                                <?php echo htmlspecialchars($tenant['tradename']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-500">
                                                <i class="fas fa-building mr-1"></i>
                                                <?php echo htmlspecialchars($tenant['company_name']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto sm:justify-end">
                                        <span
                                            class="px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-semibold <?php echo $birBadgeClass; ?> border shadow-sm">
                                            <i class="fas <?php echo $birIcon; ?> mr-1"></i><?php echo $birStatusText; ?>
                                        </span>
                                        <?php if ($tenant['stall_number']): ?>
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] sm:text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">
                                                <i
                                                    class="fas fa-store mr-1"></i><?php echo htmlspecialchars($tenant['stall_number']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <button
                                            class="flex-1 sm:flex-none justify-center px-4 py-2 bg-gradient-to-r from-emerald-500 to-blue-500 text-white rounded-xl hover:from-emerald-600 hover:to-blue-600 transition-all font-medium shadow-md text-sm">
                                            <i class="fas fa-chevron-down mr-2" id="chevron-<?php echo $index; ?>"></i>Details
                                        </button>
                                    </div>
                                </div>

                                <!-- Expanded View - Full Details (Hidden by default) -->
                                <div id="tenant-details-<?php echo $index; ?>"
                                    class="hidden border-t border-gray-200 bg-gray-50 p-6">
                                    <!-- Primary Information Section -->
                                    <div class="mb-6">
                                        <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                            <i class="fas fa-building text-blue-600"></i>
                                            Business Information
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <div
                                                class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm <?php echo ($birStatus === 'expired') ? 'border-red-300 ring-2 ring-red-50' : ''; ?>">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 <?php echo ($birStatus === 'expired') ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'; ?> rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">BIR
                                                            Registration Expiry</p>
                                                        <p
                                                            class="text-lg font-bold <?php echo ($birStatus === 'expired') ? 'text-red-600' : 'text-gray-900'; ?>">
                                                            <?php echo $birExpiry ? date('M d, Y', strtotime($birExpiry)) : 'Not Set'; ?>
                                                        </p>
                                                        <div class="flex flex-wrap items-center gap-y-1 mt-1">
                                                            <button
                                                                onclick='openManualBIRUpdateModal(<?php echo json_encode(["id" => $tenant["id"], "tradename" => $tenant["tradename"], "expiry" => $tenant["bir_expiry_date"]]); ?>)'
                                                                class="text-[10px] text-blue-600 hover:underline font-bold uppercase">
                                                                <i class="fas fa-edit mr-1"></i>Update Date
                                                            </button>
                                                            <?php if ($birDocumentUrl): ?>
                                                                <span class="text-gray-300 mx-1">|</span>
                                                                <a href="<?php echo htmlspecialchars($birDocumentUrl); ?>"
                                                                    target="_blank"
                                                                    class="text-[10px] text-emerald-600 hover:underline font-bold uppercase">
                                                                    <i class="fas fa-eye mr-1"></i>View BIR
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if ($birStatus !== 'valid' && $birStatus !== 'not_set'): ?>
                                                                <span class="text-gray-300 mx-1">|</span>
                                                                <button
                                                                    onclick="notifyTenantBIR(event, <?php echo $tenant['id']; ?>, '<?php echo addslashes($tenant['tradename']); ?>')"
                                                                    class="text-[10px] text-orange-600 hover:underline font-bold uppercase">
                                                                    <i class="fas fa-paper-plane mr-1"></i>Notify
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($birExpiry): ?>
                                                    <p
                                                        class="text-sm <?php echo ($birStatus === 'expired') ? 'text-red-500 font-bold' : 'text-gray-600'; ?>">
                                                        <i class="fas <?php echo $birIcon; ?> mr-1"></i>
                                                        <?php echo $birStatusText; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-store text-blue-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">Stall Number
                                                        </p>
                                                        <p class="text-lg font-bold text-gray-900">
                                                            <?php echo $tenant['stall_number'] ? htmlspecialchars($tenant['stall_number']) : 'Not Assigned'; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php if ($tenant['monthly_rate']): ?>
                                                    <p class="text-sm text-gray-600">
                                                        <i class="fas fa-money-bill-wave mr-1 text-green-600"></i>
                                                        ₱<?php echo number_format($tenant['monthly_rate'], 2); ?>/month
                                                    </p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-briefcase text-purple-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">Business Type
                                                        </p>
                                                        <p class="text-lg font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($tenant['business_type']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-user text-green-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">Contact Person
                                                        </p>
                                                        <p class="text-lg font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($tenant['contact_person']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-map-marker-alt text-indigo-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">Location</p>
                                                        <p class="text-lg font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($tenant['store_location']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contact Information Section -->
                                    <div class="mb-6">
                                        <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                            <i class="fas fa-address-book text-emerald-600"></i>
                                            Contact Details
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-phone text-emerald-600"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">Mobile Number
                                                        </p>
                                                        <p class="text-lg font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($tenant['mobile']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                                                        <i class="fas fa-envelope text-orange-600"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-gray-500 font-semibold uppercase">Email Address
                                                        </p>
                                                        <p class="text-sm font-bold text-gray-900 break-all">
                                                            <?php echo htmlspecialchars($tenant['email']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contract Status Section -->
                                    <div class="mb-6">
                                        <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                            <i class="fas fa-file-contract text-purple-600"></i>
                                            Contract & Lease Information
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div
                                                class="bg-gradient-to-br from-purple-50 to-pink-50 p-4 rounded-xl border-2 border-purple-200">
                                                <div class="flex items-center gap-3 mb-3">
                                                    <div
                                                        class="w-10 h-10 bg-purple-500 rounded-xl flex items-center justify-center">
                                                        <i class="fas fa-file-contract text-white"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-purple-600 font-semibold uppercase">Contract
                                                            Status</p>
                                                        <?php if ($tenant['lease_start_date'] && $tenant['lease_expiration_date']): ?>
                                                            <?php
                                                            $today = new DateTime();
                                                            $expiration = new DateTime($tenant['lease_expiration_date']);
                                                            $daysLeft = $today->diff($expiration)->days;
                                                            $isExpired = $today > $expiration;
                                                            ?>
                                                            <p
                                                                class="text-xl font-bold <?php echo $isExpired ? 'text-red-600' : 'text-purple-900'; ?>">
                                                                <?php echo $isExpired ? 'Expired' : ucfirst($tenant['contract_status'] ?? 'Active'); ?>
                                                            </p>
                                                        <?php else: ?>
                                                            <p class="text-xl font-bold text-gray-500">No Contract</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if ($tenant['lease_start_date'] && $tenant['lease_expiration_date']): ?>
                                                    <div class="space-y-2">
                                                        <div class="flex justify-between items-center">
                                                            <span class="text-sm text-purple-700"><i
                                                                    class="fas fa-calendar-check mr-1"></i>Start Date</span>
                                                            <span
                                                                class="font-semibold text-purple-900"><?php echo date('M d, Y', strtotime($tenant['lease_start_date'])); ?></span>
                                                        </div>
                                                        <div class="flex justify-between items-center">
                                                            <span class="text-sm text-purple-700"><i
                                                                    class="fas fa-calendar-times mr-1"></i>End Date</span>
                                                            <span
                                                                class="font-semibold text-purple-900"><?php echo date('M d, Y', strtotime($tenant['lease_expiration_date'])); ?></span>
                                                        </div>
                                                        <?php if (!$isExpired): ?>
                                                            <div class="mt-3 p-2 bg-purple-100 rounded-lg">
                                                                <p class="text-purple-900 font-bold text-center">
                                                                    <i class="fas fa-hourglass-half mr-1"></i><?php echo $daysLeft; ?>
                                                                    days remaining
                                                                </p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div
                                                class="bg-gradient-to-br from-blue-50 to-cyan-50 p-4 rounded-xl border-2 border-blue-200">
                                                <div class="flex items-center gap-3 mb-3">
                                                    <div
                                                        class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center">
                                                        <i class="fas fa-history text-white"></i>
                                                    </div>
                                                    <div class="flex-1">
                                                        <p class="text-xs text-blue-600 font-semibold uppercase">Latest Contract
                                                        </p>
                                                        <?php $latestContract = $latestContractsByTenantDetail[$tenant['id']] ?? null; ?>
                                                        <?php if ($latestContract): ?>
                                                            <p class="text-lg font-bold text-blue-900">
                                                                <?php echo strtoupper($latestContract['contract_status']); ?>
                                                            </p>
                                                            <p class="text-sm text-blue-700 mt-1">
                                                                Version <?php echo $latestContract['version']; ?> •
                                                                <?php echo date('M d, Y', strtotime($latestContract['created_at'])); ?>
                                                            </p>
                                                        <?php else: ?>
                                                            <p class="text-lg font-bold text-gray-500">No Contracts</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions Section -->
                                    <div class="border-t border-gray-300 pt-6">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-calendar mr-1"></i>
                                                Joined: <?php echo date('M d, Y', strtotime($tenant['created_at'])); ?>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <i class="fas fa-clock mr-1"></i>
                                                Last Updated: <?php echo date('M d, Y', strtotime($tenant['created_at'])); ?>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                                            <a href="tenant_profile.php?user_id=<?php echo $tenant['user_id']; ?>"
                                                class="flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-xl hover:from-blue-600 hover:to-purple-600 transition-all font-medium shadow-md hover:shadow-lg">
                                                <i class="fas fa-store-alt"></i>
                                                <span>View Profile</span>
                                            </a>

                                            <a href="generate_contract.php?tenant_detail_id=<?php echo $tenant['id']; ?>"
                                                class="flex items-center justify-center gap-2 px-4 py-3 bg-emerald-500 text-white rounded-xl hover:bg-emerald-600 transition-all font-medium shadow-md hover:shadow-lg">
                                                <i class="fas fa-file-signature"></i>
                                                <span>Generate Contract</span>
                                            </a>

                                            <?php if (!empty($latestContract)): ?>
                                                <a href="view_contract.php?id=<?php echo $latestContract['id']; ?>"
                                                    class="flex items-center justify-center gap-2 px-4 py-3 bg-slate-500 text-white rounded-xl hover:bg-slate-600 transition-all font-medium shadow-md hover:shadow-lg">
                                                    <i class="fas fa-eye"></i>
                                                    <span>View Contract</span>
                                                </a>
                                            <?php else: ?>
                                                <button disabled
                                                    class="flex items-center justify-center gap-2 px-4 py-3 bg-gray-300 text-gray-500 rounded-xl cursor-not-allowed font-medium">
                                                    <i class="fas fa-eye"></i>
                                                    <span>No Contract</span>
                                                </button>
                                            <?php endif; ?>

                                            <button
                                                onclick="openEditContractModal(<?php echo $tenant['id']; ?>, '<?php echo addslashes(htmlspecialchars($tenant['tradename'])); ?>', '<?php echo addslashes($tenant['lease_start_date'] ?? ''); ?>', '<?php echo addslashes($tenant['lease_expiration_date'] ?? ''); ?>')"
                                                class="flex items-center justify-center gap-2 px-4 py-3 bg-purple-500 text-white rounded-xl hover:bg-purple-600 transition-all font-medium shadow-md hover:shadow-lg">
                                                <i class="fas fa-edit"></i>
                                                <span>Adjust Lease Duration</span>
                                            </button>
                                        </div>

                                        <!-- Secondary Actions -->
                                        <div class="mt-4 flex justify-center">
                                            <button
                                                class="flex items-center justify-center gap-2 px-6 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all font-medium">
                                                <i class="fas fa-envelope"></i>
                                                <span>Contact Tenant</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="gradient-card rounded-2xl p-12 text-center shadow-lg border border-gray-100">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-users text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Tenants Found</h3>
                        <p class="text-gray-600">There are currently no approved tenants in the system.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section id="applicationsSection" class="hidden space-y-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-gray-600 mt-1">Review and manage tenant applications</p>
                    </div>
                    <div class="bg-emerald-100 text-emerald-700 px-4 py-2 rounded-xl font-semibold">
                        <i class="fas fa-file-alt mr-2"></i>
                        <?php echo count($applications); ?> Applications
                    </div>
                </div>

                <!-- Success/Warning Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div
                        class="mb-4 rounded-2xl bg-gradient-to-r from-emerald-50 to-green-50 text-emerald-700 px-6 py-4 border-l-4 border-emerald-500 shadow-md flex items-center gap-3">
                        <i class="fas fa-check-circle text-2xl"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['success']);
                        unset($_SESSION['success']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['warning'])): ?>
                    <div
                        class="mb-4 rounded-2xl bg-gradient-to-r from-amber-50 to-orange-50 text-amber-700 px-6 py-4 border-l-4 border-amber-500 shadow-md flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['warning']);
                        unset($_SESSION['warning']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div
                        class="mb-4 rounded-2xl bg-gradient-to-r from-red-50 to-pink-50 text-red-700 px-6 py-4 border-l-4 border-red-500 shadow-md flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-2xl"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($applications): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">
                        <div class="overflow-x-auto scrollbar-custom">
                            <table class="w-full min-w-[800px]">
                                <thead class="bg-gradient-to-r from-emerald-50 to-blue-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Applicant</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Business Info</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Stall</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Contact</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Date</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($applications as $index => $app): ?>
                                        <?php $isUnread = empty($app['admin_viewed']); ?>
                                        <tr class="hover:bg-gray-50 transition-colors<?php echo $isUnread ? ' unread-row' : ''; ?>"
                                            id="app-row-<?php echo $app['id']; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div
                                                            class="h-10 w-10 rounded-full bg-gradient-to-br from-emerald-400 to-blue-500 flex items-center justify-center text-white font-bold text-sm">
                                                            <?php echo strtoupper(substr($app['tradename'], 0, 2)); ?>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($app['tradename']); ?>
                                                            <?php if (empty($app['admin_viewed'])): ?>
                                                                <span class="ml-2 unread-notif-badge"
                                                                    id="unread-badge-<?php echo $app['id']; ?>"
                                                                    title="New Application">
                                                                    <span class="unread-dot"></span> NEW
                                                                    <?php if ($app['approved_stalls_count'] > 0): ?>
                                                                        <span
                                                                            class="ml-1 text-[0.6rem] border-l border-emerald-300 pl-1">ADD'L</span>
                                                                    <?php endif; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">ID:
                                                            <?php echo htmlspecialchars($app['user_id']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($app['company_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($app['business_type']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($app['stall_number']): ?>
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo htmlspecialchars($app['stall_number']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($app['stall_location']); ?>
                                                    </div>
                                                    <?php if ($app['monthly_rate']): ?>
                                                        <div class="text-xs text-emerald-600 font-semibold">
                                                            ₱<?php echo number_format($app['monthly_rate'], 2); ?>/mo</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="text-sm text-gray-500">No stall assigned</div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($app['mobile']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 truncate max-w-xs">
                                                    <?php echo htmlspecialchars($app['email']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button onclick="openApplicationModal(<?php echo $index; ?>)"
                                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg transition-all text-sm font-medium shadow-sm">
                                                    <i class="fas fa-eye mr-2"></i>View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="gradient-card rounded-3xl shadow-lg p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-inbox text-5xl text-gray-400"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">No Applications Yet</h2>
                        <p class="text-gray-600">Application submissions will appear here for review.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section id="renewalSection" class="hidden space-y-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="font-bold text-3xl text-gray-900">Contract Renewals</h1>
                        <p class="text-gray-600 mt-1">Manage contract renewal requests and expiring contracts</p>
                    </div>
                    <div class="bg-purple-100 text-purple-700 px-4 py-2 rounded-xl font-semibold">
                        <i class="fas fa-sync-alt mr-2"></i>
                        <?php echo count($contractRenewals); ?> Pending Renewals
                    </div>
                </div>

                <!-- Pending Renewal Requests -->
                <?php if ($contractRenewals): ?>
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-clock text-purple-600 mr-2"></i>Pending Renewal Requests
                        </h2>
                        <div class="grid grid-cols-1 gap-4">
                            <?php foreach ($contractRenewals as $renewal): ?>
                                <div
                                    class="gradient-card rounded-2xl shadow-lg overflow-hidden hover-lift border border-gray-100">
                                    <div class="p-6">
                                        <div class="flex items-start justify-between mb-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center text-white font-bold text-lg">
                                                        <?php echo strtoupper(substr($renewal['tradename'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-xl font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($renewal['tradename']); ?>
                                                        </h3>
                                                        <p class="text-sm text-gray-500">
                                                            <i class="fas fa-store mr-1"></i>
                                                            Stall:
                                                            <?php echo htmlspecialchars($renewal['stall_number'] ?? 'N/A'); ?>
                                                            <span class="mx-2">•</span>
                                                            <i class="fas fa-calendar mr-1"></i>
                                                            Submitted:
                                                            <?php echo date('M d, Y', strtotime($renewal['submitted_at'])); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <span
                                                class="px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-700">
                                                <i class="fas fa-clock mr-1"></i>Pending
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                            <div class="bg-gray-50 p-3 rounded-xl">
                                                <p class="text-xs text-gray-500 mb-1"><i
                                                        class="fas fa-calendar-times mr-1"></i>Old Contract End</p>
                                                <p class="font-semibold text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($renewal['old_expiration'])); ?>
                                                </p>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-xl">
                                                <p class="text-xs text-gray-500 mb-1"><i
                                                        class="fas fa-money-bill-wave mr-1"></i>Monthly Rate</p>
                                                <p class="font-semibold text-gray-900">
                                                    ₱<?php echo number_format($renewal['monthly_rate'], 2); ?></p>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-xl">
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-receipt mr-1"></i>Total
                                                    Amount</p>
                                                <p class="font-semibold text-emerald-600 text-lg">
                                                    ₱<?php echo number_format($renewal['total_amount'], 2); ?></p>
                                            </div>
                                        </div>

                                        <?php if ($renewal['late_renewal_fee'] > 0): ?>
                                            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl">
                                                <p class="text-sm text-red-700">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                                    <strong>Late Renewal Fee:</strong>
                                                    ₱<?php echo number_format($renewal['late_renewal_fee'], 2); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($renewal['payment_proof']): ?>
                                            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                                                <div class="flex items-center justify-between">
                                                    <p class="text-sm text-blue-700">
                                                        <i class="fas fa-file-image mr-2"></i>Payment proof uploaded
                                                    </p>
                                                    <a href="<?php echo htmlspecialchars($renewal['payment_proof']); ?>"
                                                        target="_blank"
                                                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                                                        <i class="fas fa-external-link-alt mr-1"></i>View
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex gap-3 pt-4 border-t border-gray-200">
                                            <a href="renewal_approval.php?id=<?php echo $renewal['id']; ?>&action=approve"
                                                class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 font-semibold shadow-md">
                                                <i class="fas fa-check-circle text-lg"></i>
                                                <span>Approve Renewal</span>
                                            </a>
                                            <a href="renewal_approval.php?id=<?php echo $renewal['id']; ?>&action=decline"
                                                class="flex-1 bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 font-semibold shadow-md">
                                                <i class="fas fa-times-circle text-lg"></i>
                                                <span>Decline</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Unified Renewal Requests -->
                <?php if ($unifiedRenewals): ?>
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-file-contract text-blue-600 mr-2"></i>Unified Renewal Requests
                        </h2>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="overflow-x-auto scrollbar-custom">
                                <table class="w-full min-w-[1000px]">
                                    <thead class="bg-gray-50 border-b border-gray-200">
                                        <tr>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Tenant</th>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Business Type</th>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Request Type</th>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Stall</th>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Amount</th>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Submitted</th>
                                            <th
                                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Documents</th>
                                            <th
                                                class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($unifiedRenewals as $renewal): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div
                                                            class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm mr-3">
                                                            <?php echo strtoupper(substr($renewal['tradename'] ?? $renewal['username'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="font-semibold text-gray-900">
                                                                <?php echo htmlspecialchars($renewal['tradename'] ?? $renewal['username']); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($renewal['user_email']); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-700">
                                                        <?php echo ucfirst(str_replace('_', ' ', $renewal['business_type'])); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <span
                                                        class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $renewal['request_type'] === 'renewal' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'; ?>">
                                                        <?php echo ucfirst($renewal['request_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($renewal['stall_number'] ?? 'N/A'); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="font-semibold text-gray-900">
                                                        ₱<?php echo number_format($renewal['total_amount'], 2); ?></div>
                                                    <?php if ($renewal['late_renewal_fee'] > 0): ?>
                                                        <div class="text-xs text-red-600">+
                                                            ₱<?php echo number_format($renewal['late_renewal_fee'], 2); ?> late fee
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($renewal['submitted_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <button
                                                        onclick="viewDocuments(<?php echo htmlspecialchars(json_encode($renewal)); ?>)"
                                                        class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                                                        <i class="fas fa-folder-open mr-1"></i>View Documents
                                                    </button>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center justify-center gap-2">
                                                        <button
                                                            onclick="showApprovalModal(<?php echo $renewal['id']; ?>, '<?php echo addslashes(htmlspecialchars($renewal['tradename'] ?? $renewal['username'])); ?>', '<?php echo addslashes(htmlspecialchars($renewal['user_email'])); ?>')"
                                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                                            <i class="fas fa-check mr-1"></i>Approve
                                                        </button>
                                                        <button
                                                            onclick="showDeclineModal(<?php echo $renewal['id']; ?>, '<?php echo addslashes(htmlspecialchars($renewal['tradename'] ?? $renewal['username'])); ?>', '<?php echo addslashes(htmlspecialchars($renewal['user_email'])); ?>')"
                                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                                                            <i class="fas fa-times mr-1"></i>Decline
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Expiring Contracts -->
                <?php if ($expiringContracts): ?>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 mb-4">
                            <i class="fas fa-exclamation-triangle text-orange-600 mr-2"></i>Expiring Contracts (Next 30
                            Days)
                        </h2>
                        <div class="grid grid-cols-1 gap-4">
                            <?php foreach ($expiringContracts as $contract): ?>
                                <?php
                                $daysLeft = $contract['days_remaining'];
                                $statusColor = $daysLeft <= 7 ? 'red' : ($daysLeft <= 14 ? 'orange' : 'yellow');
                                $statusText = $daysLeft < 0 ? 'Expired' : ($daysLeft == 0 ? 'Expires Today' : $daysLeft . ' days left');
                                ?>
                                <div
                                    class="gradient-card rounded-2xl shadow-lg overflow-hidden border-l-4 border-<?php echo $statusColor; ?>-500">
                                    <div class="p-6">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <div
                                                        class="w-10 h-10 bg-gradient-to-br from-orange-400 to-red-500 rounded-lg flex items-center justify-center text-white font-bold">
                                                        <?php echo strtoupper(substr($contract['tradename'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-lg font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($contract['tradename']); ?>
                                                        </h3>
                                                        <p class="text-sm text-gray-500">
                                                            <i class="fas fa-store mr-1"></i>
                                                            Stall:
                                                            <?php echo htmlspecialchars($contract['stall_number'] ?? 'N/A'); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <span
                                                class="px-3 py-1 rounded-full text-sm font-semibold bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-700">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-4">
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <p class="text-xs text-gray-500">Contract Start</p>
                                                <p class="font-semibold text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($contract['lease_start_date'])); ?>
                                                </p>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <p class="text-xs text-gray-500">Contract End</p>
                                                <p class="font-semibold text-gray-900">
                                                    <?php echo date('M d, Y', strtotime($contract['lease_expiration_date'])); ?>
                                                </p>
                                            </div>
                                            <div class="bg-gray-50 p-3 rounded-lg">
                                                <p class="text-xs text-gray-500">Contact</p>
                                                <p class="font-semibold text-gray-900 text-sm">
                                                    <?php echo htmlspecialchars($contract['mobile'] ?? $contract['email']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$contractRenewals && !$expiringContracts): ?>
                    <div class="gradient-card rounded-3xl shadow-lg p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-sync-alt text-5xl text-gray-400"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">No Renewal Requests</h2>
                        <p class="text-gray-600">Contract renewal requests will appear here for review.</p>
                    </div>
                <?php endif; ?>
            </section>

            <section id="paymentsSection" class="hidden space-y-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="font-bold text-3xl text-gray-900">Payment Management</h1>
                        <p class="text-gray-600 mt-1">Review approved payments and send reminders for unpaid ones</p>
                    </div>
                </div>

                <!-- Payment Tabs -->
                <div class="flex items-center gap-4 mb-6 border-b border-gray-200 p-1">
                    <button onclick="switchPaymentsTab('submissions')"
                        id="tab-payments-submissions"
                        class="px-6 py-3 text-sm font-bold transition-all border-b-2 border-blue-600 text-blue-600">
                        <i class="fas fa-credit-card mr-2"></i>Payment Submissions
                    </button>
                    <button onclick="switchPaymentsTab('unpaid')"
                        id="tab-payments-unpaid"
                        class="px-6 py-3 text-sm font-medium transition-all border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        <i class="fas fa-exclamation-triangle mr-2 text-orange-500"></i>Unpaid Reminders (<?php echo count($unpaidHistory); ?>)
                    </button>
                </div>

                <!-- View 1: Payment Submissions Table -->
                <div id="paymentsSubmissionsView" class="space-y-6">

                <?php if ($payments): ?>
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto scrollbar-custom">
                            <table class="w-full min-w-[1200px]">
                                <thead class="bg-gradient-to-r from-blue-50 to-purple-50 border-b-2 border-gray-200">
                                    <tr>
                                        <th class="px-6 py-4 text-left">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-user text-blue-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Tenant</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-left">
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-user-tie text-green-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Tenant Representative</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <div
                                                    class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-store text-purple-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Stall</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <div
                                                    class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-money-bill-wave text-amber-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Amount</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <div
                                                    class="w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-calendar text-cyan-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Date</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <div
                                                    class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-info-circle text-gray-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Status</span>
                                            </div>
                                        </th>
                                        <th class="px-6 py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                                                    <i class="fas fa-cog text-red-600 text-sm"></i>
                                                </div>
                                                <span class="font-bold text-gray-800">Actions</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($payments as $pay): ?>
                                        <?php
                                        $statusColor = strtolower($pay['status']) === 'approved' ? 'emerald' :
                                            (strtolower($pay['status']) === 'declined' ? 'red' : 'amber');
                                        $statusIcon = strtolower($pay['status']) === 'approved' ? 'check-circle' :
                                            (strtolower($pay['status']) === 'declined' ? 'times-circle' : 'clock');

                                        $totalAmount = isset($pay['amount']) ? (float) $pay['amount'] : 0;
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div
                                                        class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                                        <?php echo strtoupper(substr($pay['username'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-gray-900">
                                                            <?php echo htmlspecialchars($pay['username']); ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 font-medium">ID:
                                                            <?php echo (int) $pay['user_id']; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-2">
                                                    <i class="fas fa-user-tie text-gray-400 text-sm"></i>
                                                    <span
                                                        class="font-medium text-gray-700"><?php echo htmlspecialchars($pay['tenant_representative'] ?? 'N/A'); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center">
                                                    <?php if (!empty($pay['stall_number'])): ?>
                                                        <span
                                                            class="px-3 py-1 bg-purple-100 text-purple-700 rounded-lg text-sm font-semibold">
                                                            <?php echo htmlspecialchars($pay['stall_number']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400">—</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div
                                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-emerald-50 to-green-50 border border-emerald-200 rounded-xl">
                                                    <i class="fas fa-peso-sign text-emerald-600"></i>
                                                    <span
                                                        class="font-bold text-emerald-700">₱<?php echo number_format($totalAmount, 2); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div class="text-center">
                                                    <p class="font-semibold text-gray-800">
                                                        <?php echo date('M d, Y', strtotime($pay['payment_date'])); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?php echo date('h:i A', strtotime($pay['payment_date'])); ?>
                                                    </p>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-700">
                                                    <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                                    <?php echo htmlspecialchars(ucfirst($pay['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <!-- View Payment Button -->
                                                    <button
                                                        onclick="window.open('<?php echo htmlspecialchars($pay['payment_image']); ?>', '_blank')"
                                                        class="p-2 bg-blue-100 hover:bg-blue-200 text-blue-600 rounded-lg transition-all"
                                                        title="View Payment Proof">
                                                        <i class="fas fa-eye"></i>
                                                    </button>

                                                    <?php if (strtolower($pay['status']) !== 'approved' && strtolower($pay['status']) !== 'declined'): ?>
                                                        <!-- Approve Button -->
                                                        <button
                                                            onclick="confirmPaymentAction(<?php echo $pay['id']; ?>, 'approved', '<?php echo addslashes(htmlspecialchars($pay['username'])); ?>')"
                                                            class="p-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-600 rounded-lg transition-all"
                                                            title="Approve Payment">
                                                            <i class="fas fa-check"></i>
                                                        </button>

                                                        <!-- Decline Button -->
                                                        <button
                                                            onclick="confirmPaymentAction(<?php echo $pay['id']; ?>, 'declined', '<?php echo addslashes(htmlspecialchars($pay['username'])); ?>')"
                                                            class="p-2 bg-red-100 hover:bg-red-200 text-red-600 rounded-lg transition-all"
                                                            title="Decline Payment">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- Processed Indicator -->
                                                        <div class="p-2 bg-gray-100 text-gray-400 rounded-lg"
                                                            title="Already processed">
                                                            <i class="fas fa-check-double"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="gradient-card rounded-2xl p-12 text-center shadow-lg border border-gray-100">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-credit-card text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Payments Found</h3>
                        <p class="text-gray-600">There are currently no payment submissions to review.</p>
                    </div>
                <?php endif; ?>
                </div>

                <!-- View 2: Unpaid Reminders (Relocated) -->
                <div id="paymentsUnpaidView" class="hidden space-y-6">
                    <div class="mb-4">
                        <h3 class="text-xl font-bold text-gray-900">Payment Due Reminders</h3>
                        <p class="text-gray-600">Send notifications to tenants with outstanding balances for the current period.</p>
                    </div>

                    <?php if (empty($unpaidHistory)): ?>
                        <div class="bg-white rounded-3xl p-16 text-center shadow-lg border border-gray-100">
                            <div class="w-24 h-24 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-check-circle text-4xl text-emerald-500"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-900 mb-2">Excellent!</h4>
                            <p class="text-gray-600">All tenants have submitted their payments.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 gap-4">
                            <?php foreach ($unpaidHistory as $tenant): ?>
                                <div class="bg-white rounded-2xl p-6 shadow-lg border border-gray-100 hover:shadow-xl transition-all flex flex-col md:flex-row items-center justify-between gap-6">
                                    <div class="flex items-center gap-4 flex-1 w-full">
                                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center font-bold text-2xl shadow-lg shrink-0">
                                            <?php echo strtoupper(substr($tenant['tradename'] ?? 'T', 0, 1)); ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-xl text-gray-900 truncate"><?php echo htmlspecialchars($tenant['tradename'] ?? 'N/A'); ?></h4>
                                            <div class="flex flex-wrap gap-y-2 gap-x-6 mt-2">
                                                <span class="text-sm text-gray-600 flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-lg"><i class="fas fa-store text-gray-400"></i> Stall: <?php echo htmlspecialchars($tenant['stall_number'] ?? 'N/A'); ?></span>
                                                <span class="text-sm text-gray-600 flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-lg"><i class="fas fa-peso-sign text-emerald-600 text-xs"></i> Rate: ₱<?php echo number_format($tenant['monthly_rate'] ?? 0, 2); ?></span>
                                                <span class="text-sm text-gray-600 flex items-center gap-2 px-3 py-1 bg-gray-100 rounded-lg"><i class="fas fa-phone text-blue-500 text-xs"></i> <?php echo htmlspecialchars($tenant['mobile'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 w-full md:w-auto">
                                        <button type="button"
                                            onclick="sendDueDateReminder(this, <?php echo $tenant['user_id']; ?>, <?php echo $tenant['monthly_rate'] ?? 0; ?>)"
                                            class="flex-1 md:flex-none bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white px-8 py-3.5 rounded-xl font-bold shadow-lg shadow-orange-200 transition-all flex items-center justify-center gap-2">
                                            <i class="fas fa-paper-plane"></i> Send Payment Reminder
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Billing Modal (Redesigned from Section) -->
            <div id="billingModal"
                class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
                <div
                    class="bg-white rounded-[2rem] shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-hidden animate-fade-in flex flex-col border border-gray-100">
                    <!-- Modal Header -->
                    <div
                        class="bg-gradient-to-r from-emerald-600 via-blue-600 to-indigo-700 px-8 py-6 flex items-center justify-between shadow-lg relative overflow-hidden">
                        <div class="absolute inset-0 bg-white/10 opacity-20 pointer-events-none"></div>
                        <div class="relative z-10 flex items-center gap-4">
                            <div
                                class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center border border-white/30 shadow-inner">
                                <i class="fas fa-file-invoice-dollar text-white text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-white font-black text-2xl tracking-tight">Billing Management</h2>
                                <p class="text-emerald-100/80 text-sm font-medium">Update utility bills for all active
                                    tenants</p>
                            </div>
                        </div>
                        <div class="relative z-10 flex items-center gap-3">
                            <div
                                class="hidden md:flex bg-white/10 backdrop-blur-sm border border-white/20 px-4 py-2 rounded-xl text-white font-bold text-sm items-center gap-2">
                                <i class="fas fa-users-cog"></i>
                                <span><?php echo count($billingTenants); ?> Active Tenants</span>
                            </div>
                            <button type="button" onclick="closeBillingModal()"
                                class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-all border border-white/20 hover:scale-110">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Alert Container (Inside Modal) -->
                    <div id="billingModalAlerts" class="px-8 mt-4 empty:hidden"></div>

                    <form method="post" enctype="multipart/form-data" class="flex-1 flex flex-col overflow-hidden">
                        <input type="hidden" name="save_bills" value="1" />

                        <!-- View 1: Billing Form (Table) -->
                        <div id="billingModalFormView" class="flex-1 flex flex-col overflow-hidden">

                            <!-- Month & Action Bar -->
                            <div class="px-8 py-6 bg-gray-50/50 border-b border-gray-100">
                                <div class="flex flex-col md:flex-row gap-6 items-center justify-between">
                                    <div class="w-full md:w-auto">
                                        <label
                                            class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2 flex items-center gap-2 px-1">
                                            <i class="fas fa-calendar-alt text-blue-600"></i>
                                            Target Billing Month
                                        </label>
                                        <div class="relative">
                                            <input type="month" name="billing_month" required
                                                class="w-full md:w-72 px-5 py-3.5 bg-white border-2 border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/20 focus:border-blue-600 transition-all font-bold text-gray-800 shadow-sm"
                                                value="<?php echo htmlspecialchars(date('Y-m')); ?>" />
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4 w-full md:w-auto">
                                        <button type="button" onclick="closeBillingModal()"
                                            class="flex-1 md:flex-none px-6 py-3.5 bg-white border-2 border-gray-200 text-gray-600 rounded-2xl hover:bg-gray-50 font-bold transition-all flex items-center justify-center gap-2">
                                            Cancel
                                        </button>
                                        <button type="button" onclick="confirmSaveBills()"
                                            class="flex-1 md:flex-auto px-8 py-3.5 bg-gradient-to-r from-emerald-500 to-blue-600 text-white rounded-2xl hover:from-emerald-600 hover:to-blue-700 font-bold shadow-xl shadow-blue-500/20 transition-all transform hover:scale-[1.02] flex items-center justify-center gap-3">
                                            <i class="fas fa-save text-lg"></i>
                                            <span>Process All Billings</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Table Container -->
                            <div class="flex-1 overflow-y-auto px-8 py-6 custom-scrollbar bg-white">
                                <div class="border border-gray-100 rounded-[1.5rem] overflow-hidden shadow-sm">
                                    <table class="w-full border-collapse">
                                        <thead class="bg-gray-50 sticky top-0 z-20">
                                            <tr class="border-b-2 border-gray-100">
                                                <th
                                                    class="px-6 py-5 text-left text-xs font-black text-gray-400 uppercase tracking-widest bg-gray-50">
                                                    Tenant Details</th>
                                                <th
                                                    class="px-6 py-5 text-left text-xs font-black text-gray-400 uppercase tracking-widest bg-gray-50">
                                                    Unit</th>
                                                <th
                                                    class="px-4 py-5 text-center text-xs font-black text-gray-400 uppercase tracking-widest bg-gray-50">
                                                    Utility Bill Photo</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php if (empty($billingTenants)): ?>
                                                <tr>
                                                    <td colspan="3" class="px-6 py-20 text-center">
                                                        <div class="flex flex-col items-center gap-4">
                                                            <div
                                                                class="w-16 h-16 bg-gray-50 text-gray-300 rounded-2xl flex items-center justify-center">
                                                                <i class="fas fa-users-slash text-2xl"></i>
                                                            </div>
                                                            <p class="text-gray-400 font-bold">No active tenants available
                                                                for billing</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($billingTenants as $index => $t):
                                                    $key = (int) $t['user_id'] . '_' . (int) $t['stall_id'];
                                                    ?>
                                                    <tr class="group hover:bg-blue-50/30 transition-colors">
                                                        <td class="px-6 py-5">
                                                            <div class="flex items-center gap-4">
                                                                <div
                                                                    class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-2xl flex items-center justify-center text-white font-black shadow-md transform group-hover:scale-110 transition-transform">
                                                                    <?php echo strtoupper(substr($t['username'], 0, 2)); ?>
                                                                </div>
                                                                <div>
                                                                    <p class="font-black text-gray-900 leading-tight">
                                                                        <?php echo htmlspecialchars($t['username']); ?>
                                                                    </p>
                                                                    <p
                                                                        class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-0.5">
                                                                        ID: <?php echo (int) $t['user_id']; ?></p>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-5">
                                                            <div class="inline-flex flex-col">
                                                                <span
                                                                    class="px-3 py-1 bg-white border border-purple-100 text-purple-700 rounded-lg text-xs font-black shadow-sm mb-1">
                                                                    <?php echo htmlspecialchars($t['stall_number']); ?>
                                                                </span>
                                                                <span
                                                                    class="text-[10px] text-gray-500 font-bold truncate max-w-[120px]"><?php echo htmlspecialchars($t['stall_location']); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="px-4 py-5">
                                                            <div class="flex flex-col items-center justify-center">
                                                                <!-- Hidden inputs to store modal values -->
                                                                <input type="hidden" name="water_bill[<?php echo $key; ?>]"
                                                                    id="water_amount_<?php echo $key; ?>" value="0">
                                                                <input type="hidden" name="electric_bill[<?php echo $key; ?>]"
                                                                    id="electric_amount_<?php echo $key; ?>" value="0">

                                                                <!-- Hidden file inputs -->
                                                                <input type="file" name="bill_image[<?php echo $key; ?>]"
                                                                    id="water_file_<?php echo $key; ?>" class="hidden"
                                                                    onchange="updateIndividualBillStatus('<?php echo $key; ?>')">
                                                                <input type="file"
                                                                    name="electric_bill_image[<?php echo $key; ?>]"
                                                                    id="electric_file_<?php echo $key; ?>" class="hidden"
                                                                    onchange="updateIndividualBillStatus('<?php echo $key; ?>')">

                                                                <button type="button"
                                                                    onclick="openIndividualBillModal('<?php echo $key; ?>', '<?php echo addslashes($t['username']); ?>', '<?php echo addslashes(htmlspecialchars($t['stall_number'])); ?>')"
                                                                    class="w-full max-w-[180px] flex items-center justify-center gap-2 px-3 py-2 bg-gray-50 border border-dashed border-blue-200 rounded-lg cursor-pointer hover:bg-blue-50 transition-all group/btn"
                                                                    id="btn_config_<?php echo $key; ?>">
                                                                    <i class="fas fa-cog text-blue-600 text-xs"></i>
                                                                    <span
                                                                        class="text-[9px] font-bold text-gray-500 uppercase">Configure
                                                                        Bill</span>
                                                                </button>

                                                                <!-- Status Badges -->
                                                                <div id="status_badges_<?php echo $key; ?>"
                                                                    class="mt-2 flex flex-wrap gap-1 justify-center hidden">
                                                                    <span id="badge_water_<?php echo $key; ?>"
                                                                        class="hidden px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[8px] font-black uppercase">W</span>
                                                                    <span id="badge_electric_<?php echo $key; ?>"
                                                                        class="hidden px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-[8px] font-black uppercase">E</span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Bottom Summary -->
                            <div
                                class="px-8 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-[11px] font-black text-gray-400 uppercase tracking-widest">
                                <span>Scroll for more tenants</span>
                                <span id="billingCounter"><?php echo count($billingTenants); ?> Units Selected</span>
                            </div>
                        </div>

                        <!-- View 2: Confirmation Summary (Hidden by Default) -->
                        <div id="billingModalConfirmView" class="hidden flex-1 overflow-y-auto px-8 py-10 bg-white">
                            <div class="max-w-2xl mx-auto space-y-8">
                                <div class="text-center">
                                    <div
                                        class="w-20 h-20 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center mx-auto mb-4 shadow-sm border border-blue-100">
                                        <i class="fas fa-clipboard-check text-3xl"></i>
                                    </div>
                                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Review Uploads</h3>
                                    <p class="text-gray-500 font-bold mt-1">Please confirm the details before finalizing
                                    </p>
                                </div>

                                <div
                                    class="bg-blue-50/50 border-2 border-dashed border-blue-200 rounded-2xl p-6 flex items-center justify-center gap-4">
                                    <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                                    <p class="text-blue-900 font-black text-lg tracking-tight" id="confirmBillingMonth">
                                        --
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-6">
                                    <div
                                        class="bg-emerald-50 border border-emerald-100 rounded-3xl p-6 text-center shadow-sm">
                                        <i class="fas fa-file-image text-emerald-600 text-3xl mb-3 block"></i>
                                        <p
                                            class="text-[11px] text-emerald-700 font-black uppercase tracking-widest mb-1">
                                            Bill Photos</p>
                                        <p class="text-4xl font-black text-emerald-900" id="confirmBillCount">0</p>
                                    </div>
                                </div>

                                <div
                                    class="bg-emerald-50 border border-emerald-100 rounded-3xl p-6 flex items-center justify-between shadow-sm">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-12 h-12 bg-emerald-500 rounded-2xl flex items-center justify-center text-white shadow-lg">
                                            <i class="fas fa-users text-xl"></i>
                                        </div>
                                        <div>
                                            <p
                                                class="text-[11px] font-black text-emerald-900 uppercase tracking-widest">
                                                Affected Tenants</p>
                                            <p class="text-xs font-bold text-emerald-600">Successfully matched units</p>
                                        </div>
                                    </div>
                                    <p class="text-4xl font-black text-emerald-600" id="confirmTenantCount">0</p>
                                </div>

                                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start gap-4">
                                    <div class="text-amber-600 mt-1">
                                        <i class="fas fa-info-circle text-xl"></i>
                                    </div>
                                    <p class="text-amber-900 text-sm font-bold leading-relaxed">
                                        Finalizing will automatically set amounts to PHP 0.00 and notify tenants to
                                        check their dashboards for verification.
                                    </p>
                                </div>

                                <div class="flex gap-4 pt-4 pb-10">
                                    <button type="button" onclick="backToBillingForm()"
                                        class="flex-1 px-8 py-5 bg-white border-2 border-gray-200 text-gray-600 rounded-2xl hover:bg-gray-50 transition-all font-black uppercase tracking-widest text-xs">
                                        <i class="fas fa-arrow-left mr-2"></i> Go Back
                                    </button>
                                    <button type="button" onclick="proceedSaveBills(this)"
                                        class="flex-1 px-8 py-5 bg-gradient-to-r from-emerald-500 to-blue-600 text-white rounded-2xl hover:scale-[1.02] hover:shadow-2xl transition-all font-black uppercase tracking-widest text-xs shadow-xl shadow-blue-500/20">
                                        <i class="fas fa-check-circle mr-2"></i> Finalize & Notify
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>


            <!-- Active Contracts Section -->
            <section id="contractsSection" class="hidden space-y-6 animate-fade-in">
                <!-- Stats Header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="bg-white rounded-2xl px-4 py-2 shadow-sm border border-gray-100">
                            <span class="text-sm text-gray-600">Total Active: </span>
                            <span class="font-semibold text-green-600"><?php echo count($activeContracts); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Contracts Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 border-b border-green-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-bold text-gray-800">
                                <i class="fas fa-file-contract text-green-600 mr-2"></i>
                                Active Contracts List
                            </h3>
                            <div class="flex gap-2">
                                <button onclick="sendExpirationNotifications()"
                                    class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-semibold text-sm transition-all shadow-md">
                                    <i class="fas fa-bell mr-1"></i>Send Notification
                                </button>
                                <button onclick="selectAllContracts()"
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg font-semibold text-sm transition-all">
                                    <i class="fas fa-check-square mr-1"></i>Select All
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th
                                        class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAllContracts()"
                                            class="rounded border-gray-300 text-orange-500 focus:ring-orange-500">
                                    </th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Company Name</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Email</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Mobile</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Stall #</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Monthly Rate</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Contract Start</th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Contract End</th>
                                    <th
                                        class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Days Remaining</th>
                                    <th
                                        class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($activeContracts)): ?>
                                    <tr>
                                        <td colspan="11" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-inbox text-4xl mb-2 block text-gray-300"></i>
                                            No active contracts found
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($activeContracts as $contract):
                                        $daysRemaining = (new DateTime($contract['lease_end_date']))->diff(new DateTime())->days;
                                        $isExpiringSoon = $daysRemaining <= 30;

                                        // Determine status based on database and expiration
                                        if ($isExpiringSoon) {
                                            $statusColor = 'text-red-600 bg-red-100';
                                            $statusText = 'Expiring Soon';
                                        } else {
                                            $statusColor = 'text-green-600 bg-green-100';
                                            $statusText = 'Active';
                                        }
                                        ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 text-center">
                                                <input type="checkbox"
                                                    class="contract-checkbox rounded border-gray-300 text-orange-500 focus:ring-orange-500"
                                                    data-tenant-id="<?php echo htmlspecialchars($contract['tenant_detail_id']); ?>"
                                                    data-email="<?php echo htmlspecialchars($contract['email']); ?>"
                                                    data-mobile="<?php echo htmlspecialchars($contract['mobile']); ?>"
                                                    data-company="<?php echo htmlspecialchars($contract['company_name']); ?>"
                                                    data-days-remaining="<?php echo $daysRemaining; ?>">
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars($contract['company_name']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-600"><?php echo htmlspecialchars($contract['email']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-600"><?php echo htmlspecialchars($contract['mobile']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-900 font-medium">
                                                    <?php echo htmlspecialchars($contract['stall_number'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-900 font-medium">
                                                    ₱<?php echo number_format($contract['monthly_rate'] ?? 0, 2); ?></div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-600">
                                                    <?php echo date('M d, Y', strtotime($contract['lease_start_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-gray-900 font-medium">
                                                    <?php echo date('M d, Y', strtotime($contract['lease_end_date'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <span
                                                    class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center">
                                                <div
                                                    class="font-medium <?php echo $isExpiringSoon ? 'text-red-600' : 'text-gray-600'; ?>">
                                                    <?php echo $daysRemaining; ?> days
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-center border-l border-gray-100">
                                                <button
                                                    onclick="openNotifyBalanceModal(<?php echo $contract['tenant_detail_id']; ?>, '<?php echo addslashes(htmlspecialchars($contract['company_name'])); ?>', '<?php echo addslashes(htmlspecialchars($contract['email'])); ?>', '<?php echo addslashes(htmlspecialchars($contract['mobile'])); ?>', <?php echo $contract['monthly_rate'] ?? 0; ?>, '<?php echo addslashes($contract['lease_end_date']); ?>')"
                                                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm hover:shadow-md flex items-center justify-center gap-1 mx-auto">
                                                    <i class="fas fa-bell"></i> Notify Balance
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Reports Section -->
            <section id="historySection" class="hidden space-y-6 animate-fade-in">
                <!-- Header -->
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <p class="text-gray-600 mt-1">View comprehensive reports for applications, payments, billing,
                            work permits, and renewals</p>
                    </div>
                </div>

                <!-- Unified Tables -->
                <div class="space-y-6">

                    <!-- Unified Applications Table -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 border-b border-blue-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-gray-800">
                                    <i class="fas fa-file-alt text-blue-600 mr-2"></i>
                                    Applications History (<?php echo count($unifiedApplications); ?>)
                                </h3>
                                <div class="flex gap-2">
                                    <button onclick="printApplications()"
                                        class="bg-blue-100 hover:bg-blue-200 text-blue-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-print mr-1"></i>Print
                                    </button>
                                    <button onclick="exportApplications()"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Applicant Details</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Business Information</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Stall Details</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Contact Information</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Application Status</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Date Applied</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($unifiedApplications)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div
                                                    class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                                                </div>
                                                <p class="text-gray-500 font-medium">No application history found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($unifiedApplications as $app): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div
                                                            class="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 text-white flex items-center justify-center font-bold text-sm shadow-md">
                                                            <?php echo strtoupper(substr($app['tradename'] ?? 'A', 0, 1)); ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-bold text-gray-900">
                                                                <?php echo htmlspecialchars($app['tradename'] ?? 'N/A'); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($app['username'] ?? 'N/A'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-1">
                                                        <div class="text-sm font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?>
                                                        </div>
                                                        <div
                                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <?php echo ucfirst(str_replace('_', ' ', $app['business_type'] ?? 'N/A')); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-store text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($app['stall_number'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-peso-sign text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm text-gray-600">₱<?php echo number_format($app['monthly_rate'] ?? 0, 2); ?>/mo</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-envelope text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm text-gray-900"><?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-phone text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm text-gray-600"><?php echo htmlspecialchars($app['mobile'] ?? 'N/A'); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-<?php echo $app['status_color'] === 'success' ? 'green' : ($app['status_color'] === 'error' ? 'red' : 'yellow'); ?>-100 text-<?php echo $app['status_color'] === 'success' ? 'green' : ($app['status_color'] === 'error' ? 'red' : 'yellow'); ?>-800">
                                                        <i
                                                            class="fas fa-<?php echo $app['status_color'] === 'success' ? 'check' : ($app['status_color'] === 'error' ? 'times' : 'clock'); ?>-circle mr-1"></i>
                                                        <?php echo $app['application_status']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="text-sm text-gray-900 font-medium">
                                                        <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo date('h:i A', strtotime($app['created_at'])); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Unified Payments Table -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 p-4 border-b border-emerald-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-gray-800">
                                    <i class="fas fa-credit-card text-emerald-600 mr-2"></i>
                                    Payment History (<?php echo count($unifiedPayments); ?>)
                                </h3>
                                <div class="flex gap-2">
                                    <button onclick="printPayments()"
                                        class="bg-emerald-100 hover:bg-emerald-200 text-emerald-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-print mr-1"></i>Print
                                    </button>
                                    <button onclick="exportPayments()"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Tenant Information</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Stall Details</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Payment Amount</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Payment Type</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Payment Method</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Payment Status</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Payment Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($unifiedPayments)): ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-12 text-center">
                                                <div
                                                    class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                                                </div>
                                                <p class="text-gray-500 font-medium">No payment history found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($unifiedPayments as $payment): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div
                                                            class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center font-bold text-sm shadow-md">
                                                            <?php echo strtoupper(substr($payment['tradename'] ?? 'T', 0, 1)); ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-bold text-gray-900">
                                                                <?php echo htmlspecialchars($payment['tradename'] ?? $payment['username'] ?? 'N/A'); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($payment['email'] ?? 'N/A'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-store text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($payment['stall_number'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-peso-sign text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm text-gray-600">₱<?php echo number_format($payment['monthly_rate'] ?? 0, 2); ?>/mo</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="text-lg font-bold text-emerald-600">
                                                        ₱<?php echo number_format($payment['amount'] ?? 0, 2); ?></div>
                                                    <?php if ($payment['billing_month']): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <?php echo date('M Y', strtotime($payment['billing_month'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                                        <i class="fas fa-tag mr-1"></i>
                                                        <?php echo ucfirst($payment['payment_type'] ?? 'N/A'); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                                        <i class="fas fa-credit-card mr-1"></i>
                                                        <?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-<?php echo $payment['payment_color'] === 'success' ? 'green' : ($payment['payment_color'] === 'error' ? 'red' : ($payment['payment_color'] === 'warning' ? 'yellow' : 'gray')); ?>-100 text-<?php echo $payment['payment_color'] === 'success' ? 'green' : ($payment['payment_color'] === 'error' ? 'red' : ($payment['payment_color'] === 'warning' ? 'yellow' : 'gray')); ?>-800">
                                                        <i
                                                            class="fas fa-<?php echo $payment['payment_color'] === 'success' ? 'check' : ($payment['payment_color'] === 'error' ? 'times' : ($payment['payment_color'] === 'warning' ? 'clock' : 'minus')); ?>-circle mr-1"></i>
                                                        <?php echo $payment['payment_status_text']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <?php if ($payment['payment_date']): ?>
                                                        <div class="text-sm text-gray-900 font-medium">
                                                            <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            <?php echo date('h:i A', strtotime($payment['payment_date'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-sm text-gray-400 font-medium">No payment</div>
                                                        <div class="text-xs text-gray-400">Pending</div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Work Permits Table -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-orange-50 to-amber-50 p-4 border-b border-orange-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-gray-800">
                                    <i class="fas fa-hard-hat text-orange-600 mr-2"></i>
                                    Work Permit Reports (<?php echo count($workPermitReports); ?>)
                                </h3>
                                <div class="flex gap-2">
                                    <button onclick="printWorkPermits()"
                                        class="bg-orange-100 hover:bg-orange-200 text-orange-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-print mr-1"></i>Print
                                    </button>
                                    <button onclick="exportWorkPermits()"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Permit Information</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Tenant Details</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Total Charges</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Permit Status</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Date Filed</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($workPermitReports)): ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center">
                                                <div
                                                    class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                                                </div>
                                                <p class="text-gray-500 font-medium">No work permit records found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($workPermitReports as $permit): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="space-y-2">
                                                        <div class="flex items-center">
                                                            <div
                                                                class="w-10 h-10 rounded-full bg-gradient-to-br from-orange-500 to-amber-500 text-white flex items-center justify-center font-bold text-sm shadow-md">
                                                                <i class="fas fa-hard-hat"></i>
                                                            </div>
                                                            <div class="ml-3">
                                                                <div class="text-sm font-bold text-gray-900">
                                                                    <?php echo htmlspecialchars($permit['permit_no'] ?? 'N/A'); ?>
                                                                </div>
                                                                <div class="text-xs text-gray-500">Permit Number</div>
                                                            </div>
                                                        </div>
                                                        <div class="ml-3">
                                                            <div class="text-sm text-gray-900 max-w-xs truncate"
                                                                title="<?php echo htmlspecialchars($permit['scope_of_work'] ?? ''); ?>">
                                                                <?php echo htmlspecialchars(substr($permit['scope_of_work'] ?? 'N/A', 0, 60)) . (strlen($permit['scope_of_work'] ?? '') > 60 ? '...' : ''); ?>
                                                            </div>
                                                            <div class="text-xs text-gray-500">Scope of Work</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center">
                                                            <i class="fas fa-user text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($permit['tenant_name'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-store text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm text-gray-600"><?php echo htmlspecialchars($permit['stall_number'] ?? 'N/A'); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-envelope text-gray-400 mr-2 text-xs"></i>
                                                            <span
                                                                class="text-sm text-gray-600"><?php echo htmlspecialchars($permit['email'] ?? 'N/A'); ?></span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="text-lg font-bold text-orange-600">
                                                        ₱<?php echo number_format($permit['total_charges'] ?? 0, 2); ?></div>
                                                    <div class="text-xs text-gray-500">Total Fees</div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-<?php echo $permit['permit_color'] === 'success' ? 'green' : ($permit['permit_color'] === 'error' ? 'red' : ($permit['permit_color'] === 'warning' ? 'yellow' : 'gray')); ?>-100 text-<?php echo $permit['permit_color'] === 'success' ? 'green' : ($permit['permit_color'] === 'error' ? 'red' : ($permit['permit_color'] === 'warning' ? 'yellow' : 'gray')); ?>-800">
                                                        <i
                                                            class="fas fa-<?php echo $permit['permit_color'] === 'success' ? 'check' : ($permit['permit_color'] === 'error' ? 'times' : 'clock'); ?>-circle mr-1"></i>
                                                        <?php echo $permit['permit_status_text']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="text-sm text-gray-900 font-medium">
                                                        <?php echo date('M d, Y', strtotime($permit['date_filed'])); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo date('h:i A', strtotime($permit['date_filed'])); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Renewals Table -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-teal-50 to-cyan-50 p-4 border-b border-teal-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xl font-bold text-gray-800">
                                    <i class="fas fa-sync-alt text-teal-600 mr-2"></i>
                                    Renewal Reports (<?php echo count($renewalReports); ?>)
                                </h3>
                                <div class="flex gap-2">
                                    <button onclick="printRenewals()"
                                        class="bg-teal-100 hover:bg-teal-200 text-teal-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-print mr-1"></i>Print
                                    </button>
                                    <button onclick="exportRenewals()"
                                        class="bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded-lg font-semibold text-sm transition-all">
                                        <i class="fas fa-download mr-1"></i>Export
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Tenant Information</th>
                                        <th
                                            class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Renewal Details</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Renewal Amount</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Renewal Status</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Payment Status</th>
                                        <th
                                            class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Submitted Date</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($renewalReports)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div
                                                    class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                                                </div>
                                                <p class="text-gray-500 font-medium">No renewal records found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($renewalReports as $renewal): ?>
                                            <tr class="hover:bg-gray-50 transition-colors">
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center">
                                                        <div
                                                            class="w-12 h-12 rounded-full bg-gradient-to-br from-teal-500 to-cyan-500 text-white flex items-center justify-center font-bold text-sm shadow-md">
                                                            <?php echo strtoupper(substr($renewal['tradename'] ?? 'R', 0, 1)); ?>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-bold text-gray-900">
                                                                <?php echo htmlspecialchars($renewal['tradename'] ?? $renewal['username'] ?? 'N/A'); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($renewal['stall_number'] ?? 'N/A'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="space-y-1">
                                                        <div
                                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                            <i class="fas fa-tag mr-1"></i>
                                                            <?php echo ucfirst($renewal['request_type'] ?? 'N/A'); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-600">
                                                            <?php echo ucfirst(str_replace('_', ' ', $renewal['business_type'] ?? 'N/A')); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="text-lg font-bold text-teal-600">
                                                        ₱<?php echo number_format($renewal['total_amount'] ?? 0, 2); ?></div>
                                                    <div class="text-xs text-gray-500">Renewal Fee</div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-<?php echo $renewal['renewal_color'] === 'success' ? 'green' : ($renewal['renewal_color'] === 'error' ? 'red' : ($renewal['renewal_color'] === 'warning' ? 'yellow' : 'gray')); ?>-100 text-<?php echo $renewal['renewal_color'] === 'success' ? 'green' : ($renewal['renewal_color'] === 'error' ? 'red' : ($renewal['renewal_color'] === 'warning' ? 'yellow' : 'gray')); ?>-800">
                                                        <i
                                                            class="fas fa-<?php echo $renewal['renewal_color'] === 'success' ? 'check' : ($renewal['renewal_color'] === 'error' ? 'times' : 'clock'); ?>-circle mr-1"></i>
                                                        <?php echo $renewal['renewal_status_text']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-<?php echo $renewal['payment_status_text'] === 'Paid' ? 'green' : ($renewal['payment_status_text'] === 'Declined' ? 'red' : ($renewal['payment_status_text'] === 'Pending' ? 'yellow' : 'gray')); ?>-100 text-<?php echo $renewal['payment_status_text'] === 'Paid' ? 'green' : ($renewal['payment_status_text'] === 'Declined' ? 'red' : ($renewal['payment_status_text'] === 'Pending' ? 'yellow' : 'gray')); ?>-800">
                                                        <i
                                                            class="fas fa-<?php echo $renewal['payment_status_text'] === 'Paid' ? 'check' : ($renewal['payment_status_text'] === 'Declined' ? 'times' : ($renewal['payment_status_text'] === 'Pending' ? 'clock' : 'minus')); ?>-circle mr-1"></i>
                                                        <?php echo $renewal['payment_status_text']; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <div class="text-sm text-gray-900 font-medium">
                                                        <?php echo date('M d, Y', strtotime($renewal['submitted_at'])); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo date('h:i A', strtotime($renewal['submitted_at'])); ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Declined Applications Tab -->
            <div id="declinedTab" class="history-tab-content hidden p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-times-circle text-red-600 mr-2"></i>
                        Declined Applications (<?php echo count($declinedApplications); ?>)
                    </h3>
                </div>

                <?php if (empty($declinedApplications)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No declined applications</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($declinedApplications as $app): ?>
                            <div
                                class="bg-gradient-to-r from-red-50 to-pink-50 rounded-xl p-5 border-l-4 border-red-500 shadow-sm hover:shadow-md transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div
                                                class="w-12 h-12 rounded-full bg-gradient-to-br from-red-600 to-pink-500 text-white flex items-center justify-center font-bold text-lg shadow-lg">
                                                <?php echo strtoupper(substr($app['tradename'] ?? 'T', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-lg text-gray-900">
                                                    <?php echo htmlspecialchars($app['tradename'] ?? 'N/A'); ?>
                                                </h4>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($app['company_name'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-3">
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i
                                                        class="fas fa-briefcase mr-1"></i>Business Type</p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars(ucfirst($app['business_type'] ?? 'N/A')); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-envelope mr-1"></i>Email
                                                </p>
                                                <p class="font-semibold text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i
                                                        class="fas fa-calendar mr-1"></i>Declined Date</p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo date('M d, Y', strtotime($app['declined_date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <span
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-full font-semibold text-sm">
                                            <i class="fas fa-times-circle"></i> Declined
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Paid Tenants Tab -->
            <div id="paidTab" class="history-tab-content hidden p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-money-bill-wave text-emerald-600 mr-2"></i>
                        Paid Tenants (<?php echo count($paidHistory); ?>)
                    </h3>
                </div>

                <?php if (empty($paidHistory)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No payment history yet</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($paidHistory as $payment): ?>
                            <div
                                class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-xl p-5 border-l-4 border-emerald-500 shadow-sm hover:shadow-md transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div
                                                class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-600 to-teal-500 text-white flex items-center justify-center font-bold text-lg shadow-lg">
                                                <?php echo strtoupper(substr($payment['tradename'] ?? 'T', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-lg text-gray-900">
                                                    <?php echo htmlspecialchars($payment['tradename'] ?? $payment['username'] ?? 'N/A'); ?>
                                                </h4>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($payment['company_name'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3">
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-store mr-1"></i>Stall</p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($payment['stall_number'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-envelope mr-1"></i>Email
                                                </p>
                                                <p class="font-semibold text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($payment['email'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-phone mr-1"></i>Mobile
                                                </p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($payment['mobile'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i
                                                        class="fas fa-calendar mr-1"></i>Payment Date</p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <span
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-100 text-emerald-700 rounded-full font-semibold text-sm">
                                            <i class="fas fa-check-circle"></i> Paid
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Unpaid Tenants Tab -->
            <div id="unpaidTab" class="history-tab-content hidden p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-800">
                        <i class="fas fa-exclamation-triangle text-orange-600 mr-2"></i>
                        Unpaid Tenants (<?php echo count($unpaidHistory); ?>)
                    </h3>
                </div>

                <?php if (empty($unpaidHistory)): ?>
                    <div class="text-center py-12">
                        <div class="w-20 h-20 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-inbox text-gray-400 text-3xl"></i>
                        </div>
                        <p class="text-gray-500 font-medium">All tenants have paid!</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($unpaidHistory as $tenant): ?>
                            <div
                                class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-xl p-5 border-l-4 border-orange-500 shadow-sm hover:shadow-md transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div
                                                class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-600 to-yellow-500 text-white flex items-center justify-center font-bold text-lg shadow-lg">
                                                <?php echo strtoupper(substr($tenant['tradename'] ?? 'T', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-lg text-gray-900">
                                                    <?php echo htmlspecialchars($tenant['tradename'] ?? 'N/A'); ?>
                                                </h4>
                                                <p class="text-sm text-gray-600">
                                                    <?php echo htmlspecialchars($tenant['company_name'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3">
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-store mr-1"></i>Stall</p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($tenant['stall_number'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i
                                                        class="fas fa-peso-sign mr-1"></i>Monthly Rate</p>
                                                <p class="font-semibold text-gray-800">
                                                    ₱<?php echo number_format($tenant['monthly_rate'] ?? 0, 2); ?></p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-envelope mr-1"></i>Email
                                                </p>
                                                <p class="font-semibold text-gray-800 text-sm">
                                                    <?php echo htmlspecialchars($tenant['email'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 mb-1"><i class="fas fa-phone mr-1"></i>Mobile
                                                </p>
                                                <p class="font-semibold text-gray-800">
                                                    <?php echo htmlspecialchars($tenant['mobile'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-3 ml-4">
                                        <span
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-orange-100 text-orange-700 rounded-full font-semibold text-sm">
                                            <i class="fas fa-exclamation-triangle"></i> Unpaid
                                        </span>
                                        <button
                                            onclick="sendDueDateReminder(this, <?php echo $tenant['user_id']; ?>, <?php echo $tenant['monthly_rate'] ?? 0; ?>)"
                                            class="bg-orange-500 hover:bg-orange-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm hover:shadow-md flex items-center gap-2">
                                            <i class="fas fa-paper-plane text-xs"></i> Send Reminder
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

    </div>
    </section>

    <!-- SOA List Modal -->
    <div id="soaListModal"
        class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div
            class="bg-white rounded-3xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden animate-fade-in flex flex-col border border-gray-100">
            <!-- Modal Header -->
            <div
                class="bg-gradient-to-r from-blue-700 to-indigo-800 px-8 py-6 flex items-center justify-between shadow-lg relative overflow-hidden flex-shrink-0">
                <div class="absolute inset-0 bg-white/10 opacity-20 pointer-events-none"></div>
                <div class="relative z-10 flex items-center gap-4">
                    <div
                        class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center border border-white/30 shadow-inner">
                        <i class="fas fa-file-invoice text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-white font-black text-xl tracking-tight">Statement of Account</h1>
                        <p class="text-blue-100/80 text-sm font-medium">Generate and manage tenant Statement of Accounts
                            (SOA)</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 relative z-10">
                    <div
                        class="bg-white/20 backdrop-blur-md text-white px-4 py-2 rounded-xl font-semibold border border-white/30">
                        <i class="fas fa-users mr-2"></i>
                        <?php echo count($allTenants); ?> Active Tenants
                    </div>
                    <button type="button" onclick="closeSOAListModal()"
                        class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-all border border-white/20 hover:scale-110">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto w-full">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-6 py-4 text-left font-bold text-gray-800 uppercase text-xs tracking-wider">
                                Tenant</th>
                            <th class="px-6 py-4 text-left font-bold text-gray-800 uppercase text-xs tracking-wider">
                                Business Name</th>
                            <th class="px-6 py-4 text-center font-bold text-gray-800 uppercase text-xs tracking-wider">
                                Stall</th>
                            <th class="px-6 py-4 text-center font-bold text-gray-800 uppercase text-xs tracking-wider">
                                Rate</th>
                            <th class="px-6 py-4 text-center font-bold text-gray-800 uppercase text-xs tracking-wider">
                                Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (empty($allTenants)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div
                                        class="w-20 h-20 mx-auto mb-4 bg-gray-50 rounded-full flex items-center justify-center border border-gray-100">
                                        <i class="fas fa-inbox text-gray-300 text-3xl"></i>
                                    </div>
                                    <p class="text-gray-500 font-medium text-lg">No active tenants found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allTenants as $t): ?>
                                <tr class="hover:bg-blue-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-lg flex items-center justify-center text-white font-bold shadow-md">
                                                <?php echo strtoupper(substr($t['tradename'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900">
                                                    <?php echo htmlspecialchars($t['tradename']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($t['email']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($t['company_name']); ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="inline-flex items-center px-3 py-1 bg-purple-50 text-purple-700 border border-purple-200 rounded-lg text-xs font-bold">
                                            <i class="fas fa-store mr-1.5 opacity-70"></i>
                                            <?php echo htmlspecialchars($t['stall_number'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span
                                            class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-black">
                                            ₱<?php echo number_format($t['monthly_rate'] ?? 0, 2); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button onclick="openSOAModal(<?php echo htmlspecialchars(json_encode($t)); ?>)"
                                            class="px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all font-bold flex items-center gap-2 mx-auto shadow-md shadow-blue-500/20 hover:shadow-lg hover:-translate-y-0.5">
                                            <i class="fas fa-file-invoice text-sm opacity-90"></i>
                                            Generate SOA
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <section id="extendedBIRSection" class="hidden space-y-6 animate-fade-in">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="font-bold text-3xl text-gray-900">Extended BIR Submissions</h1>
                <p class="text-gray-600 mt-1">Review and manage Extended BIR document submissions</p>
            </div>
            <div class="bg-purple-100 text-purple-700 px-4 py-2 rounded-xl font-semibold">
                <i class="fas fa-file-invoice mr-2"></i>
                <?php echo count($birSubmissions); ?> Submissions
            </div>
        </div>

        <?php if ($birSubmissions): ?>
            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($birSubmissions as $bir): ?>
                    <?php
                    $statusColor = $bir['status'] === 'approved' ? 'emerald' :
                        ($bir['status'] === 'declined' ? 'red' : 'amber');
                    $statusIcon = $bir['status'] === 'approved' ? 'check-circle' :
                        ($bir['status'] === 'declined' ? 'times-circle' : 'clock');
                    ?>
                    <div class="gradient-card rounded-2xl shadow-lg overflow-hidden hover-lift border border-gray-100">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div
                                            class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center text-white font-bold text-lg">
                                            <?php echo strtoupper(substr($bir['tradename'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-bold text-gray-900">
                                                <?php echo htmlspecialchars($bir['tradename']); ?>
                                            </h3>
                                            <p class="text-sm text-gray-500">
                                                <i class="fas fa-hashtag mr-1"></i>
                                                <?php if ($bir['renewal_id']): ?>
                                                    Renewal ID: <?php echo $bir['renewal_id']; ?>
                                                <?php else: ?>
                                                    <span class="text-indigo-600 font-semibold italic">Standalone BIR Update</span>
                                                <?php endif; ?>
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-calendar mr-1"></i>
                                                Submitted: <?php echo date('M d, Y', strtotime($bir['submitted_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <span
                                    class="px-3 py-1 rounded-full text-sm font-semibold bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-700">
                                    <i class="fas fa-<?php echo $statusIcon; ?> mr-1"></i><?php echo ucfirst($bir['status']); ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="bg-gray-50 p-3 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-envelope mr-1"></i>Email</p>
                                    <p class="font-semibold text-gray-900 text-sm truncate">
                                        <?php echo htmlspecialchars($bir['email']); ?>
                                    </p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-phone mr-1"></i>Mobile</p>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($bir['mobile']); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-money-bill-wave mr-1"></i>Renewal
                                        Amount</p>
                                    <p class="font-semibold text-emerald-600">
                                        <?php echo $bir['total_amount'] ? '₱' . number_format($bir['total_amount'], 2) : 'N/A'; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Payment Proof -->
                            <?php if (!empty($bir['payment_proof'])): ?>
                                <div class="mb-4 p-4 bg-gradient-to-br from-blue-50 to-cyan-50 border border-blue-200 rounded-xl">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-receipt text-white"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900">Payment Proof</p>
                                                <p class="text-sm text-gray-600">Click to view payment receipt</p>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($bir['payment_proof']); ?>" target="_blank"
                                            class="bg-white hover:bg-gray-50 text-blue-600 px-4 py-2 rounded-xl transition-all flex items-center gap-2 font-medium shadow-sm border border-blue-200">
                                            <i class="fas fa-external-link-alt"></i>
                                            <span>View Payment</span>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- BIR Document -->
                            <div
                                class="mb-4 p-4 bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-200 rounded-xl">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-purple-500 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-file-pdf text-white"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900">Extended BIR Document</p>
                                            <p class="text-sm text-gray-600">Click to view document</p>
                                        </div>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($bir['bir_document']); ?>" target="_blank"
                                        class="bg-white hover:bg-gray-50 text-purple-600 px-4 py-2 rounded-xl transition-all flex items-center gap-2 font-medium shadow-sm border border-purple-200">
                                        <i class="fas fa-external-link-alt"></i>
                                        <span>View Document</span>
                                    </a>
                                </div>
                            </div>

                            <?php if ($bir['admin_notes']): ?>
                                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                                    <p class="text-sm text-blue-700">
                                        <i class="fas fa-sticky-note mr-2"></i>
                                        <strong>Admin Notes:</strong> <?php echo htmlspecialchars($bir['admin_notes']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($bir['status'] === 'pending'): ?>
                                <div class="flex gap-3 pt-4 border-t border-gray-200">
                                    <button
                                        onclick='openBIRApprovalModal(<?php echo json_encode(["id" => $bir["id"], "tradename" => addslashes($bir["tradename"])]); ?>)'
                                        class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 font-semibold shadow-md">
                                        <i class="fas fa-check-circle text-lg"></i>
                                        <span>Approve</span>
                                    </button>
                                    <a href="bir_approval.php?id=<?php echo $bir['id']; ?>&action=decline"
                                        class="flex-1 bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-xl transition-all flex items-center justify-center gap-2 font-semibold shadow-md">
                                        <i class="fas fa-times-circle text-lg"></i>
                                        <span>Decline</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="gradient-card rounded-3xl shadow-lg p-12 text-center">
                <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-file-invoice text-5xl text-gray-400"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">No BIR Submissions</h2>
                <p class="text-gray-600">Extended BIR document submissions will appear here for review.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Applicant History moved to modal -->

    </main>
    </div>

    <!-- Applicant History Modal -->
    <div id="applicantHistoryModal" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4"
        onclick="if(event.target===this)closeApplicantHistoryModal()">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl max-h-[90vh] flex flex-col animate-fade-in">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-8 py-5 border-b border-gray-100 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-emerald-500 flex items-center justify-center">
                        <i class="fas fa-history text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">Applicant History</h2>
                        <p class="text-xs text-gray-400">Submission attempts and status per applicant</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex flex-col">
                        <label class="text-gray-400 text-[10px] font-bold uppercase mb-1">Filter by Year</label>
                        <select id="applicantHistoryYearSelector"
                            class="bg-gray-50 text-gray-900 rounded-xl px-4 py-2 border-0 focus:ring-2 focus:ring-blue-300 outline-none font-semibold text-xs shadow-inner min-w-[120px]">
                            <option value="all">All Years</option>
                            <?php foreach ($applicantYears as $yr): ?>
                                <option value="<?php echo $yr; ?>"><?php echo $yr; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <span
                        class="bg-blue-50 border border-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-semibold"
                        id="applicantHistoryCountBadge">
                        <i class="fas fa-users mr-1"></i>
                        <?php echo count($applicantHistory); ?>
                        Applicant<?php echo count($applicantHistory) !== 1 ? 's' : ''; ?>
                    </span>
                    <button onclick="closeApplicantHistoryModal()"
                        class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                        <i class="fas fa-times text-gray-500"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="overflow-y-auto flex-1 px-8 py-6 space-y-5">

                <?php if ($applicantHistory):
                    $totalAttempts = 0;
                    $totalApproved = 0;
                    $totalDeclined = 0;
                    $totalPending = 0;
                    foreach ($applicantHistory as $r) {
                        $totalAttempts += (int) ($r['submission_count'] ?? 1);
                        $totalApproved += (int) $r['approved_count'];
                        $totalDeclined += (int) $r['declined_count'];
                        $totalPending += (int) $r['pending_count'];
                    }
                    ?>

                    <!-- Summary Stats -->
                    <div class="grid grid-cols-4 gap-3">
                        <div
                            class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-3 text-white flex items-center gap-3">
                            <i class="fas fa-paper-plane text-xl text-blue-200"></i>
                            <div>
                                <p class="text-xs text-blue-100 font-semibold uppercase tracking-wide">Total Attempts</p>
                                <p class="text-2xl font-bold" id="applicantStatTotalAttempts"><?php echo $totalAttempts; ?>
                                </p>
                            </div>
                        </div>
                        <div
                            class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-3 text-white flex items-center gap-3">
                            <i class="fas fa-check-circle text-xl text-emerald-200"></i>
                            <div>
                                <p class="text-xs text-emerald-100 font-semibold uppercase tracking-wide">Approved</p>
                                <p class="text-2xl font-bold" id="applicantStatApproved"><?php echo $totalApproved; ?></p>
                            </div>
                        </div>
                        <div
                            class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-3 text-white flex items-center gap-3">
                            <i class="fas fa-times-circle text-xl text-red-200"></i>
                            <div>
                                <p class="text-xs text-red-100 font-semibold uppercase tracking-wide">Declined</p>
                                <p class="text-2xl font-bold" id="applicantStatDeclined"><?php echo $totalDeclined; ?></p>
                            </div>
                        </div>
                        <div
                            class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-3 text-white flex items-center gap-3">
                            <i class="fas fa-clock text-xl text-amber-200"></i>
                            <div>
                                <p class="text-xs text-amber-100 font-semibold uppercase tracking-wide">Pending</p>
                                <p class="text-2xl font-bold" id="applicantStatPending"><?php echo $totalPending; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
                        <table class="w-full table-modern">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">
                                        Applicant</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">
                                        Business Type</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">
                                        Attempts</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">
                                        Status</th>
                                    <th
                                        class="px-4 py-3 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">
                                        Date Applied</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100" id="applicantHistoryTableBody">
                                <?php foreach ($applicantHistory as $applicant):
                                    $attempts = (int) ($applicant['submission_count'] ?? 1);
                                    $approved = (int) $applicant['approved_count'];
                                    $declined = (int) $applicant['declined_count'];
                                    if ($approved > 0) {
                                        $badgeBg = '#d1fae5';
                                        $badgeText = '#065f46';
                                        $statusLabel = 'Approved';
                                        $statusIcon = 'fa-check-circle';
                                    } elseif ($declined > 0) {
                                        $badgeBg = '#fee2e2';
                                        $badgeText = '#991b1b';
                                        $statusLabel = 'Declined';
                                        $statusIcon = 'fa-times-circle';
                                    } else {
                                        $badgeBg = '#fef3c7';
                                        $badgeText = '#92400e';
                                        $statusLabel = 'Pending';
                                        $statusIcon = 'fa-clock';
                                    }
                                    ?>
                                    <?php
                                    $histName = htmlspecialchars(addslashes($applicant['tradename'] ?? $applicant['username'] ?? 'Unknown'));
                                    $histEmail = htmlspecialchars(addslashes($applicant['user_email'] ?? ''));
                                    $histBiz = htmlspecialchars(addslashes(ucfirst($applicant['business_type'] ?? '—')));
                                    $histDate = date('M d, Y', strtotime($applicant['first_application_date']));
                                    ?>
                                    <tr class="hover:bg-indigo-50 transition-colors cursor-pointer"
                                        onclick="openHistoryFromData(<?php echo $applicant['id']; ?>,'<?php echo addslashes($histName); ?>','<?php echo addslashes($histEmail); ?>','<?php echo addslashes($histBiz); ?>',<?php echo $attempts; ?>,'<?php echo addslashes($statusLabel); ?>','<?php echo addslashes($histDate); ?>')">
                                        <td class="px-6 py-3">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-400 to-blue-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                                    <?php echo strtoupper(substr($applicant['tradename'] ?? $applicant['username'] ?? 'U', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <p class="font-semibold text-gray-900 text-sm">
                                                        <?php echo htmlspecialchars($applicant['tradename'] ?? $applicant['username'] ?? 'Unknown'); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-400">
                                                        <?php echo htmlspecialchars($applicant['user_email'] ?? ''); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full"><?php echo htmlspecialchars(ucfirst($applicant['business_type'] ?? '—')); ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold text-sm"><?php echo $attempts; ?></span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                                style="background:<?php echo $badgeBg; ?>;color:<?php echo $badgeText; ?>">
                                                <i class="fas <?php echo $statusIcon; ?>"></i>
                                                <?php echo $statusLabel; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($applicant['first_application_date'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <div class="text-center py-16">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-history text-4xl text-gray-300"></i>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800 mb-1">No Application History</h3>
                        <p class="text-gray-400 text-sm">History will appear here once tenants submit applications.</p>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Edit Contract Modal -->
    <div id="editContractModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-8 animate-fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-3xl font-bold text-gray-900" style="font-family: 'Poppins', sans-serif;">
                        <i class="fas fa-file-contract text-purple-600 mr-3"></i>Adjust Lease Duration
                    </h2>
                    <p class="text-gray-600 mt-1" id="modalTenantName"></p>
                </div>
                <button onclick="closeEditContractModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <form id="editContractForm" method="post" action="update_contract.php" class="space-y-5">
                <input type="hidden" name="tenant_id" id="modalTenantId">

                <div>
                    <label for="lease_start_date"
                        class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-calendar-check text-emerald-600"></i>
                        Contract Start Date
                    </label>
                    <input type="date" id="lease_start_date" name="lease_start_date" required
                        class="w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium" />
                </div>

                <div>
                    <label for="lease_expiration_date"
                        class="block text-sm font-bold text-gray-800 mb-2 flex items-center gap-2">
                        <i class="fas fa-calendar-times text-red-600"></i>
                        Contract End Date
                    </label>
                    <input type="date" id="lease_expiration_date" name="lease_expiration_date" required
                        class="w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3.5 text-gray-900 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition-all font-medium" />
                </div>

                <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-600 text-xl mt-0.5"></i>
                        <div class="text-sm text-blue-700">
                            <p class="font-bold mb-1">Quick Actions:</p>
                            <div class="flex gap-2 mt-2">
                                <button type="button" onclick="extendContract(6)"
                                    class="px-3 py-1 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-xs font-medium">
                                    +6 Months
                                </button>
                                <button type="button" onclick="extendContract(12)"
                                    class="px-3 py-1 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-xs font-medium">
                                    +1 Year
                                </button>
                                <button type="button" onclick="extendContract(24)"
                                    class="px-3 py-1 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors text-xs font-medium">
                                    +2 Years
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="closeEditContractModal()"
                        class="flex-1 px-6 py-4 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition-colors font-bold">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 px-6 py-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl hover:from-purple-600 hover:to-purple-700 transition-all font-bold shadow-lg">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleDropdown(element) {
            const group = element.closest('.nav-group');
            const dropdown = group.querySelector('.nav-dropdown');

            // Close other dropdowns (optional, but usually better UX)
            document.querySelectorAll('.nav-group').forEach(otherGroup => {
                if (otherGroup !== group) {
                    otherGroup.classList.remove('active');
                    const otherTrigger = otherGroup.querySelector('.dropdown-trigger');
                    const otherDropdown = otherGroup.querySelector('.nav-dropdown');
                    if (otherTrigger) otherTrigger.classList.remove('active');
                    if (otherDropdown) otherDropdown.classList.remove('active');
                }
            });

            // Toggle current dropdown
            group.classList.toggle('active');
            element.classList.toggle('active');
            dropdown.classList.toggle('active');
        }

        const viewDashboardLink = document.getElementById('viewDashboardLink');
        const viewTenantsLink = document.getElementById('viewTenantsLink');
        const viewApplicationsLink = document.getElementById('viewApplicationsLink');
        const viewRenewalLink = document.getElementById('viewRenewalLink');
        const viewPaymentsLink = document.getElementById('viewPaymentsLink');
        const viewBillingLink = document.getElementById('viewBillingLink');
        const viewSOALink = document.getElementById('viewSOALink');
        const viewContractsLink = document.getElementById('viewContractsLink');
        const viewExtendedBIRLink = document.getElementById('viewExtendedBIRLink');
        const viewApplicantHistoryLink = document.getElementById('viewApplicantHistoryLink');

        const dashboardSection = document.getElementById('dashboardSection');
        const tenantsSection = document.getElementById('tenantsSection');
        const applicationsSection = document.getElementById('applicationsSection');
        const renewalSection = document.getElementById('renewalSection');
        const paymentsSection = document.getElementById('paymentsSection');
        const billingSection = document.getElementById('billingSection');
        const soaSection = document.getElementById('soaSection');
        const contractsSection = document.getElementById('contractsSection');
        const extendedBIRSection = document.getElementById('extendedBIRSection');
        const applicantHistorySection = document.getElementById('applicantHistorySection');
        const historySection = document.getElementById('historySection');
        const maintenanceSection = document.getElementById('maintenanceSection');
        const sectionTitle = document.getElementById('sectionTitle');

        function hideAllSections() {
            if (dashboardSection) dashboardSection.classList.add('hidden');
            if (tenantsSection) tenantsSection.classList.add('hidden');
            if (applicationsSection) applicationsSection.classList.add('hidden');
            if (renewalSection) renewalSection.classList.add('hidden');
            if (paymentsSection) paymentsSection.classList.add('hidden');
            if (billingSection) billingSection.classList.add('hidden');
            if (contractsSection) contractsSection.classList.add('hidden');
            if (extendedBIRSection) extendedBIRSection.classList.add('hidden');
            if (applicantHistorySection) applicantHistorySection.classList.add('hidden');
            if (historySection) historySection.classList.add('hidden');
            if (maintenanceSection) maintenanceSection.classList.add('hidden');
        }

        const mainContent = document.getElementById('mainContent');

        viewApplicantHistoryLink?.addEventListener('click', () => {
            document.getElementById('applicantHistoryModal')?.classList.remove('hidden');
        });

        viewDashboardLink?.addEventListener('click', () => {
            hideAllSections();
            if (dashboardSection) dashboardSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Dashboard';
            if (mainContent) mainContent.scrollTop = 0;
        });

        viewTenantsLink?.addEventListener('click', () => {
            hideAllSections();
            if (tenantsSection) tenantsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'All Tenants';
            if (mainContent) mainContent.scrollTop = 0;
        });

        viewApplicationsLink?.addEventListener('click', () => {
            hideAllSections();
            if (applicationsSection) applicationsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Application Submissions';
            if (mainContent) mainContent.scrollTop = 0;
        });

        viewRenewalLink?.addEventListener('click', () => {
            hideAllSections();
            if (renewalSection) renewalSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Renewal Requests';
            if (mainContent) mainContent.scrollTop = 0;
        });

        viewPaymentsLink?.addEventListener('click', () => {
            hideAllSections();
            if (paymentsSection) paymentsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Payments';
            if (mainContent) mainContent.scrollTop = 0;
        });

        viewBillingLink?.addEventListener('click', (e) => {
            e.preventDefault();
            if (typeof openBillingModal === 'function') openBillingModal();
        });

        function openSOAListModal() {
            const modal = document.getElementById('soaListModal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeSOAListModal() {
            const modal = document.getElementById('soaListModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        viewSOALink?.addEventListener('click', (e) => {
            e.preventDefault();
            openSOAListModal();
        });

        viewContractsLink?.addEventListener('click', () => {
            hideAllSections();
            if (contractsSection) contractsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Active Contracts';
            if (mainContent) mainContent.scrollTop = 0;
        });

        const viewHistoryLink = document.getElementById('viewHistoryLink');

        viewHistoryLink?.addEventListener('click', () => {
            if (typeof openReportsModal === 'function') openReportsModal();
        });

        // History tab switching
        const historyTabs = document.querySelectorAll('.history-tab');
        const historyTabContents = document.querySelectorAll('.history-tab-content');

        historyTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.getAttribute('data-tab');

                // Remove active state from all tabs
                historyTabs.forEach(t => {
                    t.classList.remove('border-b-2', 'border-blue-500', 'bg-blue-50');
                    t.classList.add('border-transparent');
                });

                // Add active state to clicked tab
                tab.classList.remove('border-transparent');
                tab.classList.add('border-b-2');

                if (tabName === 'approved') {
                    tab.classList.add('border-emerald-500', 'bg-emerald-50');
                } else if (tabName === 'declined') {
                    tab.classList.add('border-red-500', 'bg-red-50');
                } else if (tabName === 'paid') {
                    tab.classList.add('border-emerald-500', 'bg-emerald-50');
                } else if (tabName === 'unpaid') {
                    tab.classList.add('border-orange-500', 'bg-orange-50');
                }

                // Hide all tab contents
                historyTabContents.forEach(content => {
                    content.classList.add('hidden');
                });

                // Show selected tab content
                document.getElementById(tabName + 'Tab').classList.remove('hidden');
            });
        });

        // Set default active tab (approved)
        if (historyTabs.length > 0) {
            historyTabs[0].click();
        }

        viewExtendedBIRLink?.addEventListener('click', () => {
            hideAllSections();
            if (extendedBIRSection) extendedBIRSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Extended BIR Submissions';
        });

        // Add click event listener for total tenants box
        const totalTenantsBox = document.getElementById('totalTenantsBox');
        totalTenantsBox?.addEventListener('click', () => {
            hideAllSections();
            if (applicationsSection) applicationsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Tenant Representatives';
        });

        // Unified Chart initialization moved to end of file to ensure all data is available
    </script>
    <script>
        // Toggle tenant details
        function toggleTenantDetails(index) {
            const detailsDiv = document.getElementById('tenant-details-' + index);
            const chevron = document.getElementById('chevron-' + index);

            if (detailsDiv.classList.contains('hidden')) {
                detailsDiv.classList.remove('hidden');
                chevron.classList.remove('fa-chevron-down');
                chevron.classList.add('fa-chevron-up');
            } else {
                detailsDiv.classList.add('hidden');
                chevron.classList.remove('fa-chevron-up');
                chevron.classList.add('fa-chevron-down');
            }
        }

        // Set current date
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });

        // Quick action buttons
        document.getElementById('quickApplications')?.addEventListener('click', () => {
            hideAllSections();
            if (applicationsSection) applicationsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Application Submissions';
        });

        document.getElementById('quickPayments')?.addEventListener('click', () => {
            hideAllSections();
            if (paymentsSection) paymentsSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Payments';
        });

        document.getElementById('quickMaintenance')?.addEventListener('click', () => {
            hideAllSections();
            if (maintenanceSection) maintenanceSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Maintenance Requests';
        });

        document.getElementById('quickBilling')?.addEventListener('click', () => {
            hideAllSections();
            if (billingSection) billingSection.classList.remove('hidden');
            if (sectionTitle) sectionTitle.textContent = 'Billing';
        });

        // Toggle admin settings menu
        const adminSettingsToggle = document.getElementById('adminSettingsToggle');
        const adminSettingsMenu = document.getElementById('adminSettingsMenu');

        adminSettingsToggle?.addEventListener('click', () => {
            adminSettingsMenu?.classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', (event) => {
            if (adminSettingsToggle && adminSettingsMenu && !adminSettingsToggle.contains(event.target) && !adminSettingsMenu.contains(event.target)) {
                adminSettingsMenu.classList.add('hidden');
            }
        });

        // Applicant History Modal
        function closeApplicantHistoryModal() {
            document.getElementById('applicantHistoryModal').classList.add('hidden');
        }

        // Applicant History Filtering Logic
        function fetchApplicantHistory(year) {
            const tableBody = document.getElementById('applicantHistoryTableBody');
            if (!tableBody) return;

            // Show loading state
            tableBody.style.opacity = '0.5';

            fetch(`admin_dashboard.php?action=get_applicant_history&year=${year}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.style.opacity = '1';
                    if (data.success) {
                        renderApplicantHistory(data.data);
                    } else {
                        console.error('Error fetching history:', data.message);
                    }
                })
                .catch(error => {
                    tableBody.style.opacity = '1';
                    console.error('Error:', error);
                });
        }

        function renderApplicantHistory(history) {
            const tableBody = document.getElementById('applicantHistoryTableBody');
            const countBadge = document.getElementById('applicantHistoryCountBadge');
            const statTotal = document.getElementById('applicantStatTotalAttempts');
            const statApproved = document.getElementById('applicantStatApproved');
            const statDeclined = document.getElementById('applicantStatDeclined');
            const statPending = document.getElementById('applicantStatPending');

            if (!tableBody) return;

            let totalAttempts = 0, totalApproved = 0, totalDeclined = 0, totalPending = 0;

            tableBody.innerHTML = history.length ? history.map(app => {
                const attempts = parseInt(app.submission_count) || 1;
                const approved = parseInt(app.approved_count) || 0;
                const declined = parseInt(app.declined_count) || 0;
                const pending = parseInt(app.pending_count) || 0;

                totalAttempts += attempts;
                totalApproved += approved;
                totalDeclined += declined;
                totalPending += pending;

                let badgeBg, badgeText, statusLabel, statusIcon;
                if (approved > 0) {
                    badgeBg = '#d1fae5'; badgeText = '#065f46'; statusLabel = 'Approved'; statusIcon = 'fa-check-circle';
                } else if (declined > 0) {
                    badgeBg = '#fee2e2'; badgeText = '#991b1b'; statusLabel = 'Declined'; statusIcon = 'fa-times-circle';
                } else {
                    badgeBg = '#fef3c7'; badgeText = '#92400e'; statusLabel = 'Pending'; statusIcon = 'fa-clock';
                }

                const name = (app.tradename || app.username || 'Unknown').replace(/'/g, "\\'");
                const email = (app.user_email || '').replace(/'/g, "\\'");
                const biz = (app.business_type ? app.business_type.charAt(0).toUpperCase() + app.business_type.slice(1) : '—').replace(/'/g, "\\'");
                const dateStr = new Date(app.first_application_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

                return `
                <tr class="hover:bg-indigo-50 transition-colors cursor-pointer"
                    onclick="openHistoryFromData(${app.id},'${name}','${email}','${biz}',${attempts},'${statusLabel}','${dateStr}')">
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-400 to-blue-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                ${(app.tradename || app.username || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">${app.tradename || app.username || 'Unknown'}</p>
                                <p class="text-xs text-gray-400">${app.user_email || ''}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">${app.business_type ? app.business_type.charAt(0).toUpperCase() + app.business_type.slice(1) : '—'}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 text-blue-700 font-bold text-sm">${attempts}</span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold" style="background:${badgeBg};color:${badgeText}">
                            <i class="fas ${statusIcon}"></i>
                            ${statusLabel}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-sm text-gray-500">
                        ${dateStr}
                    </td>
                </tr>
            `;
            }).join('') : `
            <tr>
                <td colspan="5" class="py-10 text-center">
                    <p class="text-gray-400 font-medium">No results found for this year.</p>
                </td>
            </tr>
        `;

            if (countBadge) countBadge.innerHTML = `<i class="fas fa-users mr-1"></i>${history.length} Applicant${history.length !== 1 ? 's' : ''}`;
            if (statTotal) statTotal.textContent = totalAttempts;
            if (statApproved) statApproved.textContent = totalApproved;
            if (statDeclined) statDeclined.textContent = totalDeclined;
            if (statPending) statPending.textContent = totalPending;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const yearSelector = document.getElementById('applicantHistoryYearSelector');
            if (yearSelector) {
                yearSelector.addEventListener('change', function () {
                    fetchApplicantHistory(this.value);
                });
            }
        });

        // Per-Applicant History Modal
        function openHistoryModal(index) {
            const app = applicationsData[index];
            const count = parseInt(app.submission_count) || 1;
            const name = app.tradename || app.company_name || 'Unknown';
            const email = app.email || '';
            const biz = app.business_type ? app.business_type.charAt(0).toUpperCase() + app.business_type.slice(1) : '—';
            const date = app.created_at ? new Date(app.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

            // Status badge
            let statusHTML = '';
            const st = (app.status || '').toLowerCase();
            if (st === 'approved') {
                statusHTML = '<span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#d1fae5;color:#065f46"><i class="fas fa-check-circle mr-1"></i>Approved</span>';
            } else if (st === 'declined') {
                statusHTML = '<span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#fee2e2;color:#991b1b"><i class="fas fa-times-circle mr-1"></i>Declined</span>';
            } else {
                statusHTML = '<span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#fef3c7;color:#92400e"><i class="fas fa-clock mr-1"></i>Pending Review</span>';
            }

            // Attempt dots
            let dotsHTML = '';
            for (let i = 1; i <= count; i++) {
                const isLast = i === count;
                let dotColor = isLast
                    ? (st === 'approved' ? '#10b981' : st === 'declined' ? '#ef4444' : '#f59e0b')
                    : '#ef4444'; // previous attempts = declined/rejected
                dotsHTML += `
          <div style="display:flex;align-items:center;gap:6px;">
            <div style="width:32px;height:32px;border-radius:50%;background:${dotColor};display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700;">${i}</div>
            ${i < count ? '<div style="width:20px;height:2px;background:#e5e7eb;"></div>' : ''}
          </div>`;
            }

            // Populate modal
            document.getElementById('histModalAvatar').innerHTML = `<span class="text-white font-bold text-lg">${name.charAt(0).toUpperCase()}</span>`;
            document.getElementById('histModalName').textContent = name;
            document.getElementById('histModalEmail').textContent = email;
            document.getElementById('histModalCount').textContent = count;
            document.getElementById('histModalDots').innerHTML = dotsHTML;
            document.getElementById('histModalBiz').textContent = biz;
            document.getElementById('histModalStatus').innerHTML = statusHTML;
            document.getElementById('histModalDate').textContent = date;

            document.getElementById('applicantHistoryDetailModal').classList.remove('hidden');
        }

        function closeHistoryModal() {
            document.getElementById('applicantHistoryDetailModal').classList.add('hidden');
        }

        // Called from clickable rows in Applicant History modal table
        function openHistoryFromData(tenantDetailId, name, email, biz, count, statusLabel, date) {
            // Status badge colors
            let statusHTML = '';
            const st = statusLabel.toLowerCase();
            if (st === 'approved') {
                statusHTML = '<span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#d1fae5;color:#065f46"><i class="fas fa-check-circle mr-1"></i>Approved</span>';
            } else if (st === 'declined') {
                statusHTML = '<span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#fee2e2;color:#991b1b"><i class="fas fa-times-circle mr-1"></i>Declined</span>';
            } else {
                statusHTML = '<span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#fef3c7;color:#92400e"><i class="fas fa-clock mr-1"></i>Pending Review</span>';
            }

            // Build attempt dots
            let dotsHTML = '';
            for (let i = 1; i <= count; i++) {
                const isLast = i === count;
                const dotColor = isLast
                    ? (st === 'approved' ? '#10b981' : st === 'declined' ? '#ef4444' : '#f59e0b')
                    : '#ef4444';
                dotsHTML += `<div style="display:flex;align-items:center;gap:6px;">
          <div style="width:32px;height:32px;border-radius:50%;background:${dotColor};display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700;">${i}</div>
          ${i < count ? '<div style="width:20px;height:2px;background:#e5e7eb;"></div>' : ''}
        </div>`;
            }

            // Progress bar
            const progressPercent = Math.min((count / 5) * 100, 100);
            const attemptsLeft = Math.max(5 - count, 0);

            document.getElementById('histModalAvatar').innerHTML = `<span class="text-white font-bold text-lg">${name.charAt(0).toUpperCase()}</span>`;
            document.getElementById('histModalName').textContent = name;
            document.getElementById('histModalEmail').textContent = email;
            document.getElementById('histModalCount').textContent = count;
            document.getElementById('histModalDots').innerHTML = dotsHTML;
            document.getElementById('histModalBiz').textContent = biz;
            document.getElementById('histModalStatus').innerHTML = statusHTML;
            document.getElementById('histModalDate').textContent = date;
            document.getElementById('histModalProgressBar').style.width = progressPercent + '%';
            document.getElementById('histModalAttemptsLeft').textContent = attemptsLeft > 0
                ? attemptsLeft + ' attempt' + (attemptsLeft !== 1 ? 's' : '') + ' remaining'
                : 'No more attempts allowed';

            // Show the modal
            document.getElementById('applicantHistoryDetailModal').classList.remove('hidden');

            // Fetch detailed history timeline
            const timeline = document.getElementById('histModalTimeline');
            timeline.innerHTML = '<p class="text-sm text-gray-400 italic"><i class="fas fa-spinner fa-spin mr-1"></i> Loading history...</p>';

            fetch(`admin_dashboard.php?action=get_applicant_detail_history&tenant_detail_id=${tenantDetailId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        timeline.innerHTML = data.data.map((log, idx) => {
                            const logStatus = (log.status || '').toLowerCase();
                            let icon, color, bgColor, borderColor, label;
                            if (logStatus === 'approved') {
                                icon = 'fa-check-circle'; color = '#065f46'; bgColor = '#d1fae5'; borderColor = '#10b981'; label = 'Approved';
                            } else if (logStatus === 'declined') {
                                icon = 'fa-times-circle'; color = '#991b1b'; bgColor = '#fee2e2'; borderColor = '#ef4444'; label = 'Declined';
                            } else {
                                icon = 'fa-clock'; color = '#92400e'; bgColor = '#fef3c7'; borderColor = '#f59e0b'; label = 'Pending';
                            }
                            const logDate = new Date(log.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                            const feedback = log.feedback ? log.feedback : '<em class="text-gray-400">No feedback</em>';
                            const isLast = idx === data.data.length - 1;

                            return `
                <div class="flex gap-3">
                  <div class="flex flex-col items-center">
                    <div style="width:28px;height:28px;border-radius:50%;background:${bgColor};border:2px solid ${borderColor};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                      <i class="fas ${icon}" style="font-size:12px;color:${color};"></i>
                    </div>
                    ${!isLast ? '<div style="width:2px;flex:1;background:#e5e7eb;min-height:20px;"></div>' : ''}
                  </div>
                  <div class="pb-4 flex-1">
                    <div class="flex items-center gap-2 mb-1">
                      <span class="text-xs font-bold" style="color:${color}">${label}</span>
                      <span class="text-[10px] text-gray-400">${logDate}</span>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-sm text-gray-700">
                      ${feedback}
                    </div>
                  </div>
                </div>
              `;
                        }).join('');
                    } else {
                        timeline.innerHTML = '<p class="text-sm text-gray-400 italic">No detailed history recorded yet. Future actions will appear here.</p>';
                    }
                })
                .catch(err => {
                    console.error('Error fetching history:', err);
                    timeline.innerHTML = '<p class="text-sm text-red-400 italic">Failed to load history.</p>';
                });
        }

        // Edit Contract Modal Functions
        function openEditContractModal(tenantId, tenantName, startDate, endDate) {
            document.getElementById('editContractModal').classList.remove('hidden');
            document.getElementById('modalTenantId').value = tenantId;
            document.getElementById('modalTenantName').textContent = 'Tenant: ' + tenantName;
            document.getElementById('lease_start_date').value = startDate;
            document.getElementById('lease_expiration_date').value = endDate;
        }

        function closeEditContractModal() {
            document.getElementById('editContractModal').classList.add('hidden');
        }

        function extendContract(months) {
            const endDateInput = document.getElementById('lease_expiration_date');
            const currentEndDate = endDateInput.value ? new Date(endDateInput.value) : new Date();

            // Add months to the date
            currentEndDate.setMonth(currentEndDate.getMonth() + months);

            // Format date as YYYY-MM-DD
            const year = currentEndDate.getFullYear();
            const month = String(currentEndDate.getMonth() + 1).padStart(2, '0');
            const day = String(currentEndDate.getDate()).padStart(2, '0');

            endDateInput.value = `${year}-${month}-${day}`;
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeEditContractModal();
            }
        });

        // Payment Action Confirmation Modal Functions
        function confirmPaymentAction(paymentId, status, username) {
            console.log('confirmPaymentAction called:', { paymentId, status, username });

            const modal = document.getElementById('paymentConfirmModal');
            const modalTitle = document.getElementById('confirmModalTitle');
            const modalMessage = document.getElementById('confirmModalMessage');
            const confirmBtn = document.getElementById('confirmPaymentBtn');
            const remarksContainer = document.getElementById('remarksContainer');

            if (!modal || !modalTitle || !modalMessage || !confirmBtn) {
                console.error('Missing modal elements for payment confirmation');
                return;
            }

            if (status === 'approved') {
                modalTitle.innerHTML = '<i class="fas fa-check-circle text-emerald-500 mr-2"></i>Approve Payment?';
                modalMessage.textContent = `Are you sure you want to approve payment from ${username}?`;
                confirmBtn.className = 'px-6 py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl font-semibold transition-all';
                confirmBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Yes, Approve';
                if (remarksContainer) remarksContainer.style.display = 'none';
            } else {
                modalTitle.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-2"></i>Decline Payment?';
                modalMessage.textContent = `Are you sure you want to decline payment from ${username}? Please provide a reason for the decline.`;
                confirmBtn.className = 'px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl font-semibold transition-all';
                confirmBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Yes, Decline';
                if (remarksContainer) remarksContainer.style.display = 'block';
            }

            confirmBtn.onclick = function () {
                const remarksInput = document.getElementById('paymentRemarks');
                const remarks = (status === 'declined' && remarksInput) ? remarksInput.value.trim() : '';
                let url = `payment_approval.php?id=${paymentId}&status=${status}`;
                if (status === 'declined' && remarks) {
                    url += `&remarks=${encodeURIComponent(remarks)}`;
                }
                window.location.href = url;
            };

            modal.classList.remove('hidden');
        }

        function closePaymentConfirmModal() {
            document.getElementById('paymentConfirmModal').classList.add('hidden');
        }

        // Check for success/error messages in session and show toast
        window.addEventListener('DOMContentLoaded', function () {
            <?php if (isset($_SESSION['payment_success'])): ?>
                showToast('success', '<?php echo addslashes($_SESSION['payment_success']);
                unset($_SESSION['payment_success']); ?>');
            <?php endif; ?>

            <?php if (isset($_SESSION['payment_error'])): ?>
                showToast('error', '<?php echo addslashes($_SESSION['payment_error']);
                unset($_SESSION['payment_error']); ?>');
            <?php endif; ?>
        });

        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = 'toast-notification';

            if (type === 'success') {
                toast.innerHTML = `
          <div class="toast-content toast-success">
            <div class="toast-icon">
              <div class="xentro-logo">
                <i class="fas fa-store"></i>
              </div>
            </div>
            <div class="toast-body">
              <div class="toast-header">
                <span class="toast-title">XentroMall</span>
                <span class="toast-badge">Success</span>
              </div>
              <p class="toast-message">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="toast-close">
              <i class="fas fa-times"></i>
            </button>
          </div>
        `;
            } else {
                toast.innerHTML = `
          <div class="toast-content toast-error">
            <div class="toast-icon">
              <div class="xentro-logo error">
                <i class="fas fa-store"></i>
              </div>
            </div>
            <div class="toast-body">
              <div class="toast-header">
                <span class="toast-title">XentroMall</span>
                <span class="toast-badge error">Error</span>
              </div>
              <p class="toast-message">${message}</p>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="toast-close">
              <i class="fas fa-times"></i>
            </button>
          </div>
        `;
            }

            document.body.appendChild(toast);

            // Trigger slide-in animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

            // Auto dismiss after 5 seconds with fade-out
            setTimeout(() => {
                toast.classList.add('fade-out');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 5000);
        }
    </script>

    <!-- Payment Confirmation Modal -->
    <div id="paymentConfirmModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
            <div class="p-8">
                <h2 id="confirmModalTitle" class="text-2xl font-bold text-gray-900 mb-4"></h2>
                <p id="confirmModalMessage" class="text-gray-600 mb-6"></p>

                <div id="remarksContainer" class="mb-6" style="display: none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-comment-dots mr-1"></i>Reason for Decline (will be sent to tenant)
                    </label>
                    <textarea id="paymentRemarks" rows="3"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-all resize-none"
                        placeholder="Please specify the reason for declining this payment..."></textarea>
                </div>

                <div class="flex gap-3">
                    <button onclick="closePaymentConfirmModal()"
                        class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-semibold transition-all">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="confirmPaymentBtn" class="flex-1"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container will be added dynamically -->

    <style>
        /* Toast Notification Styles */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: -400px;
            z-index: 9999;
            transition: right 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        .toast-notification.show {
            right: 20px;
        }

        .toast-notification.fade-out {
            opacity: 0;
            transform: translateX(100px);
            transition: all 0.5s ease-out;
        }

        .toast-content {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            min-width: 350px;
            max-width: 450px;
            border-left: 5px solid;
        }

        .toast-content.toast-success {
            border-left-color: #10b981;
        }

        .toast-content.toast-error {
            border-left-color: #ef4444;
        }

        .toast-icon {
            flex-shrink: 0;
        }

        .xentro-logo {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            animation: pulse-logo 2s infinite;
        }

        .xentro-logo.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        @keyframes pulse-logo {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .toast-body {
            flex: 1;
        }

        .toast-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .toast-title {
            font-weight: 700;
            font-size: 16px;
            color: #1f2937;
            letter-spacing: -0.5px;
        }

        .toast-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .toast-badge.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .toast-message {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.5;
            margin: 0;
        }

        .toast-close {
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: #f3f4f6;
            border: none;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toast-close:hover {
            background: #e5e7eb;
            color: #1f2937;
            transform: scale(1.1);
        }

        /* Progress bar animation */
        .toast-content::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #059669);
            animation: progress 5s linear;
            border-radius: 0 0 0 16px;
        }

        .toast-content.toast-error::after {
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }

        @keyframes progress {
            from {
                width: 100%;
            }

            to {
                width: 0%;
            }
        }
    </style>

    <!-- Application Details Modal -->
    <div id="applicationModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-2xl font-bold text-gray-900">Application Details</h2>
                <button onclick="closeApplicationModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div id="modalContent" class="p-6">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
    </div>

    <!-- Per-Applicant History Modal -->
    <div id="applicantHistoryDetailModal"
        class="hidden fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4"
        onclick="if(event.target===this)closeHistoryModal()">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col animate-fade-in">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-500 flex items-center justify-center"
                        id="histModalAvatar"></div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900" id="histModalName">—</h2>
                        <p class="text-xs text-gray-400" id="histModalEmail">—</p>
                    </div>
                </div>
                <button onclick="closeHistoryModal()"
                    class="w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 px-6 py-6 space-y-5">

                <!-- Attempt Count Big Display -->
                <div class="bg-gradient-to-br from-indigo-500 to-blue-600 rounded-2xl p-5 text-white text-center">
                    <p class="text-sm text-indigo-100 font-semibold uppercase tracking-wider mb-1">Total Submission
                        Attempts</p>
                    <p class="text-5xl font-black" id="histModalCount">1</p>
                    <p class="text-xs text-indigo-200 mt-1">Maximum of 5 attempts allowed</p>
                    <div class="mt-3 bg-white/20 rounded-full h-2 w-full">
                        <div class="bg-white rounded-full h-2 transition-all" id="histModalProgressBar"
                            style="width: 20%"></div>
                    </div>
                    <p class="text-xs text-indigo-200 mt-1" id="histModalAttemptsLeft">4 attempts remaining</p>
                </div>

                <!-- Attempt Dots -->
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-3">Submission Track</p>
                    <div class="flex flex-wrap gap-2" id="histModalDots"></div>
                </div>

                <!-- Details -->
                <div class="bg-gray-50 rounded-2xl p-4 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Business</span>
                        <span class="font-semibold text-gray-900" id="histModalBiz">—</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Current Status</span>
                        <span id="histModalStatus"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Date Applied</span>
                        <span class="font-semibold text-gray-900" id="histModalDate">—</span>
                    </div>
                </div>

                <!-- Detailed History Timeline -->
                <div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-3">
                        <i class="fas fa-stream mr-1"></i> Status History & Decline Reasons
                    </p>
                    <div id="histModalTimeline" class="space-y-0">
                        <p class="text-sm text-gray-400 italic">Loading history...</p>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <div id="confirmModal"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6">
            <div class="text-center">
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-orange-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Confirm Action</h3>
                <p id="confirmMessage" class="text-gray-600 mb-6"></p>
                <div class="flex gap-3 justify-center">
                    <button id="confirmYes"
                        class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg transition-all font-medium">
                        Yes, Proceed
                    </button>
                    <button onclick="closeConfirmModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-all font-medium">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Applications data for modal
        const applicationsData = <?php echo json_encode($applications); ?>;

        function openApplicationModal(index) {
            const app = applicationsData[index];
            console.log('All app fields:', Object.keys(app)); // Debug
            console.log('App ID fields:', { id: app.id, user_id: app.user_id, stall_id: app.stall_id }); // Debug

            // Mark as read if it's an unviewed application
            if (!app.admin_viewed) {
                fetch('mark_application_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(app.id)
                }).then(res => res.json()).then(data => {
                    if (data.success) {
                        app.admin_viewed = 1; // update local data
                        const row = document.getElementById('app-row-' + app.id);
                        const badge = document.getElementById('unread-badge-' + app.id);
                        if (row) row.classList.remove('unread-row');
                        if (badge) badge.remove();
                    }
                }).catch(() => { });
            }

            const modal = document.getElementById('applicationModal');
            const content = document.getElementById('modalContent');

            content.innerHTML = `
        <div class="space-y-6">
          <!-- Header Section -->
          <div class="flex items-start gap-4">
            <div class="w-16 h-16 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-xl flex items-center justify-center text-white font-bold text-xl">
              ${app.tradename ? app.tradename.substring(0, 2).toUpperCase() : 'NA'}
            </div>
            <div class="flex-1">
              <h3 class="text-2xl font-bold text-gray-900">${app.tradename || 'N/A'}</h3>
              <p class="text-gray-500">User ID: ${app.user_id || 'N/A'}</p>
              <p class="text-gray-500">Applied: ${app.created_at ? new Date(app.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
              ${app.approved_stalls_count > 0 ? '<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-black bg-red-100 text-red-800 mt-2 shadow-sm border border-red-200"><span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block;margin-right:8px;box-shadow: 0 0 8px #ef4444;"></span>ADDITIONAL STALL</span>' : ''}
            </div>
          </div>

          <!-- Business Information -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
              <i class="fas fa-building mr-2 text-emerald-600"></i>Business Information
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <p class="text-xs text-gray-500">Company Name</p>
                <p class="font-semibold text-gray-900">${app.company_name || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Business Type</p>
                <p class="font-semibold text-gray-900">${app.business_type || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Trade Name</p>
                <p class="font-semibold text-gray-900">${app.tradename || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">TIN</p>
                <p class="font-semibold text-gray-900">${app.tin || 'N/A'}</p>
              </div>
              <div class="md:col-span-2">
                <p class="text-xs text-gray-500">Business Address</p>
                <p class="font-semibold text-gray-900">${app.business_address || 'N/A'}</p>
              </div>
            </div>
          </div>

          <!-- Stall Information -->
          ${app.stall_number ? `
          <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-xl p-4 border-2 border-purple-200">
            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
              <i class="fas fa-store mr-2 text-purple-600"></i>Stall Information
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <p class="text-xs text-gray-500">Stall Number</p>
                <p class="font-bold text-gray-900">${app.stall_number || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Location</p>
                <p class="font-semibold text-gray-900">${app.stall_location || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Monthly Rate</p>
                <p class="font-bold text-emerald-600">₱${app.monthly_rate ? parseFloat(app.monthly_rate).toFixed(2) : '0.00'}/mo</p>
              </div>
            </div>
          </div>
          ` : ''}

          <!-- Contact Information -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
              <i class="fas fa-phone mr-2 text-blue-600"></i>Contact Information
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <p class="text-xs text-gray-500">Contact Person</p>
                <p class="font-semibold text-gray-900">${app.contact_person || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Position</p>
                <p class="font-semibold text-gray-900">${app.position || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Mobile</p>
                <p class="font-semibold text-gray-900">${app.mobile || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Office Tel</p>
                <p class="font-semibold text-gray-900">${app.office_tel || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Email</p>
                <p class="font-semibold text-gray-900">${app.email || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Tenant Representative</p>
                <p class="font-semibold text-gray-900">${app.tenant_representative || 'N/A'}</p>
              </div>
            </div>
          </div>

          <!-- Additional Details -->
          <div class="bg-gray-50 rounded-xl p-4">
            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
              <i class="fas fa-info-circle mr-2 text-gray-600"></i>Additional Details
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <p class="text-xs text-gray-500">Store Premises</p>
                <p class="font-semibold text-gray-900">${app.store_premises || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Store Location</p>
                <p class="font-semibold text-gray-900">${app.store_location || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Ownership</p>
                <p class="font-semibold text-gray-900">${app.ownership || 'N/A'}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500">Prepared By</p>
                <p class="font-semibold text-gray-900">${app.prepared_by || 'N/A'}</p>
              </div>
            </div>
          </div>

          <!-- Documents Section -->
          <div class="border-t border-gray-200 pt-4">
            <h4 class="font-bold text-gray-900 mb-3 flex items-center">
              <i class="fas fa-folder-open mr-2 text-blue-600"></i>Documents
            </h4>
            ${app.documents ? `
              <a href="view_documents.php?id=${encodeURIComponent(app.id)}" target="_blank" class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-xl transition-all font-medium shadow-sm">
                <i class="fas fa-folder-open"></i>
                <span>View Documents</span>
                <i class="fas fa-external-link-alt text-xs"></i>
              </a>
            ` : `
              <div class="inline-flex items-center gap-2 bg-gray-100 text-gray-500 px-6 py-3 rounded-xl">
                <i class="fas fa-folder-open"></i>
                <span>No Documents</span>
              </div>
            `}
          </div>

          <!-- Action Buttons -->
          <div id="actionButtons-${app.id}" class="flex gap-3 justify-end border-t border-gray-200 pt-6">
            <button onclick="confirmApprove(${app.id})" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-xl transition-all flex items-center gap-2 font-medium shadow-sm">
              <i class="fas fa-check-circle"></i>
              <span>Approve Application</span>
            </button>
            <button onclick="showDeclineForm(${app.id})" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-xl transition-all flex items-center gap-2 font-medium shadow-sm">
              <i class="fas fa-times-circle"></i>
              <span>Decline Application</span>
            </button>
          </div>

          <!-- Decline Form (Hidden by default) -->
          <div id="declineForm-${app.id}" class="hidden border-t border-gray-200 pt-6">
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
              <h4 class="font-bold text-red-800 mb-3 flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>Decline Application
              </h4>
              <div class="mb-4">
                <label class="block text-sm font-medium text-red-700 mb-2">
                  Reason for Declination <span class="text-red-500">*</span>
                </label>
                <textarea 
                  id="declineReason-${app.id}"
                  rows="3"
                  placeholder="Please provide a reason for declining this application..."
                  class="w-full px-3 py-2 border border-red-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"
                ></textarea>
              </div>
              <div class="flex gap-2">
                <button onclick="confirmDecline(${app.id})" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg transition-all text-sm font-medium">
                  <i class="fas fa-times mr-2"></i>Confirm Decline
                </button>
                <button onclick="hideDeclineForm(${app.id})" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-all text-sm font-medium">
                  <i class="fas fa-arrow-left mr-2"></i>Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      `;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeApplicationModal() {
            const modal = document.getElementById('applicationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Close modal when clicking outside
        document.getElementById('applicationModal')?.addEventListener('click', function (e) {
            if (e.target === this) {
                closeApplicationModal();
            }
        });

        function confirmApprove(applicationId) {
            showConfirmModal('Are you sure you want to APPROVE this application?', () => {
                window.location.href = `approval.php?id=${applicationId}&status=approved&remarks=Application approved by admin`;
            });
        }

        function showConfirmModal(message, onConfirm) {
            const modal = document.getElementById('confirmModal');
            const messageElement = document.getElementById('confirmMessage');
            const yesButton = document.getElementById('confirmYes');

            messageElement.textContent = message;

            // Remove old event listener and add new one
            yesButton.replaceWith(yesButton.cloneNode(true));
            const newYesButton = document.getElementById('confirmYes');

            newYesButton?.addEventListener('click', () => {
                onConfirm();
                closeConfirmModal();
            });

            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirmModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function showDeclineForm(applicationId) {
            const declineForm = document.getElementById(`declineForm-${applicationId}`);
            const actionButtons = document.getElementById(`actionButtons-${applicationId}`);

            declineForm.classList.remove('hidden');
            actionButtons.classList.add('hidden');
        }

        function hideDeclineForm(applicationId) {
            const declineForm = document.getElementById(`declineForm-${applicationId}`);
            const actionButtons = document.getElementById(`actionButtons-${applicationId}`);

            declineForm.classList.add('hidden');
            actionButtons.classList.remove('hidden');
        }

        function confirmDecline(applicationId) {
            const reasonTextarea = document.getElementById(`declineReason-${applicationId}`);
            const reason = reasonTextarea.value.trim();

            if (!reason) {
                alert('Please provide a reason for declining this application.');
                reasonTextarea.focus();
                return;
            }

            // Show custom confirmation modal
            showConfirmModal('Are you sure you want to DECLINE this application?', () => {
                window.location.href = `approval.php?id=${applicationId}&status=declined&remarks=${encodeURIComponent(reason)}`;
            });
        }

        // Billing Confirmation Functions
        function confirmSaveBills() {
            const billingMonth = document.querySelector('input[name="billing_month"]').value;
            const billFiles = document.querySelectorAll('input[name^="bill_image"]');

            let hasFiles = false;
            let billCount = 0;
            let affectedTenants = new Set();

            billFiles.forEach((input) => {
                if (input.files && input.files.length > 0) {
                    hasFiles = true;
                    billCount++;
                    const key = input.name.match(/\[(.*?)\]/)[1];
                    affectedTenants.add(key);
                }
            });

            if (!hasFiles) {
                showBillingErrorModal('Please upload at least one bill photo before saving.');
                return;
            }

            const monthYear = new Date(billingMonth + '-01').toLocaleDateString('en-US', { year: 'numeric', month: 'long' });

            showBillingConfirmModal(
                monthYear,
                affectedTenants.size,
                billCount
            );
        }

        function showBillingConfirmModal(monthYear, tenantCount, billCount) {
            // Toggle views inside the existing billing modal
            const formView = document.getElementById('billingModalFormView');
            const confirmView = document.getElementById('billingModalConfirmView');

            if (!formView || !confirmView) return;

            // Update summary data
            document.getElementById('confirmBillingMonth').textContent = 'Billing Period: ' + monthYear;
            document.getElementById('confirmTenantCount').textContent = tenantCount;
            document.getElementById('confirmBillCount').textContent = billCount;

            // Switch view
            formView.classList.add('hidden');
            confirmView.classList.remove('hidden');
        }

        function backToBillingForm() {
            const formView = document.getElementById('billingModalFormView');
            const confirmView = document.getElementById('billingModalConfirmView');

            if (formView && confirmView) {
                confirmView.classList.add('hidden');
                formView.classList.remove('hidden');
            }
        }

        function showBillingErrorModal(message) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[500] p-4 animate-fade-in';
            modal.id = 'billingErrorModal';
            modal.innerHTML = `
        <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl border-4 border-red-500 transform transition-all">
          <div class="bg-red-500 px-8 py-6 flex items-center gap-4">
            <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center text-white shadow-inner">
              <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <h3 class="text-white font-black text-xl uppercase tracking-tighter">Upload Failed</h3>
          </div>
          
          <div class="p-8">
            <div class="bg-red-50 border-2 border-red-100 rounded-2xl p-6 mb-6">
              <p class="text-red-800 font-bold text-center leading-relaxed">
                ${message}
              </p>
            </div>
            
            <button onclick="closeBillingErrorModal()" class="w-full px-8 py-4 bg-red-500 text-white rounded-2xl hover:bg-red-600 transition-all font-black uppercase tracking-widest shadow-lg shadow-red-500/30 transform hover:scale-[1.02]">
              Try Again
            </button>
          </div>
        </div>
      `;
            document.body.appendChild(modal);
        }

        function closeBillingErrorModal() {
            const modal = document.getElementById('billingErrorModal');
            if (modal) modal.remove();
        }

        function showBillingSuccessModal(tenantCount, monthYear) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[500] p-4 animate-fade-in';
            modal.id = 'billingSuccessModal';
            modal.innerHTML = `
        <div class="bg-white rounded-[2rem] w-full max-w-sm overflow-hidden shadow-2xl border-4 border-emerald-500 transform transition-all">
          <div class="bg-emerald-500 px-6 py-8 flex flex-col items-center gap-3 text-center relative overflow-hidden text-white">
            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-white shadow-lg backdrop-blur-sm border border-white/30 mb-1">
              <i class="fas fa-check-circle text-3xl"></i>
            </div>
            <h3 class="font-black text-xl uppercase tracking-tighter leading-none text-white">Upload Success!</h3>
            <p class="text-emerald-50/80 font-bold text-[10px] uppercase tracking-widest">${monthYear} Cycle</p>
          </div>
          
          <div class="p-6">
            <div class="flex items-center justify-around mb-6 bg-gray-50 rounded-2xl p-4 border border-gray-100">
              <div class="text-center">
                <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Units</p>
                <p class="text-2xl font-black text-gray-800">${tenantCount}</p>
              </div>
              <div class="w-px h-8 bg-gray-200"></div>
              <div class="text-center">
                <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest mb-1">Status</p>
                <p class="text-xs font-black text-emerald-600 uppercase">Notified</p>
              </div>
            </div>

            <p class="text-gray-500 text-[11px] font-bold text-center leading-snug mb-6 px-4">
              Tenants have been notified via app & SMS to check their dashboards.
            </p>
            
            <button onclick="closeBillingSuccessModal()" class="w-full px-6 py-4 bg-emerald-500 text-white rounded-2xl hover:bg-emerald-600 transition-all font-black uppercase tracking-widest shadow-lg shadow-emerald-500/20 transform hover:scale-[1.02]">
              Dismiss
            </button>
          </div>
        </div>
      `;
            document.body.appendChild(modal);
        }

        function closeBillingSuccessModal() {
            const modal = document.getElementById('billingSuccessModal');
            if (modal) {
                modal.classList.add('opacity-0', 'scale-95');
                setTimeout(() => {
                    modal.remove();
                    // No reload needed as PRG pattern already handled it
                }, 200);
            }
        }

        function proceedSaveBills(button) {
            const originalText = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

            // Submit the form within the billing modal
            const form = document.querySelector('#billingModal form');
            if (form) {
                form.submit();
            } else {
                // Fallback for older version if any
                document.querySelector('form[method="post"]').submit();
            }
        }

        function openBillingModal() {
            const modal = document.getElementById('billingModal');
            const formView = document.getElementById('billingModalFormView');
            const confirmView = document.getElementById('billingModalConfirmView');

            if (formView && confirmView) {
                formView.classList.remove('hidden');
                confirmView.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden'; // Prevent scroll
        }

        function closeBillingModal() {
            const modal = document.getElementById('billingModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = ''; // Restore scroll
        }

        function updateFileName(input) {
            const label = input.closest('label');
            const span = label.querySelector('span');
            if (input.files && input.files[0]) {
                span.textContent = input.files[0].name;
                span.classList.remove('text-gray-500');
                span.classList.add('text-blue-600', 'font-black');
                label.classList.add('bg-blue-50', 'border-blue-200');
            }
        }

        // Unified Renewal Functions
        function viewDocuments(renewal) {
            // Helper function to check if file is an image
            const isImage = (path) => {
                if (!path) return false;
                const ext = path.split('.').pop().toLowerCase();
                return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
            };

            // Helper function to get clean filename
            const getFileName = (path) => {
                if (!path) return '';
                const parts = path.split('/');
                const fileName = parts[parts.length - 1];
                return fileName.split('_').slice(1).join('_') || fileName;
            };

            // Helper function to render a document card
            const renderDocumentCard = (label, path) => {
                if (!path) return '';

                const fileName = getFileName(path);
                const imagePreview = isImage(path)
                    ? `<img src="${path}" alt="${label}" class="w-full h-32 object-cover rounded-lg mb-2 cursor-pointer hover:opacity-90 transition-opacity" onclick="window.open('${path}', '_blank')">`
                    : `<div class="w-full h-32 bg-gray-100 rounded-lg mb-2 flex flex-col items-center justify-center p-3 text-center cursor-pointer hover:bg-gray-200 transition-colors" onclick="window.open('${path}', '_blank')">
              <i class="fas ${path.toLowerCase().endsWith('.pdf') ? 'fa-file-pdf text-red-500' : 'fa-file text-gray-400'} text-3xl mb-2"></i>
              <span class="text-[10px] font-medium text-gray-500 truncate w-full">${path.split('.').pop().toUpperCase()} FILE</span>
            </div>`;

                return `
          <div class="bg-white border border-gray-100 rounded-xl p-3 shadow-sm hover:shadow-md transition-shadow">
            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">${label}</p>
            ${imagePreview}
            <a href="${path}" target="_blank" class="text-[11px] text-blue-600 hover:text-blue-800 font-medium truncate block" title="${fileName}">
              <i class="fas fa-external-link-alt mr-1"></i>${fileName}
            </a>
          </div>
        `;
            };

            let documentsHtml = '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">';

            // Get documents based on business type
            if (renewal.business_type === 'corporation') {
                documentsHtml += renderDocumentCard('Letter of Intent', renewal.letter_of_intent);
                documentsHtml += renderDocumentCard('Business Profile', renewal.business_profile);
                documentsHtml += renderDocumentCard('SEC Registration', renewal.business_registration);
                documentsHtml += renderDocumentCard('Secretary Certificate', renewal.secretary_certificate);
                documentsHtml += renderDocumentCard('BIR Registration', renewal.bir_registration);
                documentsHtml += renderDocumentCard('Valid ID', renewal.valid_id);
            } else if (renewal.business_type === 'sole_proprietorship') {
                documentsHtml += renderDocumentCard('Letter of Intent', renewal.letter_of_intent_sole);
                documentsHtml += renderDocumentCard('DTI Permit', renewal.business_registration_sole);
                documentsHtml += renderDocumentCard('BIR Registration', renewal.bir_registration_sole);
                documentsHtml += renderDocumentCard('Valid ID 1', renewal.valid_id_sole_1);
                documentsHtml += renderDocumentCard('Valid ID 2', renewal.valid_id_sole_2);
            } else if (renewal.business_type === 'partnership') {
                documentsHtml += renderDocumentCard('Letter of Intent', renewal.letter_of_intent_partner);
                documentsHtml += renderDocumentCard('DTI Permit', renewal.business_registration_partner);
                documentsHtml += renderDocumentCard('BIR Registration', renewal.bir_registration_partner);
                documentsHtml += renderDocumentCard('Partnership Agreement', renewal.financial_statement_partner);
                documentsHtml += renderDocumentCard('Valid ID 1', renewal.valid_id_partner_1);
                documentsHtml += renderDocumentCard('Valid ID 2', renewal.valid_id_partner_2);
            }

            documentsHtml += '</div>';

            showModal('Review Documents', documentsHtml, true);
        }

        function showApprovalModal(renewalId, tenantName, tenantEmail) {
            const modalHtml = `
        <div class="p-4">
          <h3 class="text-lg font-semibold mb-3">Approve Renewal Request</h3>
          <p class="text-gray-600 mb-6">Are you sure you want to approve the renewal request from <strong>${tenantName}</strong>?</p>
          <div class="flex gap-3">
            <button onclick="processRenewal(${renewalId}, 'approve', '${tenantEmail}')" class="flex-1 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors shadow-sm">
              Yes, Approve
            </button>
            <button onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2.5 rounded-lg font-semibold transition-colors">
              Cancel
            </button>
          </div>
        </div>
      `;
            showModal('Confirm Approval', modalHtml);
        }

        function showDeclineModal(renewalId, tenantName, tenantEmail) {
            const modalHtml = `
        <div class="p-4">
          <h3 class="text-lg font-semibold mb-3">Decline Renewal Request</h3>
          <p class="text-gray-600 mb-4">Are you sure you want to decline the renewal request from <strong>${tenantName}</strong>?</p>
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason for Declining</label>
            <textarea id="declineRemarks" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none transition-all" rows="3" placeholder="Please specify why this request is being declined..."></textarea>
          </div>
          <div class="flex gap-3 mt-4">
            <button onclick="processRenewal(${renewalId}, 'decline', '${tenantEmail}')" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2.5 rounded-lg font-semibold transition-colors shadow-sm">
              Yes, Decline
            </button>
            <button onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2.5 rounded-lg font-semibold transition-colors">
              Cancel
            </button>
          </div>
        </div>
      `;
            showModal('Confirm Decline', modalHtml);
        }

        function processRenewal(renewalId, action, tenantEmail) {
            const remarks = action === 'decline' ? document.getElementById('declineRemarks')?.value || '' : '';

            if (action === 'decline' && !remarks.trim()) {
                alert('Please provide a reason for declining.');
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('action', action);
            formData.append('renewal_request_id', renewalId);
            formData.append('admin_feedback', remarks);

            // Submit via AJAX
            fetch('admin_dashboard.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeModal();
                        showSuccessMessage(`Renewal request ${action}d successfully!`);
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
        }

        function showModal(title, content, isLarge = false) {
            const modalHtml = `
        <div id="customModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-[9999] p-4">
          <div class="bg-white rounded-2xl shadow-2xl ${isLarge ? 'max-w-4xl' : 'max-w-lg'} w-full transform transition-all animate-in fade-in zoom-in duration-200">
            <div class="p-6">
              <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-4">
                <h2 class="text-xl font-bold text-gray-900">${title}</h2>
                <button onclick="closeModal()" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div class="max-h-[70vh] overflow-y-auto custom-scrollbar">
                ${content}
              </div>
            </div>
          </div>
        </div>
      `;

            // Remove existing modal if any
            const existingModal = document.getElementById('customModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add new modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function closeModal() {
            const modal = document.getElementById('customModal');
            if (modal) {
                modal.remove();
            }
        }

        function showSuccessMessage(message) {
            const messageHtml = `
        <div id="successMessage" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
          <i class="fas fa-check-circle mr-2"></i>${message}
        </div>
      `;
            document.body.insertAdjacentHTML('beforeend', messageHtml);

            setTimeout(() => {
                const message = document.getElementById('successMessage');
                if (message) message.remove();
            }, 3000);
        }

        // ===== REPORTS FUNCTIONS =====

        // Print Functions
        function printApplications() {
            const content = document.querySelector('.bg-gradient-to-r.from-blue-50.to-indigo-50').parentElement.innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Applications History Report</title>
          <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .bg-green-100 { background-color: #d1fae5; color: #065f46; }
            .bg-red-100 { background-color: #fee2e2; color: #991b1b; }
            .bg-yellow-100 { background-color: #fef3c7; color: #92400e; }
            h1 { color: #1f2937; margin-bottom: 20px; }
          </style>
        </head>
        <body>
          <h1>Applications History Report</h1>
          <p>Generated on: ${new Date().toLocaleString()}</p>
          ${content.replace(/<button[^>]*>.*?<\/button>/g, '')}
        </body>
        </html>
      `);
            printWindow.document.close();
            printWindow.print();
        }

        function printPayments() {
            const content = document.querySelector('.bg-gradient-to-r.from-emerald-50.to-teal-50').parentElement.innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Payment History Report</title>
          <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .bg-green-100 { background-color: #d1fae5; color: #065f46; }
            .bg-red-100 { background-color: #fee2e2; color: #991b1b; }
            .bg-yellow-100 { background-color: #fef3c7; color: #92400e; }
            .bg-gray-100 { background-color: #f3f4f6; color: #374151; }
            h1 { color: #1f2937; margin-bottom: 20px; }
          </style>
        </head>
        <body>
          <h1>Payment History Report</h1>
          <p>Generated on: ${new Date().toLocaleString()}</p>
          ${content.replace(/<button[^>]*>.*?<\/button>/g, '')}
        </body>
        </html>
      `);
            printWindow.document.close();
            printWindow.print();
        }

        // Export Functions
        function exportApplications() {
            const rows = document.querySelectorAll('tbody tr');
            let csv = 'Applicant,Business,Stall,Contact,Status,Date\n';

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const applicant = cells[0].textContent.trim();
                    const business = cells[1].textContent.trim();
                    const stall = cells[2].textContent.trim();
                    const contact = cells[3].textContent.trim();
                    const status = cells[4].textContent.trim();
                    const date = cells[5].textContent.trim();

                    csv += `"${applicant}","${business}","${stall}","${contact}","${status}","${date}"\n`;
                }
            });

            downloadCSV(csv, 'applications_history.csv');
        }

        function exportPayments() {
            const rows = document.querySelectorAll('tbody tr');
            let csv = 'Tenant,Stall,Amount,Payment Type,Method,Status,Date\n';

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const tenant = cells[0].textContent.trim();
                    const stall = cells[1].textContent.trim();
                    const amount = cells[2].textContent.trim();
                    const paymentType = cells[3].textContent.trim();
                    const method = cells[4].textContent.trim();
                    const status = cells[5].textContent.trim();
                    const date = cells[6].textContent.trim();

                    csv += `"${tenant}","${stall}","${amount}","${paymentType}","${method}","${status}","${date}"\n`;
                }
            });

            downloadCSV(csv, 'payment_history.csv');
        }

        function exportWorkPermits() {
            const rows = document.querySelectorAll('tbody tr');
            let csv = 'Permit Number,Tenant,Stall,Scope of Work,Total Charges,Status,Date Filed\n';

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const permitInfo = cells[0].textContent.trim();
                    const tenant = cells[1].textContent.trim();
                    const charges = cells[2].textContent.trim();
                    const status = cells[3].textContent.trim();
                    const date = cells[4].textContent.trim();

                    csv += `"${permitInfo}","${tenant}","${charges}","${status}","${date}"\n`;
                }
            });

            downloadCSV(csv, 'work_permit_reports.csv');
        }

        function exportRenewals() {
            const rows = document.querySelectorAll('tbody tr');
            let csv = 'Tenant,Renewal Type,Business Type,Amount,Renewal Status,Payment Status,Submitted Date\n';

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length > 0) {
                    const tenant = cells[0].textContent.trim();
                    const renewalDetails = cells[1].textContent.trim();
                    const amount = cells[2].textContent.trim();
                    const renewalStatus = cells[3].textContent.trim();
                    const paymentStatus = cells[4].textContent.trim();
                    const date = cells[5].textContent.trim();

                    csv += `"${tenant}","${renewalDetails}","${amount}","${renewalStatus}","${paymentStatus}","${date}"\n`;
                }
            });

            downloadCSV(csv, 'renewal_reports.csv');
        }

        // Print Functions for Work Permits and Renewals
        function printWorkPermits() {
            const content = document.querySelector('.bg-gradient-to-r.from-orange-50.to-amber-50').parentElement.innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Work Permit Reports</title>
          <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .bg-green-100 { background-color: #d1fae5; color: #065f46; }
            .bg-red-100 { background-color: #fee2e2; color: #991b1b; }
            .bg-yellow-100 { background-color: #fef3c7; color: #92400e; }
            h1 { color: #1f2937; margin-bottom: 20px; }
          </style>
        </head>
        <body>
          <h1>Work Permit Reports</h1>
          <p>Generated on: ${new Date().toLocaleString()}</p>
          ${content.replace(/<button[^>]*>.*?<\/button>/g, '')}
        </body>
        </html>
      `);
            printWindow.document.close();
            printWindow.print();
        }

        function printRenewals() {
            const content = document.querySelector('.bg-gradient-to-r.from-teal-50.to-cyan-50').parentElement.innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Renewal Reports</title>
          <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .bg-green-100 { background-color: #d1fae5; color: #065f46; }
            .bg-red-100 { background-color: #fee2e2; color: #991b1b; }
            .bg-yellow-100 { background-color: #fef3c7; color: #92400e; }
            h1 { color: #1f2937; margin-bottom: 20px; }
          </style>
        </head>
        <body>
          <h1>Renewal Reports</h1>
          <p>Generated on: ${new Date().toLocaleString()}</p>
          ${content.replace(/<button[^>]*>.*?<\/button>/g, '')}
        </body>
        </html>
      `);
            printWindow.document.close();
            printWindow.print();
        }

        function toggleAllContracts() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const contractCheckboxes = document.querySelectorAll('.contract-checkbox');

            contractCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        }

        function selectAllContracts() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const contractCheckboxes = document.querySelectorAll('.contract-checkbox');

            // Toggle select all state
            const newState = !selectAllCheckbox.checked;
            selectAllCheckbox.checked = newState;

            contractCheckboxes.forEach(checkbox => {
                checkbox.checked = newState;
            });
        }

        function sendExpirationNotifications() {
            const selectedContracts = document.querySelectorAll('.contract-checkbox:checked');

            if (selectedContracts.length === 0) {
                showNotification('Please select at least one tenant to send notifications.', 'warning');
                return;
            }

            const tenants = [];
            selectedContracts.forEach(checkbox => {
                tenants.push({
                    tenant_id: checkbox.dataset.tenantId,
                    email: checkbox.dataset.email,
                    mobile: checkbox.dataset.mobile,
                    company: checkbox.dataset.company,
                    days_remaining: checkbox.dataset.daysRemaining
                });
            });

            // Show confirmation dialog
            if (confirm(`Send expiration reminder notifications to ${tenants.length} tenant(s)?\n\nNotifications will be sent via:\n• Email\n• SMS\n\nThis will remind tenants about their upcoming contract expiration.`)) {
                // Show loading state
                const sendButton = event.target;
                const originalText = sendButton.innerHTML;
                sendButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending...';
                sendButton.disabled = true;

                // Send notifications via AJAX
                fetch('admin_dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'send_expiration_notifications',
                        'tenants': JSON.stringify(tenants)
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification(`Notifications sent successfully to ${data.email_count} emails and ${data.sms_count} SMS messages.`, 'success');
                            // Clear selections after successful send
                            document.getElementById('selectAllCheckbox').checked = false;
                            document.querySelectorAll('.contract-checkbox').forEach(cb => cb.checked = false);
                        } else {
                            showNotification('Error sending notifications: ' + data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showNotification('Error sending notifications. Please try again.', 'error');
                    })
                    .finally(() => {
                        // Restore button state
                        sendButton.innerHTML = originalText;
                        sendButton.disabled = false;
                    });
            }
        }

        function switchPaymentsTab(tab) {
            const submissionsTab = document.getElementById('tab-payments-submissions');
            const unpaidTab = document.getElementById('tab-payments-unpaid');
            const submissionsView = document.getElementById('paymentsSubmissionsView');
            const unpaidView = document.getElementById('paymentsUnpaidView');

            if (tab === 'submissions') {
                submissionsTab.classList.add('border-blue-600', 'text-blue-600');
                submissionsTab.classList.remove('border-transparent', 'text-gray-500');
                unpaidTab.classList.remove('border-blue-600', 'text-blue-600');
                unpaidTab.classList.add('border-transparent', 'text-gray-500');
                submissionsView.classList.remove('hidden');
                unpaidView.classList.add('hidden');
            } else {
                unpaidTab.classList.add('border-blue-600', 'text-blue-600');
                unpaidTab.classList.remove('border-transparent', 'text-gray-500');
                submissionsTab.classList.remove('border-blue-600', 'text-blue-600');
                submissionsTab.classList.add('border-transparent', 'text-gray-500');
                unpaidView.classList.remove('hidden');
                submissionsView.classList.add('hidden');
            }
        }

        function markNotificationRead(id) {
            const formData = new FormData();
            formData.append('id', id);

            fetch('mark_application_read.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById(`app-row-${id}`);
                    const badge = document.getElementById(`unread-badge-${id}`);
                    if (row) row.classList.remove('unread-row');
                    if (badge) badge.classList.add('hidden');
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transform transition-all duration-300 translate-x-full`;

            // Set color based on type
            const colors = {
                success: 'bg-green-500 text-white',
                error: 'bg-red-500 text-white',
                warning: 'bg-orange-500 text-white',
                info: 'bg-blue-500 text-white'
            };

            notification.className += ' ' + colors[type];
            notification.innerHTML = `
        <div class="flex items-center gap-3">
          <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
          <span>${message}</span>
        </div>
      `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            }, 100);

            // Remove after 5 seconds
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 5000);
        }

        function downloadCSV(csv, filename) {
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', filename);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        // ===== APEXCHARTS INITIALIZATION =====
        document.addEventListener('DOMContentLoaded', function () {
            // Tenant Status Doughnut Chart
            const paidTenants = <?php echo (int) $paidTenants; ?>;
            const unpaidTenants = <?php echo (int) $unpaidTenants; ?>;

            if (document.getElementById('tenantsDoughnut')) {
                const barOptions = {
                    series: [{
                        name: 'Tenants',
                        data: [paidTenants, unpaidTenants]
                    }],
                    chart: {
                        type: 'bar',
                        height: 250,
                        fontFamily: 'Inter, sans-serif',
                        toolbar: { show: false }
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 6,
                            horizontal: false,
                            columnWidth: '55%',
                            distributed: true
                        }
                    },
                    colors: ['#10b981', '#ef4444'],
                    xaxis: {
                        categories: ['Paid', 'Unpaid'],
                        labels: { style: { colors: '#374151', fontWeight: 700 } },
                        axisBorder: { show: true, color: '#000000' },
                        axisTicks: { show: true, color: '#000000' }
                    },
                    yaxis: {
                        labels: { style: { colors: '#374151', fontWeight: 600 } },
                        axisBorder: { show: true, color: '#000000' }
                    },
                    dataLabels: { enabled: true },
                    legend: { show: false },
                    grid: {
                        show: true,
                        borderColor: '#e5e7eb',
                        strokeDashArray: 0,
                        xaxis: { lines: { show: false } },
                        yaxis: { lines: { show: true } }
                    }
                };

                const tenantBarChart = new ApexCharts(document.getElementById('tenantsDoughnut'), barOptions);
                tenantBarChart.render();
            }

            // Payment Trends Bar Chart (Dynamic Data via AJAX)
            let paymentsBarChartInstance = null;

            window.fetchPaymentTrends = function () {
                const monthInput = document.getElementById('paymentTrendsMonthSelector');
                if (!monthInput || !document.getElementById('paymentsBar')) return;

                const loader = document.getElementById('paymentsBarLoading');
                if (loader) loader.classList.remove('hidden');

                const selectedMonth = monthInput.value;
                const subtitle = document.getElementById('paymentTrendsSubtitle');
                if (subtitle) {
                    const dateObj = new Date(selectedMonth + '-01');
                    subtitle.textContent = "Daily collections for " + dateObj.toLocaleString('default', { month: 'long', year: 'numeric' });
                }

                fetch('admin_dashboard.php?action=get_payment_trends_daily&month=' + selectedMonth)
                    .then(res => res.json())
                    .then(data => {
                        if (loader) loader.classList.add('hidden');
                        if (data.success) {
                            const options = {
                                series: [{
                                    name: 'Approved Amount',
                                    data: data.data.approved
                                }, {
                                    name: 'Pending/Declined Amount',
                                    data: data.data.other
                                }],
                                chart: {
                                    type: 'bar',
                                    height: 280,
                                    fontFamily: 'Inter, sans-serif',
                                    stacked: true,
                                    toolbar: { show: false }
                                },
                                colors: ['#059669', '#f59e0b'],
                                plotOptions: {
                                    bar: {
                                        borderRadius: 4,
                                        columnWidth: '70%'
                                    }
                                },
                                dataLabels: { enabled: false },
                                xaxis: {
                                    categories: data.data.days,
                                    labels: { style: { colors: '#374151', fontWeight: 600 } },
                                    axisBorder: { show: true, color: '#000000' },
                                    axisTicks: { show: true, color: '#000000' }
                                },
                                yaxis: {
                                    labels: {
                                        style: { colors: '#374151', fontWeight: 600 },
                                        formatter: (value) => { return '₱' + value.toLocaleString(); }
                                    },
                                    axisBorder: { show: true, color: '#000000' }
                                },
                                tooltip: {
                                    y: { formatter: function (val) { return "₱" + val.toLocaleString() } }
                                },
                                legend: { show: false },
                                grid: {
                                    show: true,
                                    borderColor: '#e5e7eb',
                                    strokeDashArray: 0,
                                    xaxis: { lines: { show: false } },
                                    yaxis: { lines: { show: true } }
                                }
                            };

                            if (paymentsBarChartInstance) {
                                paymentsBarChartInstance.updateOptions(options);
                            } else {
                                paymentsBarChartInstance = new ApexCharts(document.getElementById('paymentsBar'), options);
                                paymentsBarChartInstance.render();
                            }
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        if (loader) loader.classList.add('hidden');
                    });
            };

            // Initial fetch if element exists
            if (document.getElementById('paymentsBar')) {
                fetchPaymentTrends();
            }


            // Lease Timeline Gantt Chart (Functional Roadmap)
            const leaseData = <?php echo json_encode($leaseTimelines); ?>;
            if (document.getElementById('leaseGantt') && leaseData.length > 0) {
                const ganttOptions = {
                    series: [{
                        data: leaseData
                    }],
                    chart: {
                        height: 350,
                        type: 'rangeBar',
                        fontFamily: 'Inter, sans-serif',
                        toolbar: {
                            show: true,
                            tools: {
                                download: true,
                                selection: false,
                                zoom: true,
                                zoomin: true,
                                zoomout: true,
                                pan: true
                            }
                        }
                    },
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            distributed: true,
                            dataLabels: {
                                hideOverflowingLabels: false
                            }
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function (val, opts) {
                            var label = opts.w.globals.labels[opts.dataPointIndex];
                            return label;
                        },
                        style: {
                            colors: ['#fff'],
                            fontWeight: 600
                        }
                    },
                    colors: ['#059669', '#10b981', '#34d399', '#6ee7b7'], // emerald shades
                    xaxis: {
                        type: 'datetime',
                        labels: {
                            style: {
                                colors: '#64748b',
                                fontWeight: 500
                            }
                        }
                    },
                    yaxis: {
                        show: false // Hidden because the name is inside the bar
                    },
                    grid: {
                        borderColor: '#f1f5f9',
                        row: {
                            colors: ['#f8fafc', 'transparent'],
                            opacity: 0.5
                        }
                    },
                    tooltip: {
                        custom: function ({ series, seriesIndex, dataPointIndex, w }) {
                            const data = w.config.series[seriesIndex].data[dataPointIndex];
                            const start = new Date(data.y[0]).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            const end = new Date(data.y[1]).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            return `
                <div class="px-4 py-3 shadow-2xl border-0 rounded-xl bg-white flex flex-col gap-1">
                  <div class="text-xs font-bold text-emerald-600 uppercase tracking-wider">${data.x}</div>
                  <div class="flex flex-col gap-0">
                    <div class="text-sm font-semibold text-slate-700">Lease Period:</div>
                    <div class="text-xs font-medium text-slate-500">${start} — ${end}</div>
                  </div>
                </div>
              `;
                        }
                    }
                };

                const ganttChart = new ApexCharts(document.getElementById('leaseGantt'), ganttOptions);
                ganttChart.render();
            } else if (document.getElementById('leaseGantt')) {
                document.getElementById('leaseGantt').innerHTML = '<div class="flex items-center justify-center h-full text-gray-400">No lease data available to display.</div>';
            }
        });

        // ===== REPORTS MODAL FUNCTIONS =====
        function openReportsModal() {
            document.getElementById('reportsModal').classList.remove('hidden');
            fetchReportData();
        }

        function closeReportsModal() {
            document.getElementById('reportsModal').classList.add('hidden');
        }

        function switchReportTab(tabId) {
            // Buttons
            document.querySelectorAll('.report-tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tabId);
            });
            // Contents
            document.querySelectorAll('.report-tab-content').forEach(content => {
                content.classList.toggle('hidden', content.id !== 'reportTab-' + tabId);
            });
            // Update Summary Bar
            updateReportSummary(tabId);
        }
    </script>

    <!-- Reports Modal -->
    <div id="reportsModal"
        class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
        <div
            class="bg-white rounded-3xl shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col overflow-hidden animate-fade-in">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-emerald-600 px-8 py-6 flex items-center justify-between">
                <div class="flex items-center gap-4 text-white">
                    <div class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-md">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold">Monthly Business Reports</h2>
                        <p class="text-blue-100 text-sm">Comprehensive performance analysis and tracking</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex flex-col">
                        <label class="text-blue-100 text-xs font-bold uppercase mb-1">Select Month</label>
                        <input type="month" id="reportMonthSelector"
                            class="bg-white text-gray-900 rounded-xl px-4 py-2 border-0 focus:ring-2 focus:ring-blue-300 outline-none font-semibold shadow-inner"
                            value="<?php echo date('Y-m'); ?>">
                    </div>
                    <button onclick="closeReportsModal()"
                        class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition-all">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Toolbar / Summary Bar -->
            <div class="bg-gray-50 border-b border-gray-200 px-8 py-4 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div id="reportSummaryTotalSales"
                        class="bg-white px-5 py-2.5 rounded-2xl border border-gray-100 shadow-sm">
                        <p class="text-xs text-gray-500 font-bold uppercase" id="reportSummaryLabel">Total Revenue</p>
                        <p class="text-xl font-black text-emerald-600" id="reportSummaryValue">₱0.00</p>
                    </div>
                    <div class="h-10 w-px bg-gray-200"></div>
                    <nav class="flex gap-2">
                        <button onclick="switchReportTab('sales')"
                            class="report-tab-btn active px-4 py-2 rounded-xl text-sm font-bold transition-all"
                            data-tab="sales">Sales History</button>
                        <button onclick="switchReportTab('tenants')"
                            class="report-tab-btn px-4 py-2 rounded-xl text-sm font-bold transition-all"
                            data-tab="tenants">Tenants List</button>
                        <button onclick="switchReportTab('pending')"
                            class="report-tab-btn px-4 py-2 rounded-xl text-sm font-bold transition-all"
                            data-tab="pending">Pending Applications</button>
                        <button onclick="switchReportTab('outstanding')"
                            class="report-tab-btn px-4 py-2 rounded-xl text-sm font-bold transition-all"
                            data-tab="outstanding">Outstanding Balances</button>
                    </nav>
                </div>
                <button onclick="printReport()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg hover:shadow-xl">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-8 bg-gray-50/50">
                <!-- Loading State -->
                <div id="reportLoading" class="hidden flex flex-col items-center justify-center py-20">
                    <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-4">
                    </div>
                    <p class="text-gray-500 font-bold">Generating report data...</p>
                </div>

                <!-- Tab: Sales History -->
                <div id="reportTab-sales" class="report-tab-content space-y-4">
                    <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Transaction Date
                                    </th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Tenant</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Stall</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Reference #</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase text-right">Amount
                                        Paid</th>
                                </tr>
                            </thead>
                            <tbody id="reportSalesTableBody">
                                <!-- JS populated -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Tenants List -->
                <div id="reportTab-tenants" class="report-tab-content hidden space-y-4">
                    <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Company / Tradename
                                    </th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Stall #</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Contact Email</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody id="reportTenantsTableBody">
                                <!-- JS populated -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Pending Applications -->
                <div id="reportTab-pending" class="report-tab-content hidden space-y-4">
                    <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Applicant</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Business Type</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Submission Date
                                    </th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody id="reportPendingTableBody">
                                <!-- JS populated -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Outstanding Balances -->
                <div id="reportTab-outstanding" class="report-tab-content hidden space-y-4">
                    <div class="bg-white rounded-3xl border border-gray-200 overflow-hidden shadow-sm">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Tenant Name</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Stall #</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase">Monthly Rate</th>
                                    <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase text-right">
                                        Outstanding Balance</th>
                                </tr>
                            </thead>
                            <tbody id="reportOutstandingTableBody">
                                <!-- JS populated -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .report-tab-btn {
            color: #64748b;
            cursor: pointer;
        }

        .report-tab-btn:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }

        .report-tab-btn.active {
            background-color: #3b82f6;
            color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.4);
        }
    </style>

    <script>
        function fetchReportData() {
            const monthSelector = document.getElementById('reportMonthSelector');
            if (!monthSelector) return;
            const month = monthSelector.value;
            const loading = document.getElementById('reportLoading');
            const contents = document.querySelectorAll('.report-tab-content');

            loading.classList.remove('hidden');
            contents.forEach(c => c.classList.add('hidden'));

            fetch(`admin_dashboard.php?action=get_monthly_report&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    loading.classList.add('hidden');
                    if (data.success) {
                        renderReportData(data.data);
                        // Show current active tab
                        const activeTabBtn = document.querySelector('.report-tab-btn.active');
                        const activeTab = activeTabBtn ? activeTabBtn.dataset.tab : 'sales';
                        const tabContent = document.getElementById('reportTab-' + activeTab);
                        if (tabContent) tabContent.classList.remove('hidden');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    loading.classList.add('hidden');
                    console.error('Error:', error);
                    alert('Failed to fetch report data.');
                });
        }

        function updateReportSummary(tabId) {
            const data = window.currentReportData;
            if (!data) return;

            const summaryLabel = document.getElementById('reportSummaryLabel');
            const summaryValue = document.getElementById('reportSummaryValue');

            if (tabId === 'sales') {
                summaryLabel.textContent = 'Total Revenue';
                summaryValue.textContent = `₱${parseFloat(data.total_sales || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
                summaryValue.className = 'text-xl font-black text-emerald-600';
            } else if (tabId === 'outstanding') {
                summaryLabel.textContent = 'Total Outstanding';
                summaryValue.textContent = `₱${parseFloat(data.total_outstanding || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
                summaryValue.className = 'text-xl font-black text-red-600';
            } else {
                summaryLabel.textContent = 'Report Summary';
                summaryValue.textContent = '-';
                summaryValue.className = 'text-xl font-black text-gray-400';
            }
        }

        function renderReportData(data) {
            // Cache data
            window.currentReportData = data;

            const activeTabBtn = document.querySelector('.report-tab-btn.active');
            const activeTab = activeTabBtn ? activeTabBtn.dataset.tab : 'sales';
            updateReportSummary(activeTab);

            // Sales Table
            const salesBody = document.getElementById('reportSalesTableBody');
            if (salesBody) {
                salesBody.innerHTML = data.sales_history.length ? data.sales_history.map(s => `
                <tr class="border-b border-gray-50 hover:bg-gray-50/80 transition-colors">
                    <td class="px-6 py-4 text-sm text-gray-600 whitespace-nowrap">${new Date(s.payment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    <td class="px-6 py-4 font-bold text-gray-900">${s.tradename || s.username}</td>
                    <td class="px-6 py-4 text-xs font-bold text-blue-600 uppercase tracking-tighter">${s.stall_number || 'N/A'}</td>
                    <td class="px-6 py-4 text-sm font-mono text-gray-500">${s.reference_number || 'N/A'}</td>
                    <td class="px-6 py-4 text-right font-black text-emerald-600">₱${parseFloat(s.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                </tr>
            `).join('') : '<tr><td colspan="5" class="px-6 py-10 text-center text-gray-400 font-medium">No sales history for this month</td></tr>';
            }

            // Tenants Table
            const tenantsBody = document.getElementById('reportTenantsTableBody');
            if (tenantsBody) {
                tenantsBody.innerHTML = data.tenants_list.length ? data.tenants_list.map(t => `
                <tr class="border-b border-gray-50 hover:bg-gray-50/80 transition-colors">
                    <td class="px-6 py-4 font-bold text-gray-900">${t.tradename || t.company_name}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 font-bold">${t.stall_number || '—'}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">${t.email}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-black uppercase">Approved</span>
                    </td>
                </tr>
            `).join('') : '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 font-medium">No active tenants found</td></tr>';
            }

            // Pending Applications
            const pendingBody = document.getElementById('reportPendingTableBody');
            if (pendingBody) {
                pendingBody.innerHTML = data.pending_applications.length ? data.pending_applications.map(p => `
                <tr class="border-b border-gray-50 hover:bg-gray-50/80 transition-colors">
                    <td class="px-6 py-4 font-bold text-gray-900">${p.tradename || p.company_name}</td>
                    <td class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-tighter">${p.business_type}</td>
                    <td class="px-6 py-4 text-sm text-gray-600">${new Date(p.created_at).toLocaleDateString()}</td>
                    <td class="px-6 py-4">
                        <button onclick="closeReportsModal(); viewApplicationsLink.click()" class="text-blue-600 hover:text-blue-800 font-bold text-xs uppercase underline">Review Now</button>
                    </td>
                </tr>
            `).join('') : '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400 font-medium">No pending applications for this month</td></tr>';
            }

            // Outstanding Table
            const outstandingBody = document.getElementById('reportOutstandingTableBody');
            if (outstandingBody) {
                outstandingBody.innerHTML = data.outstanding_balances.length ? data.outstanding_balances.map(o => `
                <tr class="border-b border-gray-50 hover:bg-gray-50/80 transition-colors">
                    <td class="px-6 py-4 font-bold text-gray-900">${o.tradename || o.company_name}</td>
                    <td class="px-6 py-4 text-sm text-gray-600 font-bold">${o.stall_number || '—'}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">₱${parseFloat(o.monthly_rate || 0).toLocaleString()}</td>
                    <td class="px-6 py-4 text-right font-black text-red-600">₱${parseFloat(o.monthly_rate || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                </tr>
            `).join('') : '<tr><td colspan="4" class="px-6 py-10 text-center text-emerald-600 font-bold">All tenants have cleared their balances!</td></tr>';
            }
        }

        function printReport() {
            const activeTabBtn = document.querySelector('.report-tab-btn.active');
            const activeTab = activeTabBtn ? activeTabBtn.dataset.tab : 'sales';
            const tabContentEl = document.getElementById('reportTab-' + activeTab);
            const content = tabContentEl ? tabContentEl.innerHTML : '';
            const monthSelector = document.getElementById('reportMonthSelector');
            const month = monthSelector ? monthSelector.value : '';
            const formattedMonth = month ? new Date(month + '-01').toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) : 'Current Month';
            const tabName = activeTabBtn ? activeTabBtn.textContent : 'Report';
            const generationDate = new Date().toLocaleString('en-US', {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${tabName} - ${formattedMonth}</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
                <style>
                    body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; line-height: 1.5; }
                    table { width: 100%; border-collapse: collapse; margin-top: 30px; }
                    th { 
                        background: #f8fafc; 
                        text-align: left; 
                        padding: 14px 12px; 
                        font-size: 10px; 
                        color: #000; 
                        text-transform: uppercase; 
                        letter-spacing: 0.05em;
                        border: 1px solid #000; 
                    }
                    td { 
                        padding: 14px 12px; 
                        border: 1px solid #000; 
                        font-size: 11px; 
                        vertical-align: top;
                        color: #000;
                    }
                    /* Specific column adjustments */
                    th:last-child, td:last-child { text-align: right; }
                    
                    /* Sales History column balance */
                    th:nth-child(2), td:nth-child(2) { width: 35%; } /* Tenant name gets more space */
                    .header { 
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 20px;
                        margin-bottom: 40px; 
                        border-bottom: 3px solid #3b82f6; 
                        padding-bottom: 30px; 
                    }
                    .logo-container img { width: 80px; height: 80px; object-contain: contain; border-radius: 12px; }
                    .header-text { text-align: left; }
                    h1 { margin: 0; font-size: 28px; font-weight: 900; color: #0f172a; letter-spacing: -0.02em; }
                    h2 { margin: 4px 0 0; font-size: 16px; color: #3b82f6; font-weight: 700; }
                    .report-info { margin-top: 10px; font-size: 12px; color: #64748b; }
                    
                    .summary-box { 
                        margin-top: 40px; 
                        padding: 20px; 
                        background: #f1f5f9; 
                        border-radius: 16px;
                        display: flex;
                        justify-content: flex-end;
                        align-items: center;
                    }
                    .summary-label { font-size: 14px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-right: 15px; }
                    .summary-value { font-size: 24px; font-weight: 900; color: #059669; }
                    
                    .footer { 
                        margin-top: 60px; 
                        padding-top: 20px; 
                        border-top: 1px solid #e2e8f0; 
                        text-align: center; 
                        font-size: 10px; 
                        color: #94a3b8; 
                    }
                    
                    /* Utility Classes */
                    .text-right { text-align: right; }
                    .font-bold { font-weight: 700; }
                    .font-black { font-weight: 900; }
                    .text-emerald-600 { color: #059669; }
                    .text-red-600 { color: #dc2626; }
                    .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 9px; font-weight: 900; text-transform: uppercase; }
                    
                    @media print { 
                        body { padding: 20px; }
                        .no-print { display: none; }
                        @page { margin: 1.5cm; }
                    }
                    
                    /* Table adjustments */
                    th:last-child, td:last-child { text-align: right; }
                </style>
            </head>
            <body>
                <div class="header">
                    <div class="logo-container">
                        <img src="img/logo.jpg" alt="Logo">
                    </div>
                    <div class="header-text">
                        <h1>XentroMall Management System</h1>
                        <h2>${tabName} Report</h2>
                        <div class="report-info">Period: <span class="font-bold">${formattedMonth}</span></div>
                    </div>
                </div>

                <div class="table-container">
                    ${content}
                </div>

                <div class="summary-box" id="printSummaryBox" style="display: none;">
                    <span class="summary-label" id="summaryLabel">Total:</span>
                    <span class="summary-value" id="summaryValue">₱0.00</span>
                </div>

                <div class="footer">
                    <p>XentroMall Management System - Official Internal Report</p>
                    <p>Generated on ${generationDate}</p>
                    <p style="margin-top: 8px;">&copy; ${new Date().getFullYear()} XentroMall. All rights reserved.</p>
                </div>

                <script>
                     const summaryBox = document.getElementById('printSummaryBox');
                     const summaryLabel = document.getElementById('summaryLabel');
                     const summaryValue = document.getElementById('summaryValue');
                     
                     if ("${activeTab}" === "sales") {
                         summaryBox.style.display = 'flex';
                         summaryLabel.textContent = "Total Revenue:";
                         const total = window.opener.document.getElementById('reportSummaryValue').textContent;
                         summaryValue.textContent = total;
                     } else if ("${activeTab}" === "outstanding") {
                         summaryBox.style.display = 'flex';
                         summaryLabel.textContent = "Total Outstanding:";
                         const total = window.opener.document.getElementById('reportSummaryValue').textContent;
                         summaryValue.textContent = total;
                         summaryValue.className = "summary-value text-red-600";
                     }
                <\/script>
            </body>
            </html>
        `);
            printWindow.document.close();
            setTimeout(() => {
                printWindow.print();
            }, 800);
        }

        // Event listener for month change
        document.addEventListener('DOMContentLoaded', () => {
            const selector = document.getElementById('reportMonthSelector');
            if (selector) {
                selector.addEventListener('change', fetchReportData);
            }
        });

        // BIR Approval Modal Logic
        let currentBIRSubmission = null;

        function openBIRApprovalModal(submission) {
            currentBIRSubmission = submission;
            const infoEl = document.getElementById('birSubInfo');
            infoEl.innerHTML = `
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div class="text-gray-500">Tenant:</div>
          <div class="font-bold text-gray-900">${submission.tradename}</div>
          <div class="text-gray-500">Submission ID:</div>
          <div class="font-bold text-gray-900">#${submission.id}</div>
        </div>
      `;
            document.getElementById('birApprovalModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeBIRApprovalModal() {
            document.getElementById('birApprovalModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openManualBIRUpdateModal(tenant) {
            document.getElementById('manual_bir_tenant_id').value = tenant.id;
            document.getElementById('manual_bir_tenant_name').textContent = tenant.tradename;
            document.getElementById('manual_bir_expiry_input').value = tenant.expiry || '';
            document.getElementById('manualBIRUpdateModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeManualBIRUpdateModal() {
            document.getElementById('manualBIRUpdateModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        document.getElementById('confirmBIRApproval')?.addEventListener('click', function () {
            const expiryDate = document.getElementById('bir_expiry_input').value;
            if (!expiryDate) {
                showNotification('Please select an expiry date.', 'warning');
                return;
            }

            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';
            this.disabled = true;

            window.location.href = `bir_approval.php?id=${currentBIRSubmission.id}&action=approve&bir_expiry_date=${expiryDate}`;
        });

        // BIR Expiry Notifications
        function notifyTenantBIR(event, tenantId, tradename) {
            if (!confirm(`Send BIR expiry notification to ${tradename}?`)) return;

            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Sending...';

            fetch('send_bir_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `tenant_id=${tenantId}&type=single`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Successfully notified ${tradename} via Email and SMS.`);
                        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Sent';
                        btn.classList.replace('text-orange-600', 'text-emerald-600');
                    } else {
                        alert('Error: ' + data.message);
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred while sending the notification.');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                });
        }

        function notifyAllExpiringBIR(event) {
            if (!confirm('Send BIR expiry notifications to ALL tenants with expiring or expired registrations? This will send emails and SMS to multiple tenants.')) return;

            const btn = event.currentTarget;
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Notifying All...';

            fetch('send_bir_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'type=all_expiring'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        btn.innerHTML = '<i class="fas fa-check mr-1"></i>Done';
                    } else {
                        alert('Error: ' + data.message);
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred while sending notifications.');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                });
        }
    </script>

    <!-- BIR Approval Modal -->
    <div id="birApprovalModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl animate-fade-in">
            <div class="px-8 py-6 border-b border-gray-100 bg-gradient-to-r from-emerald-50 to-blue-50">
                <h3 class="text-2xl font-bold text-gray-900">Approve BIR Documents</h3>
                <p class="text-gray-600 mt-1">Set the registration expiry date to complete approval.</p>
            </div>

            <div class="p-8 space-y-6">
                <div id="birSubInfo" class="bg-gray-50 border border-gray-200 rounded-2xl p-4">
                    <!-- JS populated -->
                </div>

                <div>
                    <label for="bir_expiry_input"
                        class="block text-sm font-bold text-gray-700 mb-2 uppercase tracking-wider">BIR Registration
                        Expiry Date</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-calendar-alt text-emerald-500"></i>
                        </div>
                        <input type="date" id="bir_expiry_input" required
                            class="w-full pl-11 pr-4 py-4 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:ring-0 transition-all text-lg font-semibold text-gray-900 uppercase">
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Check the submitted document for the exact expiration date.
                    </p>
                </div>
            </div>

            <div class="px-8 py-6 bg-gray-50 flex items-center justify-end gap-3">
                <button onclick="closeBIRApprovalModal()"
                    class="px-6 py-3 text-gray-600 font-bold hover:bg-gray-200 rounded-2xl transition-all">
                    Cancel
                </button>
                <button id="confirmBIRApproval"
                    class="px-8 py-3 bg-gradient-to-r from-emerald-600 to-blue-600 text-white font-bold rounded-2xl shadow-lg hover:from-emerald-700 hover:to-blue-700 transition-all flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span>Confirm Approval</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Manual BIR Update Modal -->
    <div id="manualBIRUpdateModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[110] p-4">
        <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-fade-in">
            <div class="px-8 py-6 border-b border-gray-100 bg-gradient-to-r from-blue-50 to-indigo-50">
                <h3 class="text-xl font-bold text-gray-900">Update BIR Expiry Date</h3>
                <p class="text-xs text-gray-500 mt-1" id="manual_bir_tenant_name"></p>
            </div>

            <form action="admin_dashboard.php" method="POST">
                <input type="hidden" name="action" value="update_bir_expiry">
                <input type="hidden" name="tenant_id" id="manual_bir_tenant_id">

                <div class="p-8">
                    <label for="manual_bir_expiry_input"
                        class="block text-xs font-bold text-gray-700 mb-2 uppercase">New Expiry Date</label>
                    <input type="date" name="bir_expiry_date" id="manual_bir_expiry_input" required
                        class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl focus:border-blue-500 focus:ring-0 transition-all font-semibold">
                    <p class="text-[11px] text-gray-400 mt-2 italic">Updates the record directly without new document
                        submission.</p>
                </div>

                <div class="px-8 py-6 bg-gray-50 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeManualBIRUpdateModal()"
                        class="px-5 py-2 text-gray-500 font-bold hover:bg-gray-200 rounded-xl transition-all text-sm">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white font-bold rounded-xl shadow-md hover:bg-blue-700 transition-all text-sm flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        <span>Save Update</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Individual Bill Modal -->
    <div id="individualBillModal"
        class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-[200] flex items-center justify-center p-4">
        <div
            class="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full overflow-hidden animate-slide-up border border-gray-100">
            <!-- Header -->
            <div class="bg-gradient-to-br from-blue-600 to-indigo-700 px-8 py-7 relative overflow-hidden">
                <div class="absolute inset-0 bg-white/10 opacity-20 pointer-events-none"></div>
                <div class="relative z-10">
                    <h3 class="text-white font-black text-2xl tracking-tight" id="indiv_modal_tenant">Configure Bill
                    </h3>
                    <p class="text-blue-100/80 text-sm font-medium" id="indiv_modal_stall">Set utility amounts and
                        upload receipts</p>
                </div>
            </div>

            <div class="p-8 space-y-6">
                <!-- Water Bill section -->
                <div class="space-y-3">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest px-1">Water
                        Bill</label>
                    <div class="flex gap-4 items-center">
                        <div class="flex-1 relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-emerald-600 font-bold">₱</span>
                            <input type="number" id="indiv_water_amount" step="0.01" placeholder="0.00"
                                class="w-full pl-8 pr-4 py-3 bg-emerald-50/50 border-2 border-emerald-100 rounded-2xl focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all font-bold text-gray-800">
                        </div>
                        <label
                            class="flex items-center justify-center w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl cursor-pointer hover:bg-emerald-200 transition-all shadow-sm border border-emerald-200">
                            <i class="fas fa-camera text-xl"></i>
                            <input type="file" id="indiv_water_file" accept="image/*" class="hidden"
                                onchange="previewIndivFile(this, 'water')">
                        </label>
                    </div>
                    <div id="indiv_water_preview"
                        class="hidden px-4 py-2 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-xl flex items-center gap-2">
                        <i class="fas fa-image"></i>
                        <span class="truncate">Receipt selected</span>
                    </div>
                </div>

                <div class="h-px bg-gray-100"></div>

                <!-- Electric Bill section -->
                <div class="space-y-3">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest px-1">Electricity
                        Bill</label>
                    <div class="flex gap-4 items-center">
                        <div class="flex-1 relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-blue-600 font-bold">₱</span>
                            <input type="number" id="indiv_electric_amount" step="0.01" placeholder="0.00"
                                class="w-full pl-8 pr-4 py-3 bg-blue-50/50 border-2 border-blue-100 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all font-bold text-gray-800">
                        </div>
                        <label
                            class="flex items-center justify-center w-14 h-14 bg-blue-100 text-blue-600 rounded-2xl cursor-pointer hover:bg-blue-200 transition-all shadow-sm border border-blue-200">
                            <i class="fas fa-camera text-xl"></i>
                            <input type="file" id="indiv_electric_file" accept="image/*" class="hidden"
                                onchange="previewIndivFile(this, 'electric')">
                        </label>
                    </div>
                    <div id="indiv_electric_preview"
                        class="hidden px-4 py-2 bg-blue-50 text-blue-700 text-[10px] font-bold rounded-xl flex items-center gap-2">
                        <i class="fas fa-image"></i>
                        <span class="truncate">Receipt selected</span>
                    </div>
                </div>
            </div>

            <div class="px-8 py-6 bg-gray-50 flex gap-4">
                <button onclick="closeIndividualBillModal()"
                    class="flex-1 px-6 py-3.5 bg-white border-2 border-gray-200 text-gray-600 rounded-2xl hover:bg-gray-100 font-bold transition-all">Cancel</button>
                <button onclick="saveIndividualBill()"
                    class="flex-1 px-6 py-3.5 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 font-bold shadow-lg shadow-blue-500/20 transition-all">Confirm</button>
            </div>
        </div>
    </div>

    <style>
        @keyframes slide-up {
            from {
                transform: translateY(30px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .animate-slide-up {
            animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
    </style>

    <script>
        let currentIndivKey = null;

        function openIndividualBillModal(key, tenant, stall) {
            currentIndivKey = key;
            document.getElementById('indiv_modal_tenant').textContent = tenant;
            document.getElementById('indiv_modal_stall').textContent = 'Stall #' + stall + ' - Utilities';

            // Reset modal inputs to current row values
            document.getElementById('indiv_water_amount').value = document.getElementById('water_amount_' + key).value || 0;
            document.getElementById('indiv_electric_amount').value = document.getElementById('electric_amount_' + key).value || 0;

            // Reset file previews
            document.getElementById('indiv_water_preview').classList.add('hidden');
            document.getElementById('indiv_electric_preview').classList.add('hidden');

            // Check if files are already selected in the main form
            if (document.getElementById('water_file_' + key).files.length > 0) {
                document.getElementById('indiv_water_preview').classList.remove('hidden');
            }
            if (document.getElementById('electric_file_' + key).files.length > 0) {
                document.getElementById('indiv_electric_preview').classList.remove('hidden');
            }

            document.getElementById('individualBillModal').classList.remove('hidden');
            document.getElementById('individualBillModal').classList.add('flex');
        }

        function closeIndividualBillModal() {
            document.getElementById('individualBillModal').classList.add('hidden');
            document.getElementById('individualBillModal').classList.remove('flex');
        }

        function previewIndivFile(input, type) {
            const preview = document.getElementById('indiv_' + type + '_preview');
            if (input.files && input.files[0]) {
                preview.classList.remove('hidden');
                preview.querySelector('span').textContent = input.files[0].name;
            } else {
                preview.classList.add('hidden');
            }
        }

        function saveIndividualBill() {
            if (!currentIndivKey) return;

            const key = currentIndivKey;
            const wAmount = document.getElementById('indiv_water_amount').value || 0;
            const eAmount = document.getElementById('indiv_electric_amount').value || 0;

            // Update main form hidden fields
            document.getElementById('water_amount_' + key).value = wAmount;
            document.getElementById('electric_amount_' + key).value = eAmount;

            // Transfer files from modal temp inputs to main form inputs
            const modalWaterFile = document.getElementById('indiv_water_file');
            const modalElectricFile = document.getElementById('indiv_electric_file');
            const mainWaterFile = document.getElementById('water_file_' + key);
            const mainElectricFile = document.getElementById('electric_file_' + key);

            if (modalWaterFile.files.length > 0) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(modalWaterFile.files[0]);
                mainWaterFile.files = dataTransfer.files;
            }
            if (modalElectricFile.files.length > 0) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(modalElectricFile.files[0]);
                mainElectricFile.files = dataTransfer.files;
            }

            updateIndividualBillStatus(key);
            closeIndividualBillModal();

            // Visual feedback on the configure button
            const btn = document.getElementById('btn_config_' + key);
            btn.classList.remove('bg-gray-50', 'border-blue-200');
            btn.classList.add('bg-blue-600', 'border-blue-700');
            btn.querySelector('i').classList.replace('text-blue-600', 'text-white');
            btn.querySelector('span').classList.replace('text-gray-500', 'text-white');
            btn.querySelector('span').textContent = 'Configured';
        }

        function updateIndividualBillStatus(key) {
            const wAmount = parseFloat(document.getElementById('water_amount_' + key).value) || 0;
            const eAmount = parseFloat(document.getElementById('electric_amount_' + key).value) || 0;
            const hasWFile = document.getElementById('water_file_' + key).files.length > 0;
            const hasEFile = document.getElementById('electric_file_' + key).files.length > 0;

            const badgesContainer = document.getElementById('status_badges_' + key);
            const badgeW = document.getElementById('badge_water_' + key);
            const badgeE = document.getElementById('badge_electric_' + key);

            if (wAmount > 0 || hasWFile || eAmount > 0 || hasEFile) {
                badgesContainer.classList.remove('hidden');
            } else {
                badgesContainer.classList.add('hidden');
            }

            if (wAmount > 0 || hasWFile) badgeW.classList.remove('hidden'); else badgeW.classList.add('hidden');
            if (eAmount > 0 || hasEFile) badgeE.classList.remove('hidden'); else badgeE.classList.add('hidden');
        }
    </script>

    <!-- SOA Generation Modal -->
    <div id="soaGenerationModal"
        class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        <div
            class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden animate-fade-in flex flex-col border border-gray-100">
            <!-- Modal Header -->
            <div
                class="bg-gradient-to-r from-blue-700 to-indigo-800 px-8 py-6 flex items-center justify-between shadow-lg relative overflow-hidden">
                <div class="absolute inset-0 bg-white/10 opacity-20 pointer-events-none"></div>
                <div class="relative z-10 flex items-center gap-4">
                    <div
                        class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center border border-white/30 shadow-inner">
                        <i class="fas fa-file-invoice text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-white font-black text-xl tracking-tight">Generate Statement of Account</h2>
                        <p class="text-blue-100/80 text-xs font-medium">Create a billing statement for the tenant</p>
                    </div>
                </div>
                <button type="button" onclick="closeSOAModal()"
                    class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-all border border-white/20 hover:scale-110">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-8 py-6 bg-white">
                <form id="soaForm" class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">Reference
                                Number</label>
                            <input type="text" id="soa_ref_no" readonly
                                class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl font-bold text-gray-800">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2 px-1">Prepared
                                By</label>
                            <input type="text" value="<?php echo htmlspecialchars($admin_username); ?>" readonly
                                class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl font-bold text-gray-800">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">Tenant
                                Name</label>
                            <input type="text" id="soa_tenant_name" readonly
                                class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl font-bold text-gray-800">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">Unit/Stall</label>
                            <input type="text" id="soa_unit_name" readonly
                                class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-100 rounded-xl font-bold text-gray-800">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">Billing
                            Period</label>
                        <input type="month" id="soa_billing_month"
                            class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-600 transition-all font-bold text-gray-800"
                            onchange="fetchBillingData()">
                    </div>

                    <div class="h-px bg-gray-100"></div>

                    <div class="space-y-4">
                        <h3 class="text-sm font-black text-gray-900 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-coins text-amber-500"></i>
                            Billing Breakdown
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50/50 p-4 rounded-2xl border border-blue-100">
                                <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mb-1">Monthly
                                    Rent</p>
                                <div class="flex items-center gap-1 font-black text-blue-900 text-lg">
                                    <span>₱</span>
                                    <span id="soa_rent_amount">0.00</span>
                                </div>
                            </div>
                            <div class="bg-emerald-50/50 p-4 rounded-2xl border border-emerald-100">
                                <p class="text-[10px] text-emerald-600 font-black uppercase tracking-widest mb-1">Water
                                    Bill</p>
                                <div class="flex items-center gap-1 font-black text-emerald-900 text-lg">
                                    <span>₱</span>
                                    <span id="soa_water_amount">0.00</span>
                                </div>
                            </div>
                            <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100">
                                <p class="text-[10px] text-indigo-600 font-black uppercase tracking-widest mb-1">
                                    Electric Bill</p>
                                <div class="flex items-center gap-1 font-black text-indigo-900 text-lg">
                                    <span>₱</span>
                                    <span id="soa_electric_amount">0.00</span>
                                </div>
                            </div>
                            <div class="bg-rose-50/50 p-4 rounded-2xl border border-rose-100">
                                <p class="text-[10px] text-rose-600 font-black uppercase tracking-widest mb-1">Arrears
                                </p>
                                <div class="flex items-center gap-1 font-black text-rose-900 text-lg">
                                    <span>₱</span>
                                    <span id="soa_arrears_amount">0.00</span>
                                </div>
                            </div>
                            <div class="bg-amber-50/50 p-4 rounded-2xl border border-amber-100">
                                <p class="text-[10px] text-amber-600 font-black uppercase tracking-widest mb-1">Penalty
                                    / Late Fee</p>
                                <div class="flex items-center gap-1 font-black text-amber-900 text-lg">
                                    <span>₱</span>
                                    <span id="soa_penalty_amount">0.00</span>
                                </div>
                            </div>
                            <div class="bg-purple-50/50 p-4 rounded-2xl border border-purple-100">
                                <p class="text-[10px] text-purple-600 font-black uppercase tracking-widest mb-1">Other
                                    Charges</p>
                                <div class="flex items-center gap-1 font-black text-purple-900 text-lg">
                                    <span>₱</span>
                                    <span id="soa_other_amount">0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gradient-to-r from-gray-900 to-gray-800 p-6 rounded-2xl shadow-xl">
                            <div class="flex items-center justify-between text-white">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60">Total Amount
                                        Due</p>
                                    <p class="text-xs font-medium opacity-80">Inclusive of all utility charges</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-3xl font-black tracking-tight" id="soa_total_amount">₱ 0.00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="px-8 py-6 bg-gray-50 flex gap-4">
                <button onclick="closeSOAModal()"
                    class="flex-1 px-6 py-4 bg-white border-2 border-gray-200 text-gray-600 rounded-2xl hover:bg-gray-100 font-bold transition-all uppercase tracking-widest text-xs">
                    Cancel
                </button>
                <button onclick="generateSOAPrint()"
                    class="flex-1 px-6 py-4 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 font-bold shadow-xl shadow-blue-500/20 transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-2">
                    <i class="fas fa-print"></i>
                    Generate & Print
                </button>
            </div>
        </div>
    </div>

    <!-- Notify Balance Modal -->
    <div id="notifyBalanceModal"
        class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
        <div
            class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden animate-fade-in border border-gray-100">
            <!-- Modal Header -->
            <div
                class="bg-gradient-to-r from-blue-600 to-blue-800 px-8 py-6 flex items-center justify-between shadow-lg">
                <div class="flex items-center gap-4">
                    <div
                        class="w-12 h-12 bg-white/20 backdrop-blur-md rounded-xl flex items-center justify-center border border-white/30 shadow-inner">
                        <i class="fas fa-bell text-white text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-white font-black text-xl tracking-tight">Notify Balance</h2>
                        <p class="text-blue-100/80 text-xs font-medium">Send payment balance reminder</p>
                    </div>
                </div>
                <button type="button" onclick="closeNotifyBalanceModal()"
                    class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition-all border border-white/20">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="px-8 py-6 space-y-6">
                <!-- Tenant Info -->
                <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-100">
                    <p class="text-[10px] text-blue-600 font-black uppercase tracking-widest mb-2">Recipient</p>
                    <p id="nb_company_name" class="font-bold text-gray-900 text-lg"></p>
                    <div class="flex gap-4 mt-1 text-xs text-gray-500">
                        <span id="nb_email"></span>
                        <span id="nb_mobile"></span>
                    </div>
                </div>

                <!-- Calculations -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Remaining Months
                        </p>
                        <p id="nb_remaining_months" class="font-black text-gray-800 text-xl"></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mb-1">Monthly Rate</p>
                        <p id="nb_monthly_rate" class="font-black text-gray-800 text-xl"></p>
                    </div>
                </div>

                <!-- Total Balance -->
                <div
                    class="bg-gradient-to-r from-gray-900 to-gray-800 p-5 rounded-2xl shadow-xl flex items-center justify-between text-white">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-60">Estimated Total Balance
                        </p>
                        <p id="nb_expiration_date" class="text-xs font-medium opacity-80"></p>
                    </div>
                    <p id="nb_total_balance" class="text-2xl font-black tracking-tight text-blue-400"></p>
                </div>

                <!-- Custom Message -->
                <div>
                    <label class="block text-xs font-black text-gray-500 uppercase tracking-wider mb-2">Custom Message
                        (Optional)</label>
                    <textarea id="nb_custom_message" rows="3"
                        class="w-full px-4 py-3 bg-white border-2 border-gray-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-600 transition-all text-sm text-gray-700"
                        placeholder="Type a message for the tenant..."></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div class="px-8 py-6 bg-gray-50 flex gap-4">
                <button onclick="closeNotifyBalanceModal()"
                    class="flex-1 px-6 py-4 bg-white border-2 border-gray-200 text-gray-600 rounded-2xl hover:bg-gray-100 font-bold transition-all uppercase tracking-widest text-xs">
                    Cancel
                </button>
                <button id="btnSendNotifyBalance" onclick="sendPaymentBalanceNotification()"
                    class="flex-1 px-6 py-4 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 font-bold shadow-xl shadow-blue-500/20 transition-all uppercase tracking-widest text-xs flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i>
                    Send Notification
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentNBId = null;

        function openNotifyBalanceModal(tenantId, company, email, mobile, rate, expiration) {
            currentNBId = tenantId;
            document.getElementById('nb_company_name').textContent = company;
            document.getElementById('nb_email').textContent = email;
            document.getElementById('nb_mobile').textContent = mobile;
            document.getElementById('nb_monthly_rate').textContent = '₱ ' + parseFloat(rate).toLocaleString(undefined, { minimumFractionDigits: 2 });

            // Calculate remaining months manually for preview
            const expDate = new Date(expiration);
            const now = new Date();
            let months = (expDate.getFullYear() - now.getFullYear()) * 12 + (expDate.getMonth() - now.getMonth());
            months = Math.max(0, months);

            document.getElementById('nb_remaining_months').textContent = months + (months === 1 ? ' month' : ' months');
            document.getElementById('nb_total_balance').textContent = '₱ ' + (months * rate).toLocaleString(undefined, { minimumFractionDigits: 2 });
            document.getElementById('nb_expiration_date').textContent = 'Valid until ' + new Date(expiration).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            document.getElementById('nb_custom_message').value = '';

            document.getElementById('notifyBalanceModal').classList.remove('hidden');
            document.getElementById('notifyBalanceModal').classList.add('flex');
        }

        function closeNotifyBalanceModal() {
            document.getElementById('notifyBalanceModal').classList.add('hidden');
            document.getElementById('notifyBalanceModal').classList.remove('flex');
        }

        function sendPaymentBalanceNotification() {
            const btn = document.getElementById('btnSendNotifyBalance');
            const originalContent = btn.innerHTML;
            const message = document.getElementById('nb_custom_message').value;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            fetch('admin_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'send_payment_balance_notification',
                    'tenant_detail_id': currentNBId,
                    'custom_message': message
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        closeNotifyBalanceModal();
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An unexpected error occurred.', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                });
        }

        function sendDueDateReminder(btn, userId, amount) {
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            fetch('admin_dashboard.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'send_due_date_reminder',
                    'user_id': userId,
                    'amount': amount
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An unexpected error occurred.', 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                });
        }

        let currentSOATenant = null;

        function openSOAModal(tenant) {
            currentSOATenant = tenant;
            document.getElementById('soa_tenant_name').value = tenant.tradename;
            document.getElementById('soa_unit_name').value = 'Stall #' + (tenant.stall_number || 'N/A');
            document.getElementById('soa_rent_amount').textContent = parseFloat(tenant.monthly_rate || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Auto-generate Reference Number (e.g., SOA-20250305-123)
            const now = new Date();
            const refDate = now.toISOString().slice(0, 10).replace(/-/g, '');
            document.getElementById('soa_ref_no').value = 'SOA-' + refDate + '-' + tenant.id;

            // Set current month as default
            const currentMonth = now.toISOString().slice(0, 7);
            document.getElementById('soa_billing_month').value = currentMonth;

            fetchBillingData();

            document.getElementById('soaGenerationModal').classList.remove('hidden');
            document.getElementById('soaGenerationModal').classList.add('flex');
        }

        function closeSOAModal() {
            document.getElementById('soaGenerationModal').classList.add('hidden');
            document.getElementById('soaGenerationModal').classList.remove('flex');
        }

        function fetchBillingData() {
            if (!currentSOATenant) return;
            const month = document.getElementById('soa_billing_month').value;
            const userId = currentSOATenant.user_id;
            const stallId = currentSOATenant.stall_id;

            // Reset values first
            document.getElementById('soa_water_amount').textContent = '0.00';
            document.getElementById('soa_electric_amount').textContent = '0.00';
            document.getElementById('soa_arrears_amount').textContent = '0.00';
            document.getElementById('soa_penalty_amount').textContent = '0.00';
            document.getElementById('soa_other_amount').textContent = '0.00';
            updateSOATotal();

            fetch(`get_tenant_billing.php?user_id=${userId}&stall_id=${stallId}&month=${month}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('soa_water_amount').textContent = parseFloat(data.water_bill || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('soa_electric_amount').textContent = parseFloat(data.electric_bill || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('soa_arrears_amount').textContent = parseFloat(data.arrears || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('soa_penalty_amount').textContent = parseFloat(data.penalty || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        document.getElementById('soa_other_amount').textContent = parseFloat(data.other_charges || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        updateSOATotal();
                    }
                })
                .catch(error => console.error('Error fetching billing data:', error));
        }

        function updateSOATotal() {
            const rent = parseFloat(currentSOATenant.monthly_rate || 0);
            const water = parseFloat(document.getElementById('soa_water_amount').textContent.replace(/,/g, '')) || 0;
            const electric = parseFloat(document.getElementById('soa_electric_amount').textContent.replace(/,/g, '')) || 0;
            const arrears = parseFloat(document.getElementById('soa_arrears_amount').textContent.replace(/,/g, '')) || 0;
            const penalty = parseFloat(document.getElementById('soa_penalty_amount').textContent.replace(/,/g, '')) || 0;
            const other = parseFloat(document.getElementById('soa_other_amount').textContent.replace(/,/g, '')) || 0;

            const total = rent + water + electric + arrears + penalty + other;
            document.getElementById('soa_total_amount').textContent = '₱ ' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function generateSOAPrint() {
            const refNo = document.getElementById('soa_ref_no').value;
            const tenant = currentSOATenant;
            const month = document.getElementById('soa_billing_month').value;
            const water = document.getElementById('soa_water_amount').textContent;
            const electric = document.getElementById('soa_electric_amount').textContent;
            const rent = document.getElementById('soa_rent_amount').textContent;
            const arrears = document.getElementById('soa_arrears_amount').textContent;
            const penalty = document.getElementById('soa_penalty_amount').textContent;
            const other = document.getElementById('soa_other_amount').textContent;
            const total = document.getElementById('soa_total_amount').textContent;
            const preparedBy = "<?php echo addslashes($admin_username); ?>";

            // Create a hidden form or window to print
            const printUrl = `generate_soa_print.php?ref_no=${encodeURIComponent(refNo)}&tenant_id=${tenant.id}&month=${month}&water=${water}&electric=${electric}&rent=${rent}&arrears=${arrears}&penalty=${penalty}&other=${other}&total=${encodeURIComponent(total)}&prepared_by=${encodeURIComponent(preparedBy)}`;
            window.open(printUrl, '_blank');
        }
    </script>

    <!-- Mobile Sidebar Toggle -->
    <script>
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const btn = document.getElementById('mobileMenuBtn');

            if (sidebar.classList.contains('mobile-open')) {
                closeMobileSidebar();
            } else {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('active');
                btn.innerHTML = '<i class="fas fa-times"></i>';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMobileSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const btn = document.getElementById('mobileMenuBtn');

            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.style.overflow = '';
        }

        // Close sidebar when a nav link is clicked (mobile)
        document.querySelectorAll('#adminSidebar .nav-item:not(.dropdown-trigger)').forEach(item => {
            item.addEventListener('click', function () {
                if (window.innerWidth <= 1024) {
                    closeMobileSidebar();
                }
            });
        });

        // Close sidebar on window resize above 1024px
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                closeMobileSidebar();
            }
        });
    </script>
</body>

</html>