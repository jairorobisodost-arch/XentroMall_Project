<?php
session_start();
require 'config.php';

if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $email = $_SESSION['reset_email'];

    if (empty($code)) {
        $error_message = 'Please enter the verification code.';
    } else {
        // Debug: Check all records for this email
        $stmtDebug = $pdo->prepare('SELECT * FROM password_resets WHERE email = :email ORDER BY created_at DESC LIMIT 1');
        $stmtDebug->execute(['email' => $email]);
        $debugReset = $stmtDebug->fetch();
        
        // Check if code is valid and not expired
        // Use string comparison instead of NOW() to avoid timezone issues
        $currentTime = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE email = :email AND token = :token AND used = 0 AND expires_at > :current_time ORDER BY created_at DESC LIMIT 1');
        $stmt->execute(['email' => $email, 'token' => $code, 'current_time' => $currentTime]);
        $reset = $stmt->fetch();

        if ($reset) {
            $_SESSION['verified_code'] = $code;
            header('Location: reset_password.php');
            exit;
        } else {
            // Debug info
            if ($debugReset) {
                $error_message = 'Invalid or expired verification code. ';
                $error_message .= 'You entered: "' . $code . '" | ';
                $error_message .= 'Expected: "' . $debugReset['token'] . '" | ';
                $error_message .= 'Match: ' . ($code === $debugReset['token'] ? 'YES' : 'NO') . ' | ';
                $error_message .= 'Expires: ' . $debugReset['expires_at'] . ' | ';
                $error_message .= 'Used: ' . $debugReset['used'] . ' | ';
                $error_message .= 'Expired: ' . ($debugReset['expires_at'] < date('Y-m-d H:i:s') ? 'YES' : 'NO');
            } else {
                $error_message = 'No verification code found for this email.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Verify Code - XentroMall</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        body {
            background: url('img/bg.jpg') no-repeat center center fixed;
            background-size: cover;
            color: #ffffff;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: "";
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 0;
        }
        .container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background-color: rgba(30, 30, 30, 0.85);
            border: none;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 400px;
            width: 100%;
        }
        .form-control,
        .form-control:focus {
            background-color: rgba(44, 44, 44, 0.85);
            color: #fff;
            border: 1px solid #444;
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
        }
        .form-control::placeholder {
            color: #aaa;
        }
        .btn-info {
            background-color: #0dcaf0;
            border: none;
        }
        .btn-info:hover {
            background-color: #31d2f2;
        }
        a {
            color: #0dcaf0;
        }
        a:hover {
            color: #31d2f2;
        }
        h2 {
            margin-bottom: 1rem;
            color: #0dcaf0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h2>Enter Verification Code</h2>
        <p class="text-muted">A 6-digit code has been sent to <?php echo htmlspecialchars($_SESSION['reset_email']); ?></p>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <form method="post" action="verify_code.php">
            <div class="mb-3">
                <label for="code" class="form-label">Verification Code</label>
                <input type="text" class="form-control" id="code" name="code" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required />
            </div>
            <button type="submit" class="btn btn-info w-100">Verify Code</button>
        </form>
        <div class="mt-3 text-center">
            <a href="forgot_password.php">Resend Code</a> | 
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
