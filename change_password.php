<?php
session_start();
require 'config.php';

$message = '';
$error = '';
$step = 1; // 1: enter email, 2: enter code, 3: change password

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        // Step 1: User submitted email to receive code
        $email = $_POST['email'];
        // Check if email exists and is admin
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = :role');
        $stmt->execute(['email' => $email, 'role' => 'admin']);
        $user = $stmt->fetch();

        if ($user) {
            // Generate code and store in session
            $code = rand(100000, 999999);
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_user_id'] = $user['id'];

            // Send code via PHPMailer SMTP
            require 'vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.example.com'; // Set your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'your_email@example.com'; // SMTP username
                $mail->Password   = 'your_email_password'; // SMTP password
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('no-reply@xentromall.com', 'XentroMall');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Code';
                $mail->Body    = "Your password reset code is: <b>$code</b>";

                $mail->send();
                $message = "A verification code has been sent to your email.";
                $step = 2;
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                $step = 1;
            }
        } else {
            $error = "Email not found or not an admin.";
            $step = 1;
        }
    } elseif (isset($_POST['code'])) {
        // Step 2: User submitted code to verify
        $code = $_POST['code'];
        if (isset($_SESSION['reset_code']) && $code == $_SESSION['reset_code']) {
            $message = "Code verified. Please enter your new password.";
            $step = 3;
        } else {
            $error = "Invalid verification code.";
            $step = 2;
        }
    } elseif (isset($_POST['new_password'], $_POST['confirm_password'])) {
        // Step 3: User submitted new password
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password === $confirm_password) {
            if (isset($_SESSION['reset_user_id'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->execute(['password' => $hashed_password, 'id' => $_SESSION['reset_user_id']]);
                
                $message = "Password changed successfully. You can now login.";
                $step = 4; // Or any step > 3 to show the success view
                
                // Clear session reset info
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_code']);
                unset($_SESSION['reset_user_id']);
            } else {
                $error = "Session expired. Please start over.";
                $step = 1;
            }
        } else {
            $error = "Passwords do not match.";
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-md mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Change Password</h1>
        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-200 text-green-800 rounded"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-200 text-red-800 rounded"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST" action="change_password.php" class="space-y-4">
                <div>
                    <label for="email" class="block font-semibold mb-1">Enter your email</label>
                    <input type="email" id="email" name="email" required class="w-full border border-gray-300 rounded p-2" />
                </div>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Send Verification Code</button>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="POST" action="change_password.php" class="space-y-4">
                <div>
                    <label for="code" class="block font-semibold mb-1">Enter verification code</label>
                    <input type="text" id="code" name="code" required class="w-full border border-gray-300 rounded p-2" />
                </div>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Verify Code</button>
            </form>
        <?php elseif ($step === 3): ?>
            <form method="POST" action="change_password.php" class="space-y-4">
                <div>
                    <label for="new_password" class="block font-semibold mb-1">New Password</label>
                    <input type="password" id="new_password" name="new_password" required class="w-full border border-gray-300 rounded p-2" />
                </div>
                <div>
                    <label for="confirm_password" class="block font-semibold mb-1">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required class="w-full border border-gray-300 rounded p-2" />
                </div>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Change Password</button>
            </form>
        <?php else: ?>
            <a href="login.php" class="text-blue-600 hover:underline">Return to Login</a>
        <?php endif; ?>

        <a href="admin_dashboard.php" class="inline-block mt-4 text-blue-600 hover:underline">Back to Dashboard</a>
    </div>
</body>
</html>
