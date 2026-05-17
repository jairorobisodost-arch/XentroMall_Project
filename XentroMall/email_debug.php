<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

echo "<h1>🔍 Email Debug System</h1>";

// Step 1: Check PHPMailer installation
echo "<h2>Step 1: PHPMailer Check</h2>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "✅ PHPMailer is installed<br>";
} else {
    echo "❌ PHPMailer is NOT installed<br>";
    exit;
}

// Step 2: Test basic Gmail connection
echo "<h2>Step 2: Gmail Connection Test</h2>";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'xentromall@gmail.com';
    $mail->Password = 'jlou wjlj qkbv nkfv';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // SSL fix
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    echo "✅ SMTP configuration set<br>";
    
    // Test connection
    if ($mail->smtpConnect()) {
        echo "✅ Gmail SMTP connection successful<br>";
        
        // Step 3: Test actual email send
        echo "<h2>Step 3: Test Email Send</h2>";
        
        $mail->setFrom('xentromall@gmail.com', 'XentroMall Debug');
        $mail->addAddress('jairorobiso.dost@gmail.com', 'Debug Test');
        $mail->Subject = '🔍 DEBUG TEST - ' . date('Y-m-d H:i:s');
        $mail->Body = 'This is a debug test email from XentroMall system.';
        
        if ($mail->send()) {
            echo "✅ Email sent successfully to jairorobiso.dost@gmail.com<br>";
        } else {
            echo "❌ Email send failed: " . $mail->ErrorInfo . "<br>";
        }
        
    } else {
        echo "❌ Gmail SMTP connection failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "❌ PHPMailer Error: " . $mail->ErrorInfo . "<br>";
}

// Step 4: Test PHP mail() function
echo "<h2>Step 4: PHP mail() Test</h2>";

$to = 'jairorobiso.dost@gmail.com';
$subject = '🔍 PHP mail() DEBUG TEST - ' . date('Y-m-d H:i:s');
$message = 'This is a debug test email using PHP mail() function.';
$headers = 'From: xentromall@gmail.com' . "\r\n" .
           'Reply-To: xentromall@gmail.com' . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

if (mail($to, $subject, $message, $headers)) {
    echo "✅ PHP mail() sent successfully<br>";
} else {
    echo "❌ PHP mail() failed<br>";
    echo "⚠️ Note: PHP mail() requires local mail server configuration<br>";
}

// Step 5: Check Gmail account requirements
echo "<h2>Step 5: Gmail Account Checklist</h2>";
echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<h3>Required Gmail Settings:</h3>";
echo "<ol>";
echo "<li>✅ Enable 'Less secure apps': <a href='https://myaccount.google.com/lesssecureapps' target='_blank'>https://myaccount.google.com/lesssecureapps</a></li>";
echo "<li>✅ If 2FA is enabled, generate App Password: <a href='https://myaccount.google.com/apppasswords' target='_blank'>https://myaccount.google.com/apppasswords</a></li>";
echo "<li>✅ Check if Gmail account exists and is accessible</li>";
echo "<li>✅ Verify username and password are correct</li>";
echo "</ol>";
echo "</div>";

// Step 6: Alternative solutions
echo "<h2>Step 6: Alternative Solutions</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h3>If Gmail still doesn't work:</h3>";
echo "<ul>";
echo "<li>🔄 Use a different Gmail account</li>";
echo "<li>🔄 Use SendGrid (free 100 emails/day)</li>";
echo "<li>🔄 Use Mailgun (free tier)</li>";
echo "<li>🔄 Configure local SMTP server (hMailServer)</li>";
echo "<li>🔄 Use transactional email service</li>";
echo "</ul>";
echo "</div>";

// Step 7: Test with different SMTP settings
echo "<h2>Step 7: Alternative SMTP Settings</h2>";

// Test with port 465 and SSL
try {
    $mail2 = new PHPMailer(true);
    $mail2->isSMTP();
    $mail2->Host = 'smtp.gmail.com';
    $mail2->SMTPAuth = true;
    $mail2->Username = 'xentromall@gmail.com';
    $mail2->Password = 'jlou wjlj qkbv nkfv';
    $mail2->SMTPSecure = 'ssl';
    $mail2->Port = 465;
    
    $mail2->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    $mail2->setFrom('xentromall@gmail.com', 'XentroMall Debug');
    $mail2->addAddress('jairorobiso.dost@gmail.com');
    $mail2->Subject = '🔍 SSL Port 465 DEBUG TEST';
    $mail2->Body = 'Test with SSL port 465';
    
    if ($mail2->send()) {
        echo "✅ SSL Port 465 test successful<br>";
    } else {
        echo "❌ SSL Port 465 test failed: " . $mail2->ErrorInfo . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ SSL Port 465 error: " . $e->getMessage() . "<br>";
}

echo "<h2>📋 Summary</h2>";
echo "<p>Run this debug page to identify the exact issue with email sending.</p>";
echo "<p>Check the results above to see which method works best.</p>";
?>
