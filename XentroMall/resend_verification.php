<?php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
    } else {
        try {
            // Check if user exists and is not verified
            $stmt = $pdo->prepare('SELECT u.id, u.username, td.tradename FROM users u LEFT JOIN tenant_details td ON u.id = td.user_id WHERE u.email = :email AND u.role = :role AND u.email_verified = :verified');
            $stmt->execute(['email' => $email, 'role' => 'tenant', 'verified' => false]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $message = 'Email not found or already verified. Please check your email or try logging in.';
            } else {
                // Generate new verification token
                $verificationToken = bin2hex(random_bytes(32));
                $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Update user with new token
                $stmt = $pdo->prepare('UPDATE users SET verification_token = :token, verification_token_expires = :expires WHERE id = :id');
                $stmt->execute(['token' => $verificationToken, 'expires' => $tokenExpires, 'id' => $user['id']]);
                
                // Send verification email
                $verificationLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/xentromall/verify_email.php?token=' . $verificationToken;
                
                $mail = new PHPMailer(true);
                
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'mallxentro5@gmail.com';
                    $mail->Password = 'iwld cjlr kmcy bxab';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
                    
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                    
                    $mail->setFrom('mallxentro5@gmail.com', 'XentroMall Management');
                    $mail->addAddress($email, $user['tradename']);
                    $mail->isHTML(true);
                    
                    $mail->Subject = 'Verify Your Email Address - XentroMall Registration';
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
                            <div style='background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h2 style='color: #10b981; margin: 0;'>XentroMall</h2>
                                    <p style='color: #6b7280; margin: 5px 0 0 0;'>Tenant Management System</p>
                                </div>
                                
                                <h3 style='color: #1f2937; margin-bottom: 20px;'>Resend: Verify Your Email Address</h3>
                                
                                <p style='color: #4b5563; line-height: 1.6; margin-bottom: 25px;'>
                                    You requested to resend the verification email. Please click the button below to verify your email address and complete your registration.
                                </p>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='$verificationLink' style='background: linear-gradient(135deg, #10b981, #3b82f6); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                                        Verify Email Address
                                    </a>
                                </div>
                                
                                <p style='color: #6b7280; font-size: 14px; text-align: center; margin-bottom: 20px;'>
                                    This link will expire in 24 hours.
                                </p>
                                
                                <div style='background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb;'>
                                    <p style='color: #6b7280; font-size: 12px; margin: 0 0 8px 0; text-align: center;'>
                                        If the button above doesn't work, copy and paste this link into your browser:
                                    </p>
                                    <p style='color: #1f2937; font-size: 11px; margin: 0; word-break: break-all; text-align: center; font-family: monospace; background: #f3f4f6; padding: 8px; border-radius: 4px;'>
                                        $verificationLink
                                    </p>
                                </div>
                                
                                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;'>
                                    <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                                        This is an automated message from XentroMall Management System<br>
                                        © " . date('Y') . " XentroMall. All rights reserved.
                                    </p>
                                </div>
                            </div>
                        </div>
                    ";
                    
                    if($mail->send()) {
                        $success = true;
                        $message = 'Verification email sent successfully! Please check your inbox.';
                    } else {
                        $message = 'Failed to send verification email. Please try again later.';
                    }
                    
                } catch (Exception $e) {
                    $message = 'Email sending error: ' . $e->getMessage();
                    error_log("Resend verification email error: " . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $message = 'An error occurred. Please try again later.';
            error_log('Resend verification error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Resend Verification Email - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
        }
        .hero-bg {
            position: fixed;
            inset: 0;
            z-index: -10;
            overflow: hidden;
        }
        .hero-bg::before {
            content: "";
            position: absolute;
            inset: 0;
            background: url('img/bg.jpg') center/cover no-repeat;
            filter: brightness(0.55) saturate(1.1);
            transform: scale(1.02);
        }
        .hero-bg::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,.25), transparent 60%),
                        radial-gradient(900px 500px at 90% 110%, rgba(34,197,94,.20), transparent 60%),
                        linear-gradient(180deg, rgba(2,6,23,.65), rgba(2,6,23,.65));
            mix-blend-mode: screen;
        }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <div class="hero-bg pointer-events-none" aria-hidden="true"></div>

    <main class="relative z-10 min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <!-- Card -->
            <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5">
                <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 0% 0%, rgba(59,130,246,.08), transparent 50%), radial-gradient(700px 400px at 100% 100%, rgba(16,185,129,.08), transparent 50%);"></div>

                <div class="relative p-8 sm:p-10">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="h-12 w-12 rounded-xl overflow-hidden ring-1 ring-black/10 shadow-sm bg-white/60 flex items-center justify-center mx-auto mb-4">
                            <img src="img/logo.jpg" alt="XentroMall" class="h-10 w-10 object-cover" />
                        </div>
                        <h1 class="text-2xl font-semibold text-slate-900 mb-2">Resend Verification Email</h1>
                        <p class="text-sm text-slate-600">Enter your email to receive a new verification link</p>
                    </div>

                    <!-- Message -->
                    <?php if (!empty($message)): ?>
                        <div class="mb-5 rounded-lg border <?php echo $success ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'; ?> px-4 py-3 text-sm">
                            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                        <!-- Form -->
                        <form action="resend_verification.php" method="post" class="space-y-5">
                            <div>
                                <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                                <div class="relative">
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        required
                                        placeholder="your@email.com"
                                        class="peer w-full rounded-lg border border-slate-300 bg-white/90 px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition"
                                    />
                                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="submit"
                                name="resend"
                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-emerald-500 px-4 py-3 text-white font-semibold shadow-lg hover:from-blue-700 hover:to-emerald-600 transition"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"/>
                                </svg>
                                Send Verification Email
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Links -->
                    <div class="mt-6 text-center space-y-2">
                        <div class="text-sm text-slate-600">
                            <a href="login.php" class="font-medium text-blue-600 hover:text-blue-700">← Back to Login</a>
                        </div>
                        <div class="text-sm text-slate-600">
                            Don't have an account?
                            <a href="user_stall_page.php" class="font-medium text-blue-600 hover:text-blue-700">Register</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-6 text-center text-xs text-slate-400">
                © <?php echo date('Y'); ?> XentroMall • All rights reserved
            </p>
        </div>
    </main>
</body>
</html>
