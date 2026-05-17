<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();
require 'config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } else {
        // Check if email exists in tenant_details table
        $stmt = $pdo->prepare('SELECT td.user_id, td.tradename, u.email FROM tenant_details td 
                               INNER JOIN users u ON u.id = td.user_id 
                               WHERE td.email = :email');
        $stmt->execute(['email' => $email]);
        $tenant = $stmt->fetch();

        if ($tenant) {
            // Generate a 6-digit verification code
            $code = sprintf("%06d", mt_rand(1, 999999));
            $user_id = $tenant['user_id'];
            $tradename = $tenant['tradename'];
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            // Insert verification code in password_resets table
            $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, email, token, expires_at, used) VALUES (:user_id, :email, :token, :expires_at, 0)');
            $stmt->execute(['user_id' => $user_id, 'email' => $email, 'token' => $code, 'expires_at' => $expires_at]);

            $to = $email;
            $subject = "Password Reset Verification Code - XentroMall";
            $message = "Dear $tradename,\n\nYour password reset verification code is: $code\n\nThis code will expire in 30 minutes.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nXentroMall Management";
            $headers = "From: XentroMall <noreply@xentromall.com>\r\n";

            // Use PHPMailer to send email via SMTP
require_once __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->SMTPDebug = 0; // Turn off debugging for production
                $mail->Debugoutput = 'html';
                $mail->Host       = 'smtp.gmail.com'; // Set the SMTP server to send through
                $mail->SMTPAuth   = true;
                $mail->Username   = 'mallxentro5@gmail.com'; // SMTP username
                $mail->Password   = 'iwld cjlr kmcy bxab'; // SMTP password or app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // SSL configuration for development
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                //Recipients
                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall');
                $mail->addAddress($to);

                // Content
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
                $_SESSION['reset_email'] = $email;
                header('Location: verify_code.php');
                exit;
            } catch (Exception $e) {
                $error_message = "Failed to send verification code. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error_message = 'Email not found in tenant records. Please use your registered email.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4">
  <main class="w-full max-w-md">
    <!-- Card -->
    <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl p-8">
      <!-- Logo -->
      <div class="flex flex-col items-center mb-8">
        <div class="w-20 h-20 bg-gradient-to-br from-emerald-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="w-10 h-10">
            <path d="M15.75 1.5a.75.75 0 00-.75.75V9a.75.75 0 00.75.75h6.75a.75.75 0 00.75-.75V2.25a.75.75 0 00-.75-.75h-6.75zM1.5 15.75a.75.75 0 01.75-.75h6.75a.75.75 0 01.75.75v6.75a.75.75 0 01-.75.75H2.25a.75.75 0 01-.75-.75v-6.75z" />
            <path d="M15 9.75A.75.75 0 0115.75 9h6.75a.75.75 0 01.75.75v12a.75.75 0 01-.75.75h-6.75a.75.75 0 01-.75-.75v-12zM1.5 2.25a.75.75 0 01.75-.75h6.75a.75.75 0 01.75.75v6.75a.75.75 0 01-.75.75H2.25a.75.75 0 01-.75-.75V2.25z" />
          </svg>
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Forgot Password</h1>
        <p class="text-sm text-slate-600 mt-1">Enter your email to reset password</p>
      </div>

      <!-- Messages -->
      <?php if ($success_message): ?>
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
          <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_message); ?>
        </div>
      <?php endif; ?>
      <?php if ($error_message): ?>
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
          <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php endif; ?>

      <!-- Form -->
      <form method="post" action="forgot_password.php" class="space-y-5">
        <div>
          <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
          <div class="relative">
            <input
              type="email"
              id="email"
              name="email"
              required
              placeholder="your.email@example.com"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition"
            />
            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
              <i class="fas fa-envelope"></i>
            </div>
          </div>
        </div>

        <button
          type="submit"
          class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 to-blue-500 px-4 py-3 text-white font-semibold shadow-lg shadow-emerald-500/20 hover:from-emerald-700 hover:to-blue-600 focus:outline-none focus:ring-4 focus:ring-emerald-600/30 active:scale-[.99] transition"
        >
          <i class="fas fa-paper-plane"></i>
          <span>Send Verification Code</span>
        </button>
      </form>

      <!-- Back Link -->
      <div class="mt-6 text-center">
        <a href="login.php" class="text-sm font-medium text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-2">
          <i class="fas fa-arrow-left"></i>
          <span>Back to Login</span>
        </a>
      </div>
    </div>

    <!-- Footer -->
    <p class="mt-6 text-center text-xs text-slate-400">
      © <?php echo date('Y'); ?> XentroMall • All rights reserved
    </p>
  </main>
</body>
</html>
