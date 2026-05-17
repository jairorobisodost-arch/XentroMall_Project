<?php
// Email Debug Test Script
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h2>Email Configuration Test</h2>";

// Test 1: Check if PHPMailer is loaded
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✅ PHPMailer is loaded<br>";
} else {
    echo "❌ PHPMailer is NOT loaded<br>";
    exit;
}

// Test 2: Try to send test email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'mallxentro5@gmail.com';
    $mail->Password = 'iwld cjlr kmcy bxab';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->SMTPDebug = 2; // Enable debug output
    
    // SSL fix
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail->setFrom('mallxentro5@gmail.com', 'XentroMall Test');
    $mail->addAddress('test@example.com', 'Test Recipient');
    
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - XentroMall Debug';
    $mail->Body = 'This is a test email to debug the sending issue.';
    
    echo "Attempting to send test email...<br>";
    $mail->send();
    echo "✅ Test email sent successfully!<br>";
    
} catch (Exception $e) {
    echo "❌ Email sending failed!<br>";
    echo "Error: " . $mail->ErrorInfo . "<br>";
    echo "Exception: " . $e->getMessage() . "<br>";
    
    // Check common issues
    echo "<br><h3>Common Issues Check:</h3>";
    
    // Check if OpenSSL is enabled
    if (extension_loaded('openssl')) {
        echo "✅ OpenSSL extension is loaded<br>";
    } else {
        echo "❌ OpenSSL extension is NOT loaded - This is required for SMTP TLS/SSL<br>";
    }
    
    // Check if sockets are enabled
    if (extension_loaded('sockets')) {
        echo "✅ Sockets extension is loaded<br>";
    } else {
        echo "❌ Sockets extension is NOT loaded<br>";
    }
    
    // Check PHP version
    echo "PHP Version: " . PHP_VERSION . "<br>";
    
    // Check if we can connect to Gmail SMTP
    echo "<br>Testing SMTP connection...<br>";
    $socket = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 10);
    if ($socket) {
        echo "✅ Can connect to smtp.gmail.com:587<br>";
        fclose($socket);
    } else {
        echo "❌ Cannot connect to smtp.gmail.com:587 - Error: $errno - $errstr<br>";
    }
}

echo "<br><h3>Server Info:</h3>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Current Directory: " . __DIR__ . "<br>";
?>
