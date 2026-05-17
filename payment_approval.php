<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/sms_integration.php';
require __DIR__ . '/welcome_email_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$paymentId = $_GET['id'] ?? null;
$status = $_GET['status'] ?? null;
$remarks = $_GET['remarks'] ?? null;

if (!$paymentId || !in_array($status, ['approved', 'declined'])) {
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Check current payment status
    $stmtCheck = $pdo->prepare("SELECT status, user_id FROM payments WHERE id = ?");
    $stmtCheck->execute([$paymentId]);
    $payment = $stmtCheck->fetch();

    if (!$payment) {
        // Payment not found, redirect
        header('Location: admin_dashboard.php');
        exit;
    }

    if (in_array($payment['status'], ['approved', 'declined'])) {
        // Already approved or declined, do not update again
        $_SESSION['message'] = "Payment has already been " . $payment['status'] . ". No changes were made.";
        header('Location: admin_dashboard.php');
        exit;
    }

    // Update payment status with remarks
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, admin_remarks = ? WHERE id = ? AND status NOT IN ('approved', 'declined')");
    $stmt->execute([$status, $remarks, $paymentId]);

    if ($stmt->rowCount() === 0) {
        // No rows were updated (another process might have updated it)
        $_SESSION['message'] = "Payment status could not be updated. It may have been already processed.";
        header('Location: admin_dashboard.php');
        exit;
    }

    // Get payment details to check if it's a renewal payment
    $stmtPaymentDetails = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $stmtPaymentDetails->execute([$paymentId]);
    $paymentDetails = $stmtPaymentDetails->fetch(PDO::FETCH_ASSOC);

    $userId = $payment['user_id'];
    $paymentType = $paymentDetails['payment_type'] ?? '';
    // Check if it's a renewal, advance, or additional stall payment
    $isRenewalPayment = ($paymentType === 'renewal_monthly' || $paymentType === 'renewal');
    $isAdvancePayment = ($paymentType === 'new_applicant_advance' || $paymentType === 'additional_stall_advance' || $paymentType === 'advance');

    if ($userId) {

        $stmtTenant = $pdo->prepare("SELECT email, tradename FROM tenant_details WHERE user_id = ?");
        $stmtTenant->execute([$userId]);
        $tenantInfo = $stmtTenant->fetch();

        // Use tenant_details email as PRIMARY source
        $tenantEmail = $tenantInfo['email'] ?? null;
        $tradename = $tenantInfo['tradename'] ?? 'Tenant';

        // Get user info as FALLBACK if tenant_details email is empty
        $stmtUser = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $userInfo = $stmtUser->fetch();

        // Use users table email as FALLBACK only if tenant_details email is empty
        if (empty($tenantEmail) && !empty($userInfo['email'])) {
            $tenantEmail = $userInfo['email'];
        }

        // Use username as fallback for tradename
        if (empty($tradename) && !empty($userInfo['username'])) {
            $tradename = $userInfo['username'];
        }

        // Log email info for debugging
        error_log("Payment #$paymentId - User ID: $userId, Tenant Email: " . ($tenantInfo['email'] ?? 'NULL') . ", User Email: " . ($userInfo['email'] ?? 'NULL') . ", Final Email: " . ($tenantEmail ?: 'NOT FOUND') . ", Tradename: $tradename");

        // Initialize contract dates
        $contractStart = null;
        $contractEnd = null;

        if ($status === 'approved') {

            // Handle RENEWAL PAYMENT - Extend contract by 1 year
            if ($isRenewalPayment) {
                error_log("Processing RENEWAL payment approval for User ID: $userId");

                try {
                    // Get tenant details for renewal
                    $stmtTenantDetails = $pdo->prepare("
                        SELECT td.id as tenant_detail_id, td.stall_id, t.id as tenant_id, tld.lease_expiration_date 
                        FROM tenant_details td
                        LEFT JOIN tenants t ON t.email = td.email
                        LEFT JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
                        WHERE td.user_id = ?
                    ");
                    $stmtTenantDetails->execute([$userId]);
                    $tenantRenewalInfo = $stmtTenantDetails->fetch(PDO::FETCH_ASSOC);

                    if ($tenantRenewalInfo && $tenantRenewalInfo['lease_expiration_date']) {
                        // Extend contract by 1 year from current expiration
                        $currentExpiration = $tenantRenewalInfo['lease_expiration_date'];
                        $newExpirationDate = date('Y-m-d', strtotime($currentExpiration . ' + 1 year'));

                        error_log("Extending contract from $currentExpiration to $newExpirationDate");

                        // Update contract expiration date
                        $stmtExtendContract = $pdo->prepare("
                            UPDATE tenant_lease_dates 
                            SET lease_expiration_date = ?, status = 'active' 
                            WHERE tenant_id = ?
                        ");
                        $stmtExtendContract->execute([$newExpirationDate, $tenantRenewalInfo['tenant_id']]);

                        // Update renewal request status to completed
                        $stmtUpdateRenewal = $pdo->prepare("
                            UPDATE unified_renewal_requests 
                            SET status = 'completed' 
                            WHERE user_id = ? AND stall_id = ? AND status IN ('payment_pending', 'approved')
                        ");
                        $stmtUpdateRenewal->execute([$userId, $tenantRenewalInfo['stall_id']]);

                        // Also update old contract renewals if they exist
                        $stmtUpdateOldRenewal = $pdo->prepare("
                            UPDATE contract_renewals 
                            SET status = 'completed' 
                            WHERE user_id = ? AND stall_id = ? AND status IN ('payment_pending', 'approved')
                        ");
                        $stmtUpdateOldRenewal->execute([$userId, $tenantRenewalInfo['stall_id']]);

                        // Send success notification to tenant
                        $renewalMessage = "🎉 Your contract renewal has been completed! Your contract is now extended until " . date('F j, Y', strtotime($newExpirationDate));
                        $stmtRenewalNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                        $stmtRenewalNotif->execute([$userId, $renewalMessage]);

                        error_log("✅ Renewal completed successfully! New expiration: $newExpirationDate");

                        // Set contract dates for email
                        $contractStart = date('Y-m-d');
                        $contractEnd = $newExpirationDate;

                    } else {
                        error_log("❌ Could not find tenant contract info for renewal");
                    }

                } catch (PDOException $e) {
                    error_log("Renewal processing error: " . $e->getMessage());
                }
            }

            $message = "Your payment has been completed.";

            // Get the tenant's stall_id and user email
            $stmtStall = $pdo->prepare("SELECT td.stall_id, u.email FROM tenant_details td 
                                        INNER JOIN users u ON u.id = td.user_id
                                        WHERE td.user_id = ?");
            $stmtStall->execute([$userId]);
            $tenant = $stmtStall->fetch();

            // Debug log
            error_log("Payment Approval - User ID: $userId, Stall ID: " . ($tenant['stall_id'] ?? 'NULL'));

            if ($tenant && !empty($tenant['stall_id'])) {
                // Update the stall status to not_available
                $stmtUpdateStall = $pdo->prepare("UPDATE stalls SET status = 'not_available' WHERE id = ?");
                $stmtUpdateStall->execute([$tenant['stall_id']]);

                // Get or create tenant entry in tenants table
                $stmtGetTenant = $pdo->prepare("SELECT id FROM tenants WHERE email = ?");
                $stmtGetTenant->execute([$tenant['email']]);
                $tenantRecord = $stmtGetTenant->fetch();

                if (!$tenantRecord) {
                    // Create tenant record if doesn't exist
                    error_log("Creating new tenant record for user ID: $userId");
                    $stmtCreateTenant = $pdo->prepare("INSERT INTO tenants (username, email, password, role, created_at) 
                                                        SELECT username, email, password, 'tenant', NOW() 
                                                        FROM users WHERE id = ?");
                    $stmtCreateTenant->execute([$userId]);
                    $tenantId = $pdo->lastInsertId();
                    error_log("Created tenant ID: $tenantId");
                } else {
                    $tenantId = $tenantRecord['id'];
                    error_log("Found existing tenant ID: $tenantId");
                }

                // Check if contract already exists
                $stmtCheckContract = $pdo->prepare("SELECT tenant_id FROM tenant_lease_dates WHERE tenant_id = ?");
                $stmtCheckContract->execute([$tenantId]);
                $existingContract = $stmtCheckContract->fetch();

                if (!$existingContract) {
                    // First payment - Create initial contract
                    error_log("Creating new contract for tenant ID: $tenantId");
                    try {
                        // Get contract duration from settings
                        $stmtSettings = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'contract_duration_months'");
                        $stmtSettings->execute();
                        $contractDuration = $stmtSettings->fetchColumn() ?: 12; // Default 12 months

                        error_log("Contract duration: $contractDuration months");

                        // Set contract dates based on payment approval date (today's date)
                        $contractStart = date('Y-m-d'); // Contract starts on approval date (today)
                        $contractEnd = date('Y-m-d', strtotime("+{$contractDuration} months")); // 1 year from approval date

                        error_log("Contract dates: $contractStart to $contractEnd");

                        // Insert contract dates
                        $stmtContract = $pdo->prepare("INSERT INTO tenant_lease_dates 
                            (tenant_id, lease_start_date, lease_expiration_date, status, renewal_reminder_sent, late_renewal_fee) 
                            VALUES (?, ?, ?, 'active', 0, 0.00)");
                        $stmtContract->execute([$tenantId, $contractStart, $contractEnd]);

                        error_log("Contract created successfully!");

                        // Send notification about contract
                        $contractMessage = "Your contract has been activated! Contract period: " . date('M j, Y', strtotime($contractStart)) . " to " . date('M j, Y', strtotime($contractEnd));
                        $stmtContractNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                        $stmtContractNotif->execute([$userId, $contractMessage]);

                        // Send dedicated Welcome Email to New Tenant
                        $stmtStallNum = $pdo->prepare("SELECT stall_number FROM stalls WHERE id = ?");
                        $stmtStallNum->execute([$tenant['stall_id']]);
                        $stallNumber = $stmtStallNum->fetchColumn();

                        sendWelcomeEmail($tenantEmail, $tradename, $stallNumber);
                    } catch (PDOException $e) {
                        // Log error but don't stop the payment approval
                        error_log("Contract creation error: " . $e->getMessage());
                    }
                } else {
                    error_log("Contract already exists for tenant ID: $tenantId");

                    // Update existing contract to use correct approval date if it has old dates
                    try {
                        // Get current contract details
                        $stmtCurrentContract = $pdo->prepare("SELECT lease_start_date, lease_expiration_date FROM tenant_lease_dates WHERE tenant_id = ?");
                        $stmtCurrentContract->execute([$tenantId]);
                        $currentContract = $stmtCurrentContract->fetch(PDO::FETCH_ASSOC);

                        if ($currentContract) {
                            $currentStart = $currentContract['lease_start_date'];
                            $currentEnd = $currentContract['lease_expiration_date'];

                            // Check if contract dates are in the past or seem incorrect
                            $today = date('Y-m-d');
                            if ($currentStart < $today || $currentEnd <= $today) {
                                // Update contract to start from today and end 1 year from today
                                $newStart = $today;
                                $newEnd = date('Y-m-d', strtotime("+12 months"));

                                $stmtUpdateContract = $pdo->prepare("UPDATE tenant_lease_dates 
                                    SET lease_start_date = ?, lease_expiration_date = ?, status = 'active' 
                                    WHERE tenant_id = ?");
                                $stmtUpdateContract->execute([$newStart, $newEnd, $tenantId]);

                                error_log("Updated contract dates: $newStart to $newEnd");
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Contract update error: " . $e->getMessage());
                    }
                }
            }

            // [NEW] CRITICAL SYNC: Mark any pending renewal/application requests as completed
            // This ensures "Payment Under Review" disappears from the dashboard
            try {
                // Update unified_renewal_requests status to completed
                $stmtUpdateRenewal = $pdo->prepare("
                    UPDATE unified_renewal_requests 
                    SET status = 'completed' 
                    WHERE user_id = ? AND status IN ('payment_pending', 'approved')
                ");
                $stmtUpdateRenewal->execute([$userId]);

                // Also update contract_renewals if applicable
                $stmtUpdateOldRenewal = $pdo->prepare("
                    UPDATE contract_renewals 
                    SET status = 'completed' 
                    WHERE user_id = ? AND status IN ('payment_pending', 'approved')
                ");
                $stmtUpdateOldRenewal->execute([$userId]);

                error_log("✅ Status sync completed for user $userId - Requests marked as completed.");
            } catch (PDOException $e) {
                error_log("❌ Status sync error on approval: " . $e->getMessage());
            }

            // Build professional email with contract dates
            if ($isRenewalPayment) {
                $emailSubject = "🎉 Renewal Payment Approved - Contract Extended!";
                $emailTitle = "Renewal Payment Approved!";
                $emailSubtitle = "Your contract has been successfully extended.";
            } else {
                $emailSubject = "🎉 Payment Approved - XentroMall";
                $emailTitle = "Congratulations!";
                $emailSubtitle = "Your payment has been approved.";
            }

            $contractSection = "";
            if ($contractStart && $contractEnd) {
                if ($isRenewalPayment) {
                    $contractSection = "
                    <div style='background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%); border-left: 5px solid #f59e0b; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                        <h3 style='color: #92400e; margin-top: 0; font-size: 18px;'>🔄 Contract Renewal Details</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #374151;'><strong>Previous Expiration:</strong></td>
                                <td style='padding: 8px 0; color: #92400e;'>" . date('F j, Y', strtotime($currentExpiration ?? 'now')) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #374151;'><strong>New Expiration:</strong></td>
                                <td style='padding: 8px 0; color: #059669; font-weight: bold;'>" . date('F j, Y', strtotime($contractEnd)) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #374151;'><strong>Extension:</strong></td>
                                <td style='padding: 8px 0; color: #059669; font-weight: bold;'>12 Months</td>
                            </tr>
                        </table>
                        <p style='margin: 15px 0 0 0; padding: 12px; background: white; border-radius: 6px; color: #059669; font-size: 14px;'>
                            ✅ Your contract renewal is complete! Your stall remains active.
                        </p>
                    </div>";
                } else {
                    $contractSection = "
                    <div style='background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 5px solid #10b981; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                        <h3 style='color: #059669; margin-top: 0; font-size: 18px;'>📅 Contract Details</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #374151;'><strong>Start Date:</strong></td>
                                <td style='padding: 8px 0; color: #059669; font-weight: bold;'>" . date('F j, Y', strtotime($contractStart)) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #374151;'><strong>End Date:</strong></td>
                                <td style='padding: 8px 0; color: #059669; font-weight: bold;'>" . date('F j, Y', strtotime($contractEnd)) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #374151;'><strong>Duration:</strong></td>
                                <td style='padding: 8px 0; color: #059669; font-weight: bold;'>12 Months</td>
                            </tr>
                        </table>
                        <p style='margin: 15px 0 0 0; padding: 12px; background: white; border-radius: 6px; color: #059669; font-size: 14px;'>
                            ✅ Your stall is now active and ready for use!
                        </p>
                    </div>";
                }
            }

            $emailBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                    .header { background: #10b981; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                    .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                    .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                    .greeting { font-size: 16px; color: #1f2937; margin-bottom: 20px; }
                    .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #10b981; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                    .info-box h3 { margin-top: 0; color: #10b981; font-size: 18px; }
                    .badge { display: inline-block; background: #10b981; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                    .button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>$emailTitle</h1>
                        <p>$emailSubtitle</p>
                    </div>
                    <div class='content'>
                        <p class='greeting'>Dear <strong>$tradename</strong>,</p>
                        <p style='font-size: 15px; color: #374151;'>
                            Great news! Your payment has been successfully <strong style='color: #10b981;'>approved</strong>. 
                            " . ($isRenewalPayment ? 'Your contract has been extended!' : 'Welcome to XentroMall!') . "
                        </p>
                        
                        <div class='info-box'>
                            <h3>💳 Payment Details</h3>
                            <p style='margin: 8px 0;'><strong>Payment ID:</strong> #$paymentId</p>
                            <p style='margin: 8px 0;'><strong>Status:</strong> <span class='badge'>✓ Approved</span></p>
                        </div>
                        
                        $contractSection
                        
                        <p style='font-size: 15px; color: #374151; margin-top: 25px;'>
                            You can now access your tenant dashboard to manage your stall and view all contract details.
                        </p>
                        
                        <p style='font-size: 15px; color: #374151; margin-top: 30px;'>
                            Thank you for choosing XentroMall! We're excited to have you as part of our community.
                        </p>
                        
                        <div class='footer'>
                            <p style='margin: 5px 0;'>This is an automated message from XentroMall Management System</p>
                            <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                            <p style='margin: 15px 0 0 0; color: #9ca3af;'>If you have any questions, please contact our support team.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";

        } elseif ($status === 'declined') {
            $message = "Your payment has been declined.";
            $emailSubject = "❌ Payment Declined - XentroMall";

            // Build remarks section for email
            $remarksSection = '';
            if (!empty($remarks)) {
                $remarksSection = "
                        <div class='alert-box' style='background: #fef3c7; border: 1px solid #fde68a; padding: 15px; border-radius: 8px; margin: 20px 0; color: #92400e;'>
                            <strong>📝 Admin Remarks:</strong><br>
                            " . htmlspecialchars($remarks) . "
                        </div>";
            }

            $emailBody = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                    .header { background: #f97316; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                    .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                    .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                    .greeting { font-size: 16px; color: #1f2937; margin-bottom: 20px; }
                    .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #f97316; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                    .info-box h3 { margin-top: 0; color: #f97316; font-size: 18px; }
                    .badge { display: inline-block; background: #f97316; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                    .alert-box { background: #fff7ed; border: 1px solid #ffedd5; padding: 15px; border-radius: 8px; margin: 20px 0; color: #9a3412; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Payment Status Update</h1>
                        <p>XentroMall Management System</p>
                    </div>
                    <div class='content'>
                        <p class='greeting'>Dear <strong>$tradename</strong>,</p>
                        <p style='font-size: 15px; color: #374151;'>
                            We regret to inform you that your payment has been <strong style='color: #ef4444;'>declined</strong>.
                        </p>
                        
                        <div class='info-box'>
                            <h3>💳 Payment Details</h3>
                            <p style='margin: 8px 0;'><strong>Payment ID:</strong> #$paymentId</p>
                            <p style='margin: 8px 0;'><strong>Status:</strong> <span class='badge'>✗ Declined</span></p>
                        </div>
                        
                        $remarksSection
                        
                        <div class='alert-box'>
                            <strong>⚠️ Next Steps:</strong><br>
                            Please contact the XentroMall administration office for more information about your payment status. 
                            Our team will be happy to assist you with any questions or concerns.
                        </div>
                        
                        <p style='font-size: 15px; color: #374151; margin-top: 25px;'>
                            If you believe this is an error, please reach out to our support team immediately.
                        </p>
                        
                        <div class='footer'>
                            <p style='margin: 5px 0;'>This is an automated message from XentroMall Management System</p>
                            <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                            <p style='margin: 15px 0 0 0; color: #9ca3af;'>For assistance, please contact our support team.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
        } else {
            $message = "Your payment status has been updated.";
            $emailSubject = "Payment Status Update - XentroMall";
            $emailBody = "<h2>Payment Status Update</h2>
                         <p>Dear $tradename,</p>
                         <p>Your payment status has been updated.</p>
                         <p><strong>Payment ID:</strong> $paymentId</p>
                         <p><strong>Status:</strong> $status</p>
                         <br>
                         <p>Best regards,<br>XentroMall Management</p>";
        }

        // Insert notification
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
        $stmtNotif->execute([$userId, $message]);

        // Send email notification
        error_log("=== EMAIL SENDING START === Payment ID: $paymentId, Status: $status");
        error_log("Tenant Email: " . ($tenantEmail ?: 'NULL'));
        error_log("Email Subject: " . ($emailSubject ?? 'NOT SET'));

        if ($tenantEmail) {
            $mail = new PHPMailer(true);
            try {
                // Server settings with debug
                $mail->SMTPDebug = 2; // Enable verbose debug output
                $mail->Debugoutput = function ($str, $level) {
                    error_log("SMTP Debug [$level]: $str");
                };

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mallxentro5@gmail.com';
                $mail->Password = 'iwld cjlr kmcy bxab';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->Timeout = 30; // 30 seconds timeout

                // Fix SSL certificate verification issue in XAMPP
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // Recipients
                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall');
                $mail->addAddress($tenantEmail);

                error_log("Email recipient set to: $tenantEmail");

                // Content
                $mail->isHTML(true);
                $mail->Subject = $emailSubject;
                $mail->Body = $emailBody;

                error_log("Email subject: $emailSubject");
                error_log("Attempting to send email...");

                if ($mail->send()) {
                    $logMsg = date('Y-m-d H:i:s') . " - ✅ EMAIL SENT to $tenantEmail for payment ID: $paymentId\n";
                    file_put_contents(__DIR__ . '/payment_email_log.txt', $logMsg, FILE_APPEND);
                    error_log("✅✅✅ EMAIL SENT SUCCESSFULLY to $tenantEmail for payment #$paymentId");
                } else {
                    $logMsg = date('Y-m-d H:i:s') . " - ❌ EMAIL FAILED: " . $mail->ErrorInfo . "\n";
                    file_put_contents(__DIR__ . '/payment_email_log.txt', $logMsg, FILE_APPEND);
                    error_log("❌❌❌ EMAIL FAILED - Mailer Error: " . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                $logMsg = date('Y-m-d H:i:s') . " - ❌ EXCEPTION: " . $e->getMessage() . "\n";
                file_put_contents(__DIR__ . '/payment_email_log.txt', $logMsg, FILE_APPEND);
                error_log("❌❌❌ EXCEPTION while sending email to $tenantEmail");
                error_log("Exception Message: " . $e->getMessage());
                error_log("Exception Code: " . $e->getCode());
                error_log("Mailer Error Info: " . $mail->ErrorInfo);
                error_log("Stack Trace: " . $e->getTraceAsString());
            }
        } else {
            $logMsg = date('Y-m-d H:i:s') . " - ⚠️ NO EMAIL for payment ID: $paymentId\n";
            file_put_contents(__DIR__ . '/payment_email_log.txt', $logMsg, FILE_APPEND);
            error_log("⚠️⚠️⚠️ NO EMAIL ADDRESS FOUND for payment ID: $paymentId - Email NOT sent!");
        }

        error_log("=== EMAIL SENDING END ===");

        // Send SMS notification
        error_log("=== SMS SENDING START === Payment ID: $paymentId, Status: $status");

        // Get tenant phone number from tenant_details
        $stmtPhone = $pdo->prepare("SELECT mobile FROM tenant_details WHERE user_id = ?");
        $stmtPhone->execute([$userId]);
        $tenantPhone = $stmtPhone->fetchColumn();

        if ($tenantPhone) {
            $sms = new IPROG_SMS();

            if ($status === 'approved') {
                $smsResult = $sms->sendPaymentApprovalSMS($tradename, $tenantPhone, 'approved');
                error_log("Payment approval SMS sent to $tenantPhone for $tradename");
            } elseif ($status === 'declined') {
                $smsResult = $sms->sendPaymentApprovalSMS($tradename, $tenantPhone, 'declined', $remarks);
                error_log("Payment declined SMS sent to $tenantPhone for $tradename with remarks: $remarks");
            }

            if ($smsResult['success']) {
                $logMsg = date('Y-m-d H:i:s') . " - ✅ SMS SENT to $tenantPhone for payment ID: $paymentId\n";
                file_put_contents(__DIR__ . '/payment_sms_log.txt', $logMsg, FILE_APPEND);
                error_log("✅✅✅ SMS SENT SUCCESSFULLY to $tenantPhone for payment #$paymentId");
            } else {
                $logMsg = date('Y-m-d H:i:s') . " - ❌ SMS FAILED: " . $smsResult['response'] . "\n";
                file_put_contents(__DIR__ . '/payment_sms_log.txt', $logMsg, FILE_APPEND);
                error_log("❌❌❌ SMS FAILED for payment #$paymentId: " . $smsResult['response']);
            }
        } else {
            $logMsg = date('Y-m-d H:i:s') . " - ⚠️ NO PHONE NUMBER for payment ID: $paymentId\n";
            file_put_contents(__DIR__ . '/payment_sms_log.txt', $logMsg, FILE_APPEND);
            error_log("⚠️⚠️⚠️ NO PHONE NUMBER FOUND for payment ID: $paymentId - SMS NOT sent!");
        }

        error_log("=== SMS SENDING END ===");
    }

    // Set success message for toast notification
    if ($status === 'approved') {
        $_SESSION['payment_success'] = "Payment #$paymentId has been approved successfully! Email and SMS notifications sent to tenant.";
    } else {
        $_SESSION['payment_success'] = "Payment #$paymentId has been declined. Email and SMS notifications sent to tenant.";
    }


} catch (Exception $e) {
    // Log error or handle as needed
    $_SESSION['payment_error'] = "An error occurred while processing payment #$paymentId. Please try again.";
    error_log("Payment approval error: " . $e->getMessage());
}

header('Location: admin_dashboard.php');
exit;
?>