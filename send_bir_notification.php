<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';
require_once 'sms_integration.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantId = $_POST['tenant_id'] ?? null;
    $type = $_POST['type'] ?? 'single'; // 'single' or 'all_expiring'

    if ($type === 'single' && !$tenantId) {
        echo json_encode(['success' => false, 'message' => 'Missing Tenant ID.']);
        exit;
    }

    try {
        $tenantsToNotify = [];

        if ($type === 'single') {
            $stmt = $pdo->prepare("
                SELECT td.*, u.username, u.email as user_email 
                FROM tenant_details td
                JOIN users u ON td.user_id = u.id
                WHERE td.id = ?
            ");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant)
                $tenantsToNotify[] = $tenant;
        }
        else {
            // Fetch all approved tenants with expired or expiring BIR (within 30 days)
            $stmt = $pdo->prepare("
                SELECT td.*, u.username, u.email as user_email 
                FROM tenant_details td
                JOIN users u ON td.user_id = u.id
                WHERE td.status = 'approved' 
                AND td.bir_expiry_date IS NOT NULL
                AND (
                    td.bir_expiry_date < CURDATE() 
                    OR (td.bir_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                )
            ");
            $stmt->execute();
            $tenantsToNotify = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($tenantsToNotify)) {
            echo json_encode(['success' => true, 'message' => 'No tenants to notify.', 'count' => 0]);
            exit;
        }

        $successCount = 0;
        $smsEnabled = true; // Set to false if SMS API is not ready

        foreach ($tenantsToNotify as $tenant) {
            $email = !empty($tenant['email']) ? $tenant['email'] : $tenant['user_email'];
            $mobile = $tenant['mobile'];
            $tradename = $tenant['tradename'];
            $userId = $tenant['user_id'];
            $birExpiry = $tenant['bir_expiry_date'];

            // Determine status
            $status = 'expiring_soon';
            if ($birExpiry) {
                $today = new DateTime();
                $expiryDate = new DateTime($birExpiry);
                if ($expiryDate < $today)
                    $status = 'expired';
            }
            $expiryDateFormatted = $birExpiry ? date('F j, Y', strtotime($birExpiry)) : 'Not Set';

            // 1. Send Email
            $mailSent = false;
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mallxentro5@gmail.com';
                $mail->Password = 'iwld cjlr kmcy bxab';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall Management');
                $mail->addAddress($email);
                $mail->isHTML(true);

                if ($status === 'expired') {
                    $mail->Subject = "⚠️ URGENT: BIR Registration Expired - $tradename | XentroMall";
                    $bodyTitle = "BIR Registration Expired";
                    $bodyText = "Your BIR registration expired on <strong>$expiryDateFormatted</strong>. Please update your records immediately to avoid penalties and potential lease issues.";
                    $alertClass = "background-color: #fee2e2; border-color: #f87171; color: #991b1b;";
                }
                else {
                    $mail->Subject = "📄 Reminder: BIR Registration Renewal Due - $tradename | XentroMall";
                    $bodyTitle = "BIR Registration Renewal";
                    $bodyText = "Your BIR registration is due for renewal on <strong>$expiryDateFormatted</strong>. Please process your renewal as soon as possible and submit the updated documents to the mall administration.";
                    $alertClass = "background-color: #fef3c7; border-color: #fbbf24; color: #92400e;";
                }

                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0;'>
                    <div style='max-width: 600px; margin: 20px auto; padding: 30px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);'>
                        <div style='text-align: center; margin-bottom: 25px;'>
                            <h2 style='color: #059669; margin: 0; font-size: 24px; font-weight: 800;'>XentroMall</h2>
                            <p style='color: #64748b; font-size: 14px; margin-top: 5px;'>Mall Management System</p>
                        </div>
                        <div style='padding: 24px; border-radius: 12px; margin-bottom: 25px; border-left: 4px solid; $alertClass'>
                            <h3 style='margin-top: 0; font-size: 18px; font-weight: 700;'>$bodyTitle</h3>
                            <p style='margin-bottom: 0; font-size: 15px;'>$bodyText</p>
                        </div>
                        <p style='font-size: 16px; margin-bottom: 10px;'>Dear <strong>$tradename</strong>,</p>
                        <p style='font-size: 15px; color: #475569;'>This is an automated reminder regarding your BIR registration requirements for your stall at XentroMall.</p>
                        <p style='font-size: 15px; color: #475569;'>Ensuring your business documents are up-to-date is a key requirement of your lease agreement. Please take action immediately by renewing your registration and uploading the latest documents to your tenant dashboard.</p>
                        <div style='text-align: center; margin: 35px 0;'>
                            <a href='" . BASE_URL . "login.php' style='background-color: #0ea5e9; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 10px; font-weight: 700; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(14, 165, 233, 0.2);'>Access Tenant Dashboard</a>
                        </div>
                        <div style='margin-top: 40px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-align: center;'>
                            &copy; " . date('Y') . " XentroMall Management System<br>
                            This is an automated message. Please do not reply directly to this email.
                        </div>
                    </div>
                </body>
                </html>";
                $mail->send();
                $mailSent = true;
            }
            catch (Exception $e) {
                error_log("PHPMailer Error for $tradename: " . $mail->ErrorInfo);
            }

            // 2. Send SMS
            $smsSent = false;
            if ($smsEnabled && !empty($mobile)) {
                try {
                    $sms = new IPROG_SMS();
                    $smsResult = $sms->sendBIRNotificationSMS($tradename, $mobile, $status, $expiryDateFormatted);
                    $smsSent = $smsResult['success'];
                }
                catch (Exception $e) {
                    error_log("SMS Error for $tradename: " . $e->getMessage());
                }
            }

            // 3. System Notification
            $notifMessage = ($status === 'expired')
                ? "🚨 Your BIR registration has EXPIRED on $expiryDateFormatted. Please update it immediately."
                : "📄 Your BIR registration will expire on $expiryDateFormatted. Please process your renewal.";

            try {
                $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                $stmtNotif->execute([$userId, $notifMessage]);
                $successCount++;
            }
            catch (Exception $e) {
                error_log("Database Error for $tradename: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Successfully processed $successCount notifications.",
            'count' => $successCount
        ]);

    }
    catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method.']);
}
