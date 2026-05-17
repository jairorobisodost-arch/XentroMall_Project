<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$id || !$action || !in_array($action, ['approve', 'decline'])) {
    $_SESSION['error'] = "Invalid request.";
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Get BIR submission details with tenant email from tenant_details
    $stmt = $pdo->prepare("SELECT eb.*, td.tradename, td.email, td.mobile
                           FROM extended_bir_submissions eb
                           INNER JOIN tenant_details td ON td.user_id = eb.user_id
                           WHERE eb.id = ?");
    $stmt->execute([$id]);
    $birSubmission = $stmt->fetch();
    
    if (!$birSubmission) {
        $_SESSION['error'] = "BIR submission not found.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    $tenantEmail = $birSubmission['email'];
    $tenantName = $birSubmission['tradename'];
    
    // Update status
    $newStatus = $action === 'approve' ? 'approved' : 'declined';
    $birExpiryDate = $_GET['bir_expiry_date'] ?? null;
    
    $stmtUpdate = $pdo->prepare("UPDATE extended_bir_submissions 
                                 SET status = ?, processed_at = NOW() 
                                 WHERE id = ?");
    $stmtUpdate->execute([$newStatus, $id]);
    
    if ($newStatus === 'approved' && !empty($birExpiryDate)) {
        $stmtUpdateTD = $pdo->prepare("UPDATE tenant_details SET bir_expiry_date = ? WHERE user_id = ?");
        $stmtUpdateTD->execute([$birExpiryDate, $birSubmission['user_id']]);
    }
    
    // Create notification for tenant
    $message = $action === 'approve' 
        ? "Your Extended BIR documents have been approved by the admin."
        : "Your Extended BIR documents have been declined. Please contact admin for more information.";
    
    $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
    $stmtNotif->execute([$birSubmission['user_id'], $message]);
    
    // Prepare email content
    if ($action === 'approve') {
        $emailSubject = "✅ Extended BIR Documents Approved - XentroMall";
        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                .greeting { font-size: 16px; color: #1f2937; margin-bottom: 20px; }
                .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #10b981; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .info-box h3 { margin-top: 0; color: #10b981; font-size: 18px; }
                .badge { display: inline-block; background: #10b981; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                .success-icon { font-size: 48px; text-align: center; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Documents Approved!</h1>
                    <p>Extended BIR Submission Status</p>
                </div>
                <div class='content'>
                    <div class='success-icon'>🎉</div>
                    <p class='greeting'>Dear <strong>{$tenantName}</strong>,</p>
                    <p style='font-size: 15px; color: #374151;'>
                        Great news! Your Extended BIR documents have been <strong style='color: #10b981;'>approved</strong> by the admin.
                    </p>
                    
                    <div class='info-box'>
                        <h3>📄 Document Status</h3>
                        <p style='margin: 8px 0;'><strong>Submission ID:</strong> #{$id}</p>
                        <p style='margin: 8px 0;'><strong>Status:</strong> <span class='badge'>✓ Approved</span></p>
                        <p style='margin: 8px 0;'><strong>Processed:</strong> " . date('F j, Y g:i A') . "</p>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 5px solid #10b981; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                        <h3 style='color: #059669; margin-top: 0; font-size: 16px;'>✅ What's Next?</h3>
                        <p style='margin: 8px 0; color: #374151;'>• Your contract renewal process is now <strong>complete</strong></p>
                        <p style='margin: 8px 0; color: #374151;'>• All documents are approved and on file</p>
                        <p style='margin: 8px 0; color: #374151;'>• You can continue your business operations</p>
                    </div>
                    
                    <p style='font-size: 15px; color: #374151; margin-top: 25px;'>
                        Thank you for your prompt submission and compliance with our requirements.
                    </p>
                    
                    <div class='footer'>
                        <p style='margin: 5px 0;'>This is an automated message from XentroMall Management System</p>
                        <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                        <p style='margin: 15px 0 0 0; color: #9ca3af;'>For questions, please contact our administration office.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    } else {
        $emailSubject = "❌ Extended BIR Documents Declined - XentroMall";
        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                .greeting { font-size: 16px; color: #1f2937; margin-bottom: 20px; }
                .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #ef4444; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                .info-box h3 { margin-top: 0; color: #ef4444; font-size: 18px; }
                .badge { display: inline-block; background: #ef4444; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                .alert-box { background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px; margin: 20px 0; color: #991b1b; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Document Status Update</h1>
                    <p>Extended BIR Submission Review</p>
                </div>
                <div class='content'>
                    <p class='greeting'>Dear <strong>{$tenantName}</strong>,</p>
                    <p style='font-size: 15px; color: #374151;'>
                        We regret to inform you that your Extended BIR documents have been <strong style='color: #ef4444;'>declined</strong>.
                    </p>
                    
                    <div class='info-box'>
                        <h3>📄 Document Status</h3>
                        <p style='margin: 8px 0;'><strong>Submission ID:</strong> #{$id}</p>
                        <p style='margin: 8px 0;'><strong>Status:</strong> <span class='badge'>✗ Declined</span></p>
                        <p style='margin: 8px 0;'><strong>Processed:</strong> " . date('F j, Y g:i A') . "</p>
                    </div>
                    
                    <div class='alert-box'>
                        <strong>⚠️ Action Required:</strong><br>
                        Please contact the XentroMall administration office to discuss the reasons for the decline and 
                        what steps you need to take to resubmit your documents.
                    </div>
                    
                    <div style='background: #fef3c7; border-left: 5px solid #f59e0b; padding: 20px; margin: 25px 0; border-radius: 8px;'>
                        <h3 style='color: #92400e; margin-top: 0; font-size: 16px;'>📞 Contact Information</h3>
                        <p style='margin: 8px 0; color: #78350f;'>• Visit the admin office during business hours</p>
                        <p style='margin: 8px 0; color: #78350f;'>• Bring your original documents for verification</p>
                        <p style='margin: 8px 0; color: #78350f;'>• Our staff will guide you through the resubmission process</p>
                    </div>
                    
                    <p style='font-size: 15px; color: #374151; margin-top: 25px;'>
                        We apologize for any inconvenience and look forward to resolving this matter with you.
                    </p>
                    
                    <div class='footer'>
                        <p style='margin: 5px 0;'>This is an automated message from XentroMall Management System</p>
                        <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                        <p style='margin: 15px 0 0 0; color: #9ca3af;'>For assistance, please contact our administration office.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // Send email using PHPMailer
    error_log("=== EXTENDED BIR EMAIL SENDING START === ID: $id, Action: $action");
    error_log("Tenant Email: " . ($tenantEmail ?: 'NULL'));
    
    if ($tenantEmail) {
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug [$level]: $str");
            };
            
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mallxentro5@gmail.com';
            $mail->Password = 'iwld cjlr kmcy bxab';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->Timeout = 30;
            
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
            
            error_log("Attempting to send Extended BIR email...");

            if($mail->send()) {
                $logMsg = date('Y-m-d H:i:s') . " - ✅ EXTENDED BIR EMAIL SENT to $tenantEmail for submission ID: $id ($action)\n";
                file_put_contents(__DIR__ . '/bir_email_log.txt', $logMsg, FILE_APPEND);
                error_log("✅✅✅ EXTENDED BIR EMAIL SENT SUCCESSFULLY to $tenantEmail");
            } else {
                $logMsg = date('Y-m-d H:i:s') . " - ❌ EMAIL FAILED: " . $mail->ErrorInfo . "\n";
                file_put_contents(__DIR__ . '/bir_email_log.txt', $logMsg, FILE_APPEND);
                error_log("❌❌❌ EMAIL FAILED - Mailer Error: " . $mail->ErrorInfo);
            }
        } catch (Exception $e) {
            $logMsg = date('Y-m-d H:i:s') . " - ❌ EXCEPTION: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/bir_email_log.txt', $logMsg, FILE_APPEND);
            error_log("❌❌❌ EXCEPTION while sending Extended BIR email");
            error_log("Exception Message: " . $e->getMessage());
        }
    } else {
        $logMsg = date('Y-m-d H:i:s') . " - ⚠️ NO EMAIL for BIR submission ID: $id\n";
        file_put_contents(__DIR__ . '/bir_email_log.txt', $logMsg, FILE_APPEND);
        error_log("⚠️⚠️⚠️ NO EMAIL ADDRESS FOUND for BIR submission ID: $id");
    }
    
    error_log("=== EXTENDED BIR EMAIL SENDING END ===");
    
    $successMessage = $action === 'approve' 
        ? "Extended BIR documents approved successfully!"
        : "Extended BIR documents declined.";
    
    $_SESSION['success'] = $successMessage;
    header('Location: admin_dashboard.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error processing request: " . $e->getMessage();
    header('Location: admin_dashboard.php');
    exit;
}
?>
