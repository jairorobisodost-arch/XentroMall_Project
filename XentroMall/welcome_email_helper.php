<?php
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Sends a welcome email to a new tenant.
 *
 * @param string $tenantEmail The recipient's email address.
 * @param string $tradename The tenant's business or trade name.
 * @param string $stallNumber The stall number assigned to the tenant.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function sendWelcomeEmail($tenantEmail, $tradename, $stallNumber) {
    if (empty($tenantEmail)) {
        error_log("Welcome Email Error: Email address is empty for $tradename");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'mallxentro5@gmail.com';
        $mail->Password = 'iwld cjlr kmcy bxab';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Timeout = 30;

        // SSL certificate verification bypass (as seen in existing code)
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

        // Content
        $mail->isHTML(true);
        $mail->Subject = "🎉 Welcome to XentroMall, $tradename!";

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
                .greeting { font-size: 18px; color: #111827; margin-bottom: 20px; font-weight: 600; }
                .message-box { background: white; padding: 25px; margin: 25px 0; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
                .stall-badge { display: inline-block; background: #ecfdf5; color: #059669; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 700; border: 1px solid #d1fae5; }
                .footer { text-align: center; margin-top: 35px; padding-top: 25px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                .steps { margin: 25px 0; padding: 0; list-style: none; }
                .step-item { display: flex; align-items: flex-start; margin-bottom: 15px; }
                .step-icon { background: #10b981; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 12px; flex-shrink: 0; }
                .step-text { color: #4b5563; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome Aboard!</h1>
                    <p>Official Tenant Notification</p>
                </div>
                <div class='content'>
                    <div class='greeting'>Hello $tradename,</div>
                    <p style='font-size: 15px; color: #374151;'>
                        We are thrilled to officially welcome you as a tenant at <strong>XentroMall</strong>! Your application and payment have been processed, and your space is ready.
                    </p>

                    <div class='message-box'>
                        <h3 style='margin-top: 0; color: #111827; font-size: 16px;'>📍 Your Assigned Space</h3>
                        <p style='margin: 10px 0; color: #6b7280; font-size: 14px;'>You have been assigned to:</p>
                        <div class='stall-badge'>Stall #$stallNumber</div>
                    </div>

                    <h3 style='color: #111827; font-size: 16px; margin-top: 30px;'>🚀 Getting Started</h3>
                    <div class='steps'>
                        <div class='step-item'>
                            <div class='step-icon'>1</div>
                            <div class='step-text'><strong>Access Dashboard:</strong> Log in to your tenant portal using your registered credentials.</div>
                        </div>
                        <div class='step-item'>
                            <div class='step-icon'>2</div>
                            <div class='step-text'><strong>Review Contract:</strong> Your lease agreement is now available for viewing and download.</div>
                        </div>
                        <div class='step-item'>
                            <div class='step-icon'>3</div>
                            <div class='step-text'><strong>Settle In:</strong> Coordinate with mall management for your physical move-in schedule.</div>
                        </div>
                    </div>

                    <p style='font-size: 15px; color: #374151; margin-top: 30px;'>
                        We look forward to a successful partnership and seeing your business thrive at XentroMall.
                    </p>

                    <div class='footer'>
                        <p style='margin: 5px 0;'>Automated message from XentroMall Management System</p>
                        <p style='margin: 5px 0;'>&copy; " . date('Y') . " XentroMall. All rights reserved.</p>
                        <p style='margin: 15px 0 0 0; color: #9ca3af;'>Need help? Contact our administration office.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $emailBody;

        if ($mail->send()) {
            error_log("Welcome Email Sent: Successfully sent to $tenantEmail for $tradename");
            return true;
        }
    } catch (Exception $e) {
        error_log("Welcome Email Exception: " . $e->getMessage());
    }
    return false;
}
