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
        header('Location: admin_dashboard.php');
        exit;
    }

    if (in_array($payment['status'], ['approved', 'declined'])) {
        $_SESSION['success'] = "Payment #$paymentId was already processed as " . $payment['status'] . ".";
        header('Location: admin_dashboard.php');
        exit;
    }

    // Update payment status
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, admin_remarks = ? WHERE id = ?");
    $stmt->execute([$status, $remarks, $paymentId]);

    // Get basic details
    $userId = $payment['user_id'];
    
    // Fetch payment details for type check
    $stmtPD = $pdo->prepare("SELECT payment_type FROM payments WHERE id = ?");
    $stmtPD->execute([$paymentId]);
    $pType = $stmtPD->fetchColumn();
    $isRenewal = ($pType === 'renewal_monthly' || $pType === 'renewal');

    // Fetch tenant contact details
    $stmtTd = $pdo->prepare("SELECT email, tradename, mobile FROM tenant_details WHERE user_id = ?");
    $stmtTd->execute([$userId]);
    $tenant = $stmtTd->fetch();
    
    $tenantEmail = $tenant['email'] ?? null;
    $tradename = $tenant['tradename'] ?? 'Tenant';
    $tenantPhone = $tenant['mobile'] ?? null;

    if (empty($tenantEmail)) {
        $stmtU = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmtU->execute([$userId]);
        $tenantEmail = $stmtU->fetchColumn();
    }

    // ============================================================
    // STEP 1: Process Contracts/Stalls (Approved only)
    // ============================================================
    $contractStart = null;
    $contractEnd = null;

    if ($status === 'approved') {
        if ($isRenewal) {
            try {
                // Get tenant extension info
                $stmtExt = $pdo->prepare("
                    SELECT t.id as tenant_id, tld.lease_expiration_date 
                    FROM tenant_details td
                    JOIN tenants t ON t.email = td.email
                    JOIN tenant_lease_dates tld ON tld.tenant_id = t.id
                    WHERE td.user_id = ?
                ");
                $stmtExt->execute([$userId]);
                $extInfo = $stmtExt->fetch();
                
                if ($extInfo && $extInfo['lease_expiration_date']) {
                    $newEnd = date('Y-m-d', strtotime($extInfo['lease_expiration_date'] . ' + 1 year'));
                    $pdo->prepare("UPDATE tenant_lease_dates SET lease_expiration_date = ?, status = 'active' WHERE tenant_id = ?")
                        ->execute([$newEnd, $extInfo['tenant_id']]);
                    
                    // Complete renewal requests
                    $pdo->prepare("UPDATE unified_renewal_requests SET status = 'completed' WHERE user_id = ? AND status != 'completed'")
                        ->execute([$userId]);
                    
                    $contractEnd = $newEnd;
                }
            } catch (Exception $e) { error_log("Renewal Error: " . $e->getMessage()); }
        } else {
            // New Application Payment
            $stmtSt = $pdo->prepare("SELECT td.stall_id, u.email, u.username FROM tenant_details td JOIN users u ON u.id = td.user_id WHERE td.user_id = ?");
            $stmtSt->execute([$userId]);
            $inf = $stmtSt->fetch();

            if ($inf && !empty($inf['stall_id'])) {
                $pdo->prepare("UPDATE stalls SET status = 'not_available' WHERE id = ?")->execute([$inf['stall_id']]);
                
                $stmtT = $pdo->prepare("SELECT id FROM tenants WHERE email = ?");
                $stmtT->execute([$inf['email']]);
                $tid = $stmtT->fetchColumn();

                if (!$tid) {
                    $pdo->prepare("INSERT INTO tenants (username, email, password, role, created_at) SELECT username, email, password, 'tenant', NOW() FROM users WHERE id = ?")
                        ->execute([$userId]);
                    $tid = $pdo->lastInsertId();
                }

                // Create Contract
                $stmtCheckC = $pdo->prepare("SELECT id FROM tenant_lease_dates WHERE tenant_id = ?");
                $stmtCheckC->execute([$tid]);
                if (!$stmtCheckC->fetch()) {
                    $contractStart = date('Y-m-d');
                    $contractEnd = date('Y-m-d', strtotime("+1 year"));
                    $pdo->prepare("INSERT INTO tenant_lease_dates (tenant_id, lease_start_date, lease_expiration_date, status) VALUES (?, ?, ?, 'active')")
                        ->execute([$tid, $contractStart, $contractEnd]);
                }
            }
        }
    }

    // ============================================================
    // STEP 2: Send SMS (Independent)
    // ============================================================
    $smsSuccess = false;
    if ($tenantPhone) {
        try {
            $sms = new IPROG_SMS();
            $msgTask = ($status === 'approved') ? 'approved' : 'declined';
            $res = $sms->sendPaymentApprovalSMS($tradename, $tenantPhone, $msgTask, $remarks);
            if ($res['success']) $smsSuccess = true;
        } catch (Exception $e) { error_log("SMS Error: " . $e->getMessage()); }
    }

    // ============================================================
    // STEP 3: Send Email (Independent)
    // ============================================================
    $emailSuccess = false;
    if ($tenantEmail) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mallxentro5@gmail.com';
            $mail->Password = 'iwld cjlr kmcy bxab';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
            $mail->setFrom('mallxentro5@gmail.com', 'XentroMall');
            $mail->addAddress($tenantEmail);
            $mail->isHTML(true);

            if ($status === 'approved') {
                $mail->Subject = "Payment Approved - XentroMall";
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                        .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                        .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #10b981; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
                        .badge { display: inline-block; background: #10b981; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'><h1>Payment Approved</h1><p>Your payment has been successfully verified.</p></div>
                        <div class='content'>
                            <p>Dear <strong>$tradename</strong>,</p>
                            <p>Your payment for <strong>Payment ID #$paymentId</strong> has been successfully processed and verified by our management.</p>
                            <div class='info-box'>
                                <h3 style='margin-top: 0; color: #10b981;'>Payment Details</h3>
                                <p><strong>Status:</strong> <span class='badge'>Approved</span></p>
                            </div>
                            <p>You can now log in to your tenant dashboard to view your updated statement of account and contract details.</p>
                            <p>Thank you for your prompt payment.</p>
                            <div class='footer'>
                                <p>XentroMall Management System</p>
                                <p>© " . date('Y') . " XentroMall. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>";
            } else {
                $mail->Subject = "Payment Declined - XentroMall";
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                        .header { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                        .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                        .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #f97316; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 12px; }
                        .badge { display: inline-block; background: #f97316; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'><h1>Payment Declined</h1><p>An update regarding your recent payment submission.</p></div>
                        <div class='content'>
                            <p>Dear <strong>$tradename</strong>,</p>
                            <p>We regret to inform you that your payment for <strong>Payment ID #$paymentId</strong> has been declined after review.</p>
                            <div class='info-box'>
                                <h3 style='margin-top: 0; color: #f97316;'>Admin Remarks</h3>
                                <p>" . ($remarks ?: 'No specific reason provided. Please contact the admin office for more information.') . "</p>
                                <p><strong>Status:</strong> <span class='badge'>Declined</span></p>
                            </div>
                            <p>Please resolve the issues mentioned above and re-submit your payment proof through the portal.</p>
                            <div class='footer'>
                                <p>XentroMall Management System</p>
                                <p>© " . date('Y') . " XentroMall. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </body>
                </html>";
            }
            
            if ($mail->send()) $emailSuccess = true;
        } catch (Exception $e) { error_log("Email Error: " . $e->getMessage()); }
    }

    // ============================================================
    // STEP 4: Finalize
    // ============================================================
    $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())")
        ->execute([$userId, ($status === 'approved' ? "Payment #$paymentId approved." : "Payment #$paymentId declined.")]);

    $details = [];
    if ($emailSuccess) $details[] = "Email sent"; else if ($tenantEmail) $details[] = "Email failed"; else $details[] = "No email";
    if ($smsSuccess) $details[] = "SMS sent"; else if ($tenantPhone) $details[] = "SMS failed"; else $details[] = "No mobile";
    
    $_SESSION['success'] = "✅ Payment #$paymentId has been <strong>" . strtoupper($status) . "</strong>! " . implode(". ", $details) . ".";

} catch (Exception $e) {
    error_log("Payment Process Error: " . $e->getMessage());
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header('Location: admin_dashboard.php');
exit;
