<?php
require 'config.php';
require 'vendor/autoload.php';
require_once 'sms_integration.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Email & SMS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        .success { color: green; }
        .error { color: red; }
        .result { margin: 10px 0; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>Test Email & SMS Notifications</h1>
    
    <div class="test-section">
        <h2>Test Email</h2>
        <form method="post">
            <input type="email" name="test_email" placeholder="Enter email address" required>
            <button type="submit" name="test_email_send">Send Test Email</button>
        </form>
        
        <?php
        if (isset($_POST['test_email_send'])) {
            $testEmail = $_POST['test_email'];
            echo "<div class='result'>";
            
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'xentromall@gmail.com';
                $mail->Password = 'jlou wjlj qkbv nkfv';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                // Fix SSL certificate verification issue
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );
                
                $mail->setFrom('xentromall@gmail.com', 'XentroMall Administration');
                $mail->addAddress($testEmail);
                
                $mail->isHTML(true);
                $mail->Subject = 'Test Email - XentroMall';
                $mail->Body = "<h1>Test Email</h1><p>This is a test email from XentroMall notification system.</p>";
                
                $mail->send();
                echo "<div class='success'>✅ Email sent successfully to {$testEmail}</div>";
            } catch (Exception $e) {
                echo "<div class='error'>❌ Email failed: " . $mail->ErrorInfo . "</div>";
            }
            echo "</div>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>Test SMS</h2>
        <form method="post">
            <input type="text" name="test_mobile" placeholder="Enter mobile number (09xxxxxxxxx)" required>
            <button type="submit" name="test_sms_send">Send Test SMS</button>
        </form>
        
        <?php
        if (isset($_POST['test_sms_send'])) {
            $testMobile = $_POST['test_mobile'];
            echo "<div class='result'>";
            
            try {
                $smsService = new IPROG_SMS();
                $smsMessage = "This is a test SMS from XentroMall notification system.";
                $smsResult = $smsService->sendSMS($testMobile, $smsMessage);
                
                if ($smsResult['success']) {
                    echo "<div class='success'>✅ SMS sent successfully to {$testMobile}</div>";
                    echo "<div>Response: " . htmlspecialchars($smsResult['response']) . "</div>";
                } else {
                    echo "<div class='error'>❌ SMS failed to {$testMobile}</div>";
                    echo "<div>HTTP Code: {$smsResult['http_code']}</div>";
                    echo "<div>Response: " . htmlspecialchars($smsResult['response']) . "</div>";
                }
            } catch (Exception $e) {
                echo "<div class='error'>❌ SMS error: " . $e->getMessage() . "</div>";
            }
            echo "</div>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>Check Error Logs</h2>
        <p>Check the PHP error logs for detailed debugging information:</p>
        <button onclick="window.open('data:text/plain,' + encodeURIComponent('Check your XAMPP logs at: C:\\xampp\\apache\\logs\\error.log'))">View Log Location</button>
    </div>
</body>
</html>
