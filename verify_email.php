<?php
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$verification_message = '';
$verification_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Debug: Log the received token
    error_log("=== EMAIL VERIFICATION ATTEMPT ===");
    error_log("Received token: " . $token);
    
    // Find user with this verification token
    $stmt = $pdo->prepare('SELECT id, email, username, verification_token_expires FROM users WHERE verification_token = :token AND role = :role');
    $stmt->execute(['token' => $token, 'role' => 'tenant']);
    $user = $stmt->fetch();
    
    // Debug: Log if user was found
    if ($user) {
        error_log("User found: ID={$user['id']}, Email={$user['email']}");
        error_log("Token expires: {$user['verification_token_expires']}");
        error_log("Current time: " . date('Y-m-d H:i:s'));
    } else {
        error_log("No user found with token: $token");
    }
    
    if ($user) {
        // Check if token has expired
        if (strtotime($user['verification_token_expires']) > time()) {
            // Token is valid, verify the email
            $stmt = $pdo->prepare('UPDATE users SET email_verified = TRUE, verification_token = NULL, verification_token_expires = NULL WHERE id = :id');
            $stmt->execute(['id' => $user['id']]);
            
            $verification_status = 'success';
            $verification_message = "
                <div class='bg-green-50 border border-green-200 rounded-lg p-6 text-center'>
                    <div class='mb-4'>
                        <svg class='w-16 h-16 text-green-500 mx-auto' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'></path>
                        </svg>
                    </div>
                    <h2 class='text-2xl font-bold text-green-800 mb-2'>Email Verified Successfully!</h2>
                    <p class='text-green-700 mb-4'>
                        Thank you, <strong>" . htmlspecialchars($user['username']) . "</strong>! Your email address has been verified.
                    </p>
                    <p class='text-green-600 mb-6'>
                        Your application is now pending admin review. You will be notified once your application is approved.
                    </p>
                    <div class='space-y-3'>
                        <a href='login.php' class='inline-block bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors'>
                            Go to Login
                        </a>
                        <div class='text-sm text-green-600'>
                            <p>You can now login with your credentials.</p>
                            <p class='mt-1'>Status: <strong>Waiting for Admin Approval</strong></p>
                        </div>
                    </div>
                </div>
            ";
            
            // Log successful verification
            error_log("✅ Email verified successfully for user ID: {$user['id']}, Email: {$user['email']}");
            
        } else {
            // Token has expired
            $verification_status = 'expired';
            $verification_message = "
                <div class='bg-red-50 border border-red-200 rounded-lg p-6 text-center'>
                    <div class='mb-4'>
                        <svg class='w-16 h-16 text-red-500 mx-auto' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'></path>
                        </svg>
                    </div>
                    <h2 class='text-2xl font-bold text-red-800 mb-2'>Verification Link Expired</h2>
                    <p class='text-red-700 mb-6'>
                        The verification link has expired. Please request a new verification email.
                    </p>
                    <div class='space-y-3'>
                        <a href='resend_verification.php?email=" . urlencode($user['email']) . "' class='inline-block bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors'>
                            Request New Verification Email
                        </a>
                        <div class='text-sm text-red-600'>
                            <a href='login.php' class='text-red-600 hover:underline'>Back to Login</a>
                        </div>
                    </div>
                </div>
            ";
            
            // Log expired token
            error_log("❌ Verification token expired for user ID: {$user['id']}, Email: {$user['email']}");
        }
    } else {
        // Invalid token
        $verification_status = 'invalid';
        $verification_message = "
            <div class='bg-red-50 border border-red-200 rounded-lg p-6 text-center'>
                <div class='mb-4'>
                    <svg class='w-16 h-16 text-red-500 mx-auto' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>
                    </svg>
                </div>
                <h2 class='text-2xl font-bold text-red-800 mb-2'>Invalid Verification Link</h2>
                <p class='text-red-700 mb-6'>
                    The verification link is invalid or has already been used.
                </p>
                <div class='space-y-3'>
                    <a href='login.php' class='inline-block bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors'>
                        Back to Login
                    </a>
                    <div class='text-sm text-red-600'>
                        If you need assistance, please contact support.
                    </div>
                </div>
            </div>
        ";
        
        // Log invalid token attempt
        error_log("❌ Invalid verification token attempted: $token");
    }
} else {
    // No token provided
    $verification_status = 'no_token';
    $verification_message = "
        <div class='bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center'>
            <div class='mb-4'>
                <svg class='w-16 h-16 text-yellow-500 mx-auto' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.314 16.5c-.77.833.192 2.5 1.732 2.5z'></path>
                </svg>
            </div>
            <h2 class='text-2xl font-bold text-yellow-800 mb-2'>Verification Required</h2>
            <p class='text-yellow-700 mb-6'>
                Please check your email for the verification link.
            </p>
            <div class='space-y-3'>
                <a href='login.php' class='inline-block bg-yellow-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-yellow-700 transition-colors'>
                    Back to Login
                </a>
                <div class='text-sm text-yellow-600'>
                    If you didn't receive the email, please check your spam folder or request a new one.
                </div>
            </div>
        </div>
    ";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
        }
        
        .verification-container {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-animation {
            animation: successPulse 2s ease-in-out infinite;
        }
        
        @keyframes successPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo and Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white rounded-full shadow-lg mb-4">
                <img src="img/logo.jpg" alt="XentroMall" class="w-16 h-16 object-contain">
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">XentroMall</h1>
            <p class="text-gray-300">Tenant Management System</p>
        </div>
        
        <!-- Verification Message -->
        <div class="verification-container">
            <?php echo $verification_message; ?>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-gray-400 text-sm">
                © <?php echo date('Y'); ?> XentroMall • All rights reserved
            </p>
            <div class="mt-2">
                <a href="contact.php" class="text-gray-400 hover:text-white text-sm transition-colors">
                    Need Help? Contact Support
                </a>
            </div>
        </div>
    </div>
    
    <!-- Additional JavaScript for animations -->
    <script>
        // Add success animation if verification was successful
        <?php if ($verification_status === 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.verification-container > div');
            if (container) {
                container.classList.add('success-animation');
            }
        });
        <?php endif; ?>
        
        // Auto-redirect to login after 10 seconds on successful verification
        <?php if ($verification_status === 'success'): ?>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>
