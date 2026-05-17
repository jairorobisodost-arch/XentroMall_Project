<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

echo "<h1>Email Configuration Test</h1>";

// Test different SMTP configurations
$configs = [
    'gmail_tls' => [
        'Host' => 'smtp.gmail.com',
        'Port' => 587,
        'SMTPSecure' => 'tls',
        'description' => 'Gmail with TLS'
    ],
    'gmail_ssl' => [
        'Host' => 'smtp.gmail.com', 
        'Port' => 465,
        'SMTPSecure' => 'ssl',
        'description' => 'Gmail with SSL'
    ],
    'gmail_no_secure' => [
        'Host' => 'smtp.gmail.com',
        'Port' => 25,
        'SMTPSecure' => '',
        'description' => 'Gmail without encryption'
    ]
];

foreach ($configs as $name => $config) {
    echo "<h3>Testing: {$config['description']}</h3>";
    
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config['Host'];
        $mail->SMTPAuth = true;
        $mail->Username = 'xentromall@gmail.com';
        $mail->Password = 'jlou wjlj qkbv nkfv';
        $mail->SMTPSecure = $config['SMTPSecure'];
        $mail->Port = $config['Port'];
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            echo "<div style='font-size: 12px; color: #666;'>" . htmlspecialchars($str) . "</div>";
        };
        
        $mail->setFrom('xentromall@gmail.com', 'Test');
        $mail->addAddress('test@example.com');
        
        $mail->Subject = "Test - {$config['description']}";
        $mail->Body = "Test email";
        
        $mail->send();
        echo "<div style='color: green;'>✅ SUCCESS: {$config['description']}</div>";
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>❌ FAILED: {$config['description']} - {$mail->ErrorInfo}</div>";
    }
    
    echo "<hr>";
}

echo "<h2>Gmail Account Checklist:</h2>";
echo "<ul>";
echo "<li>✅ Enable 'Less secure apps' at: https://myaccount.google.com/lesssecureapps</li>";
echo "<li>✅ If 2FA is enabled, generate App Password at: https://myaccount.google.com/apppasswords</li>";
echo "<li>✅ Check if Gmail account is blocked or has sending limits</li>";
echo "<li>✅ Verify username and password are correct</li>";
echo "</ul>";

echo "<h2>Alternative Solutions:</h2>";
echo "<ul>";
echo "<li>🔄 Use a different Gmail account</li>";
echo "<li>🔄 Use SendGrid (free tier available)</li>";
echo "<li>🔄 Use Mailgun (free tier available)</li>";
echo "<li>🔄 Use local mail server (like hMailServer)</li>";
echo "</ul>";
?>
