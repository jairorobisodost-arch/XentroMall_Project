<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sms_integration.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends a payment reminder to a tenant via Email and SMS.
 *
 * @param int $userId The tenant's user ID.
 * @param string $dueDate The formatted due date (e.g., "March 5, 2026").
 * @param float|null $amount Optional amount due.
 * @return array Results of sending attempts.
 */
function sendPaymentReminder($userId, $dueDate, $amount = null, $customMessage = '')
{
    global $pdo;

    $results = [
        'email' => ['success' => false, 'message' => ''],
        'sms' => ['success' => false, 'message' => '']
    ];

    try {
        // Fetch tenant contact details
        $stmt = $pdo->prepare("SELECT tradename, email, mobile FROM tenant_details WHERE user_id = ?");
        $stmt->execute([$userId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            throw new Exception("Tenant details not found for User ID: $userId");
        }

        $tradename = $tenant['tradename'];
        $email = $tenant['email'];
        $mobile = $tenant['mobile'];

        // 1. Send Email Notification
        if (!empty($email)) {
            $results['email'] = sendPaymentReminderEmail($email, $tradename, $dueDate, $amount, $customMessage);
        }
        else {
            $results['email']['message'] = "No email address found.";
        }

        // 2. Send SMS Notification
        if (!empty($mobile)) {
            $sms = new IPROG_SMS();
            $smsResult = $sms->sendPaymentReminderSMS($tradename, $mobile, $dueDate, $amount, $customMessage);
            $results['sms']['success'] = $smsResult['success'];
            $results['sms']['message'] = $smsResult['success'] ? "SMS sent successfully." : "SMS failed: " . $smsResult['response'];
        }
        else {
            $results['sms']['message'] = "No mobile number found.";
        }

    }
    catch (Exception $e) {
        error_log("Payment Reminder Error (User ID $userId): " . $e->getMessage());
        $results['error'] = $e->getMessage();
    }

    return $results;
}

/**
 * Sends the payment reminder email using PHPMailer.
 */
function sendPaymentReminderEmail($recipientEmail, $tradename, $dueDate, $amount = null, $customMessage = '')
{
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP Debug [$level]: $str");
        };
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mallxentro5@gmail.com';
        $mail->Password = 'iwld cjlr kmcy bxab';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('mallxentro5@gmail.com', 'XentroMall');
        $mail->addAddress($recipientEmail);

        $mail->isHTML(true);
        $mail->Subject = "🔔 Payment Reminder: Your XentroMall Rent is Due on $dueDate";

        $amountHtml = $amount ? "<p style='font-size: 18px; color: #111827;'><strong>Amount Due: ₱" . number_format($amount, 2) . "</strong></p>" : "";

        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                .header { background: #3b82f6; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 12px 12px; }
                .message-box { background: white; padding: 25px; margin: 20px 0; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                .btn { display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin:0;'>Payment Reminder</h1>
                </div>
                <div class='content'>
                    <p class='greeting'>Hello $tradename,</p>
                    <p>This is a friendly reminder regarding your monthly rental payment for your space at <strong>XentroMall</strong>.</p>
                    
                    <div class='message-box'>
                        <p style='margin-top: 0; color: #4b5563;'>Payment Due Date:</p>
                        <p style='font-size: 20px; color: #111827; font-weight: bold; margin: 5px 0;'>$dueDate</p>
                        $amountHtml
                        <p>Ensuring your payments are up-to-date is essential for maintaining your lease and enjoying uninterrupted access to XentroMall services.</p>
                    </div>
                    
                    " . (!empty($customMessage) ? "
                    <div style='background: #fefce8; border-left: 4px solid #eab308; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                        <p style='margin: 0; color: #854d0e; font-size: 14px;'><strong>Administrative Note:</strong></p>
                        <p style='margin: 5px 0 0 0; color: #713f12; font-style: italic;'>\"$customMessage\"</p>
                    </div>" : "") . "

                    <p>Please ensure that your payment is settled on or before the due date to avoid any late payment fees or service interruptions.</p>
                    
                    <div style='text-align: center;'>
                        <a href='" . BASE_URL . "tenant_dashboard.php' class='btn'>View Dashboard & Pay</a>
                    </div>

                    <div class='footer'>
                        <p>If you have already made the payment, please ignore this message.</p>
                        <p>&copy; " . date('Y') . " XentroMall Management. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $emailBody;

        if ($mail->send()) {
            return ['success' => true, 'message' => "Email sent successfully."];
        }
    }
    catch (Exception $e) {
        return ['success' => false, 'message' => "Email exception: " . $e->getMessage()];
    }
    return ['success' => false, 'message' => "Unknown email error."];
}
