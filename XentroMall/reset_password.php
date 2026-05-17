<?php
session_start();
require 'config.php';

// Check if user has verified code
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['verified_code'])) {
    header('Location: forgot_password.php');
    exit;
}

$email = $_SESSION['reset_email'];
$code = $_SESSION['verified_code'];
$error_message = '';
$success_message = '';

// Get user_id from tenant_details
$stmt = $pdo->prepare('SELECT user_id FROM tenant_details WHERE email = :email');
$stmt->execute(['email' => $email]);
$tenant = $stmt->fetch();

if (!$tenant) {
    $error_message = 'User not found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error_message)) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($password) || empty($confirm_password)) {
        $error_message = 'Please fill in all password fields.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } else {
        // Update user's password
        $user_id = $tenant['user_id'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :user_id');
        $stmt->execute(['password' => $hashed_password, 'user_id' => $user_id]);

        // Mark token as used
        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = :email AND token = :token');
        $stmt->execute(['email' => $email, 'token' => $code]);

        // Clear session
        unset($_SESSION['reset_email']);
        unset($_SESSION['verified_code']);

        $success_message = 'Your password has been reset successfully. You can now <a href="login.php">login</a>.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password - XentroMall</title>
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
            <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
          </svg>
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reset Password</h1>
        <p class="text-sm text-slate-600 mt-1">Enter your new password</p>
      </div>

      <!-- Messages -->
      <?php if ($error_message): ?>
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
          <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php elseif ($success_message): ?>
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm">
          <i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?>
        </div>
        <div class="text-center">
          <a href="login.php" class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 to-blue-500 px-6 py-3 text-white font-semibold shadow-lg hover:from-emerald-700 hover:to-blue-600 transition">
            <i class="fas fa-sign-in-alt"></i>
            <span>Go to Login</span>
          </a>
        </div>
      <?php else: ?>
        <!-- Form -->
        <form method="post" action="" class="space-y-5">
          <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
            <div class="relative">
              <input
                type="password"
                id="password"
                name="password"
                required
                placeholder="Enter new password"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition"
              />
              <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                <i class="fas fa-lock"></i>
              </div>
            </div>
          </div>

          <div>
            <label for="confirm_password" class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
            <div class="relative">
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                required
                placeholder="Confirm new password"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/20 transition"
              />
              <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                <i class="fas fa-lock"></i>
              </div>
            </div>
          </div>

          <button
            type="submit"
            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 to-blue-500 px-4 py-3 text-white font-semibold shadow-lg shadow-emerald-500/20 hover:from-emerald-700 hover:to-blue-600 focus:outline-none focus:ring-4 focus:ring-emerald-600/30 active:scale-[.99] transition"
          >
            <i class="fas fa-key"></i>
            <span>Reset Password</span>
          </button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <p class="mt-6 text-center text-xs text-slate-400">
      © <?php echo date('Y'); ?> XentroMall • All rights reserved
    </p>
  </main>
</body>
</html>
