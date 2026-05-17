<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$renewalId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if (!$renewalId || !in_array($action, ['approve', 'decline'])) {
    header('Location: admin_dashboard.php');
    exit;
}

try {
    // Get renewal request details
    $stmtRenewal = $pdo->prepare("SELECT cr.*, td.email, td.tradename 
                                   FROM contract_renewals cr
                                   INNER JOIN tenant_details td ON td.user_id = cr.user_id
                                   WHERE cr.id = ? AND cr.status = 'pending'");
    $stmtRenewal->execute([$renewalId]);
    $renewal = $stmtRenewal->fetch();
    
    if (!$renewal) {
        $_SESSION['error'] = "Renewal request not found or already processed.";
        header('Location: admin_dashboard.php');
        exit;
    }
    
    if ($action === 'approve') {
        // Get contract duration from settings
        $stmtSettings = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'contract_duration_months'");
        $stmtSettings->execute();
        $contractDuration = $stmtSettings->fetchColumn() ?: 12;
        
        // Calculate new contract dates
        $newStart = date('Y-m-d');
        $newEnd = date('Y-m-d', strtotime("+{$contractDuration} months"));
        
        // Update renewal request
        $stmtUpdate = $pdo->prepare("UPDATE contract_renewals 
                                     SET status = 'approved', 
                                         new_contract_start = ?, 
                                         new_contract_end = ?, 
                                         processed_at = NOW() 
                                     WHERE id = ?");
        $stmtUpdate->execute([$newStart, $newEnd, $renewalId]);
        
        // Update tenant_lease_dates
        $stmtContract = $pdo->prepare("UPDATE tenant_lease_dates 
                                       SET lease_start_date = ?, 
                                           lease_expiration_date = ?, 
                                           status = 'active',
                                           renewal_reminder_sent = 0,
                                           late_renewal_fee = 0.00,
                                           updated_at = NOW()
                                       WHERE tenant_id = ?");
        $stmtContract->execute([$newStart, $newEnd, $renewal['tenant_id']]);
        
        // Send notification
        $message = "Your contract renewal has been approved! New contract period: " . 
                   date('M j, Y', strtotime($newStart)) . " to " . date('M j, Y', strtotime($newEnd));
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $stmtNotif->execute([$renewal['user_id'], $message]);
        
        // Send email
        $emailSubject = "Contract Renewal Approved - XentroMall";
        $emailBody = "<h2>Contract Renewal Approved</h2>
                     <p>Dear {$renewal['tradename']},</p>
                     <p>Your contract renewal has been <strong>approved</strong>!</p>
                     <p><strong>New Contract Period:</strong><br>
                     Start: " . date('F j, Y', strtotime($newStart)) . "<br>
                     End: " . date('F j, Y', strtotime($newEnd)) . "</p>
                     <p>Thank you for continuing with us!</p>
                     <br>
                     <p>Best regards,<br>XentroMall Management</p>";
        
        $_SESSION['success'] = "Renewal request approved successfully!";
        
    } else {
        // Decline renewal
        $stmtUpdate = $pdo->prepare("UPDATE contract_renewals 
                                     SET status = 'declined', 
                                         processed_at = NOW() 
                                     WHERE id = ?");
        $stmtUpdate->execute([$renewalId]);
        
        // Send notification
        $message = "Your contract renewal request has been declined. Please contact the admin for more information.";
        $stmtNotif = $pdo->prepare("INSERT INTO notifications (user_id, message, is_read) VALUES (?, ?, 0)");
        $stmtNotif->execute([$renewal['user_id'], $message]);
        
        // Send email
        $emailSubject = "Contract Renewal Declined - XentroMall";
        $emailBody = "<h2>Contract Renewal Declined</h2>
                     <p>Dear {$renewal['tradename']},</p>
                     <p>Your contract renewal request has been <strong>declined</strong>.</p>
                     <p>Please contact the admin for more information.</p>
                     <br>
                     <p>Best regards,<br>XentroMall Management</p>";
        
        $_SESSION['error'] = "Renewal request declined.";
    }
    
    // Send email notification
    if ($renewal['email']) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'jairopogirobiso@gmail.com';
            $mail->Password = 'wedi stuc gbbz qisl';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            $mail->setFrom('jairopogirobiso@gmail.com', 'XentroMall');
            $mail->addAddress($renewal['email']);
            
            $mail->isHTML(true);
            $mail->Subject = $emailSubject;
            $mail->Body = $emailBody;
            
            $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    error_log("Renewal approval error: " . $e->getMessage());
}

header('Location: admin_dashboard.php');
exit;
?>
