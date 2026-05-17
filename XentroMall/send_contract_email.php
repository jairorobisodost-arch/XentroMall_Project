<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contract_email'])) {
    $contractId = $_POST['contract_id'] ?? 0;
    
    if ($contractId > 0) {
        try {
            // Get tenant email from tenant_details
            $stmt = $pdo->prepare("
                SELECT tc.*, td.email, td.contact_person, td.tradename
                FROM tenant_contracts tc
                INNER JOIN tenant_details td ON td.id = tc.tenant_detail_id
                WHERE tc.id = ?
            ");
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contract && $contract['email']) {
                $tenantEmail = $contract['email'];
                $lesseeName = $contract['contact_person'] ?: $contract['tradename'];
                
                // Create simple HTML email
                $subject = 'Your Stall Lease Agreement - XentroMall';
                $viewLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/view_contract.php?id=' . $contractId;
                
                $message = "
                <html>
                <head>
                    <title>Stall Lease Agreement</title>
                </head>
                <body>
                    <p>Good day {$lesseeName},</p>
                    <p>Your stall lease agreement with XentroMall is ready for review.</p>
                    <p><a href='{$viewLink}' style='background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Lease Agreement</a></p>
                    <p>Please review the contract and coordinate with mall management for any questions or signing arrangements.</p>
                    <p>Best regards,<br>XentroMall Management Team</p>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
                $headers .= "From: mallxentro5@gmail.com" . "\r\n";
                $headers .= "Reply-To: mallxentro5@gmail.com" . "\r\n";
                
                if (mail($tenantEmail, $subject, $message, $headers)) {
                    $_SESSION['success_message'] = 'Contract successfully sent to tenant email!';
                } else {
                    $_SESSION['error_message'] = 'Email sending failed. Please use Copy Link option.';
                }
            } else {
                $_SESSION['error_message'] = 'Tenant email not found.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
    }
    
    header('Location: view_contract.php?id=' . $contractId);
    exit;
}
?>
