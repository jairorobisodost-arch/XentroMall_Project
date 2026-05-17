<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/application_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';


// Handle GET requests from admin dashboard (direct approval links)
if (isset($_GET['id']) && isset($_GET['status'])) {
    $type = 'application';
    $id = $_GET['id'];
    $status = $_GET['status'];
    $remarks = $_GET['remarks'] ?? 'Processed by admin';

    // File-based logging
    $logFile = __DIR__ . '/approval_log.txt';
    $logMessage = date('Y-m-d H:i:s') . " - GET Request: ID=$id, Status=$status\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);

    error_log("=== APPROVAL PROCESS STARTED (GET) ===");
    error_log("Type: $type, ID: $id, Status: $status");

    // Process the approval (same logic as POST)
    $_POST['submit'] = true;
    $_POST['type'] = $type;
    $_POST['application_id'] = $id;
    $_POST['status'] = $status;
    $_POST['remarks'] = $remarks;
}

if (isset($_POST['submit'])) {
    $type = $_POST['type'] ?? 'application';
    $id = $_POST['application_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $remarks = $_POST['remarks'] ?? '';

    error_log("=== APPROVAL PROCESS STARTED ===");
    error_log("Type: $type, ID: $id, Status: $status");

    if ($type === 'application') {
        error_log("Processing APPLICATION approval for ID: $id");
        // Update tenant application status and feedback
        $sql = 'UPDATE tenant_details SET status = :status, admin_feedback = :admin_feedback WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $data = [
            'id' => $id,
            'status' => $status,
            'admin_feedback' => $remarks
        ];
        $results = $stmt->execute($data);

        // Log status change to history
        $stmtSC = $pdo->prepare('SELECT user_id, COALESCE(submission_count, 1) as submission_count FROM tenant_details WHERE id = :id');
        $stmtSC->execute(['id' => $id]);
        $scData = $stmtSC->fetch();
        if ($scData) {
            logApplicationStatus($pdo, $id, $scData['user_id'], $status, $remarks, $scData['submission_count']);
        }

        // Get user_id from tenant_details first
        $stmtGetUser = $pdo->prepare('SELECT user_id, email, tradename FROM tenant_details WHERE id = :id');
        $stmtGetUser->execute(['id' => $id]);
        $tenantData = $stmtGetUser->fetch();
        $userId = $tenantData['user_id'] ?? null;
        $tenantEmail = $tenantData['email'] ?? null;
        $tradename = $tenantData['tradename'] ?? 'Tenant';

        // Use users table email as FALLBACK if tenant_details email is empty
        if (empty($tenantEmail) && $userId) {
            $stmtUser = $pdo->prepare('SELECT email, username FROM users WHERE id = :user_id');
            $stmtUser->execute(['user_id' => $userId]);
            $userInfo = $stmtUser->fetch();
            $tenantEmail = $userInfo['email'] ?? null;

            // Use username as fallback for tradename
            if (empty($tradename) && !empty($userInfo['username'])) {
                $tradename = $userInfo['username'];
            }
        }

        // Log email info for debugging
        error_log("Application #$id - User ID: " . ($userId ?: 'NULL') . ", Email: " . ($tenantEmail ?: 'NOT FOUND') . ", Tradename: $tradename");

        // ============================================================
        // STEP 1: Create contract (INDEPENDENT of email/SMS)
        // ============================================================
        if ($status === 'approved') {
            $tenantRecord = null;

            // Try by email first
            if ($tenantEmail) {
                $stmtTenant = $pdo->prepare('SELECT id FROM tenants WHERE email = :email');
                $stmtTenant->execute(['email' => $tenantEmail]);
                $tenantRecord = $stmtTenant->fetch();
            }

            // Fallback: try by user_id if no email match
            if (!$tenantRecord && $userId) {
                $stmtTenant = $pdo->prepare('SELECT id FROM tenants WHERE user_id = :user_id');
                $stmtTenant->execute(['user_id' => $userId]);
                $tenantRecord = $stmtTenant->fetch();
            }

            if ($tenantRecord) {
                $tenantId = $tenantRecord['id'];

                // Check if contract already exists
                $stmtCheck = $pdo->prepare('SELECT tenant_id FROM tenant_lease_dates WHERE tenant_id = :tenant_id');
                $stmtCheck->execute(['tenant_id' => $tenantId]);

                if (!$stmtCheck->fetch()) {
                    $startDate = date('Y-m-d');
                    $endDate = date('Y-m-d', strtotime('+1 year'));

                    $stmtContract = $pdo->prepare('
                        INSERT INTO tenant_lease_dates (tenant_id, lease_start_date, lease_expiration_date, status) 
                        VALUES (:tenant_id, :start_date, :end_date, :status)
                    ');
                    $stmtContract->execute([
                        'tenant_id' => $tenantId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'active'
                    ]);
                    error_log("✅ Contract created for tenant ID: $tenantId");
                }
            } else {
                error_log("⚠️ No tenant record found for contract creation. User ID: " . ($userId ?: 'NULL') . ", Email: " . ($tenantEmail ?: 'NULL'));
            }
        }

        $subject = ' Application Status Update - XentroMall';

        // Create professional email body for application
        if ($status === 'approved') {
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #10b981; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                    .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                    .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #10b981; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                    .badge { display: inline-block; background: #10b981; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0;'>Application Approved!</h1>
                        <p style='margin: 10px 0 0 0;'>XentroMall Management System</p>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>$tradename</strong>,</p>
                        <p>Congratulations! Your application has been <strong>approved</strong>.</p>
                        
                        <div class='info-box'>
                            <h3 style='margin-top: 0; color: #10b981;'>Application Details</h3>
                            <p><strong>Application ID:</strong> #$id</p>
                            <p><strong>Status:</strong> <span class='badge'>Approved</span></p>
                            <p><strong>Admin Remarks:</strong> $remarks</p>
                        </div>
                        
                        <p>You can now proceed with the next steps. Please log in to your tenant dashboard for more information.</p>
                        
                        <p style='margin-top: 30px;'>Welcome to XentroMall!</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from XentroMall Management System</p>
                        <p>© " . date('Y') . " XentroMall. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #f97316; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                    .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #f97316; }
                    .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                    .badge { display: inline-block; background: #f97316; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1 style='margin: 0;'>Application Status Update</h1>
                        <p style='margin: 10px 0 0 0;'>XentroMall Management System</p>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>$tradename</strong>,</p>
                        <p>We regret to inform you that your application has been <strong>declined</strong>.</p>
                        
                        <div class='info-box'>
                            <h3 style='margin-top: 0; color: #f97316;'>Application Details</h3>
                            <p><strong>Application ID:</strong> #$id</p>
                            <p><strong>Status:</strong> <span class='badge'>Declined</span></p>
                            <p><strong>Admin Remarks:</strong> $remarks</p>
                        </div>
                        
                        <p>For more information, please contact the XentroMall administration office.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message from XentroMall Management System</p>
                        <p>© " . date('Y') . " XentroMall. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
        }
    } elseif ($type === 'payment') {
        // Update payment status and remarks
        $sql = 'UPDATE payments SET status = :status, remarks = :remarks WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $data = [
            'id' => $id,
            'status' => $status,
            'remarks' => $remarks
        ];
        if (!$stmt->execute($data)) {
            error_log("Failed to update payment status for payment ID $id");
        }

        // Fetch tenant email from database
        $stmtEmail = $pdo->prepare('SELECT u.email FROM payments p JOIN users u ON p.user_id = u.id WHERE p.id = :id');
        $stmtEmail->execute(['id' => $id]);
        $tenantEmail = $stmtEmail->fetchColumn();

        $subject = 'Payment Status Update';
        $body = "Payment ID: $id<br>Status: $status<br>Remarks: $remarks";

        // Insert notification for tenant
        $stmtUser = $pdo->prepare('SELECT user_id FROM payments WHERE id = :id');
        $stmtUser->execute(['id' => $id]);
        $userId = $stmtUser->fetchColumn();

        if ($userId) {
            $notificationMessage = "Your payment (ID: $id) has been $status. Remarks: $remarks";
            $stmtNotif = $pdo->prepare('INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())');
            $stmtNotif->execute([$userId, $notificationMessage]);
        }
    } else {
        // Unknown type
        header('Location: admin_dashboard.php');
        exit;
    }

    // ============================================================
    // STEP 2: Send SMS notification (INDEPENDENT of email)
    // ============================================================
    $smsResult = null;
    $tenantMobile = null;

    try {
        $stmtMobile = $pdo->prepare("SELECT mobile FROM tenant_details WHERE id = ?");
        $stmtMobile->execute([$id]);
        $tenantMobile = $stmtMobile->fetchColumn();

        if ($tenantMobile) {
            require_once 'sms_integration.php';
            $sms = new IPROG_SMS();

            if ($status === 'approved') {
                $smsMessage = "XENTROMALL: Congratulations! Your application #$id has been APPROVED. Welcome to XentroMall! Please check your email or dashboard for details.";
            } else {
                $smsMessage = "XENTROMALL: Your application #$id has been DECLINED. Remarks: $remarks. Please contact admin office for more info.";
            }

            $smsResult = $sms->sendSMS($tenantMobile, $smsMessage);

            if ($smsResult['success']) {
                error_log("✅ SMS sent successfully to $tenantMobile for application #$id");
            } else {
                error_log("❌ SMS failed to send to $tenantMobile: " . $smsResult['response']);
            }
        } else {
            error_log("⚠️ No mobile number found for tenant ID: $id");
        }
    } catch (Exception $e) {
        error_log("SMS sending error for application #$id: " . $e->getMessage());
    }

    // ============================================================
    // STEP 3: Send email notification (INDEPENDENT of SMS)
    // ============================================================
    $emailSent = false;
    error_log("=== EMAIL SENDING START === Type: $type, ID: $id, Email: " . ($tenantEmail ?: 'NULL'));

    if ($tenantEmail) {
        $mail = new PHPMailer(true);

        try {
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mallxentro5@gmail.com';
            $mail->Password = 'iwld cjlr kmcy bxab';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->Timeout = 30;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom('mallxentro5@gmail.com', 'XentroMall');
            $mail->addAddress($tenantEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            $mail->send();
            $emailSent = true;

            $logMsg = date('Y-m-d H:i:s') . " - ✅ EMAIL SENT to $tenantEmail for $type ID: $id\n";
            file_put_contents(__DIR__ . '/approval_log.txt', $logMsg, FILE_APPEND);
            error_log("✅ EMAIL SENT SUCCESSFULLY to $tenantEmail for $type ID: $id");
        } catch (Exception $e) {
            $logMsg = date('Y-m-d H:i:s') . " - ❌ EXCEPTION: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/approval_log.txt', $logMsg, FILE_APPEND);
            error_log("❌ EXCEPTION while sending email to $tenantEmail: " . $e->getMessage());
        }
    } else {
        $logMsg = date('Y-m-d H:i:s') . " - ⚠️ NO EMAIL for $type ID: $id\n";
        file_put_contents(__DIR__ . '/approval_log.txt', $logMsg, FILE_APPEND);
        error_log("⚠️ NO EMAIL ADDRESS FOUND for $type ID: $id - Email NOT sent!");
    }

    error_log("=== EMAIL SENDING END ===");

    // ============================================================
    // STEP 4: Build accurate status message
    // ============================================================
    $smsSent = ($smsResult && isset($smsResult['success']) && $smsResult['success']);
    $statusLabel = (strtolower($status) === 'approved') ? 'APPROVED' : 'DECLINED';
    $icon = (strtolower($status) === 'approved') ? '✅' : '⚠️';

    $notifications = [];
    if ($emailSent) $notifications[] = 'Email sent';
    if ($smsSent) $notifications[] = 'SMS sent';
    if (!$emailSent && $tenantEmail) $notifications[] = 'Email failed';
    if (!$emailSent && !$tenantEmail) $notifications[] = 'No email on file';
    if (!$smsSent && $tenantMobile) $notifications[] = 'SMS failed';
    if (!$smsSent && !$tenantMobile) $notifications[] = 'No mobile on file';
    $notifSummary = implode('. ', $notifications) . '.';

    if ($emailSent || $smsSent) {
        $_SESSION['success'] = "$icon Application #$id has been <strong>$statusLabel</strong>! $notifSummary";
    } else {
        $_SESSION['warning'] = "⚠️ Application #$id has been <strong>$statusLabel</strong>. $notifSummary";
    }

    // Redirect after processing
    header('Location: admin_dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Review - Xentro Mall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
        }

        .gradient-text {
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-back {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .input-field {
            transition: all 0.3s ease;
        }

        .input-field:focus {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .success-modal {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .checkmark {
            animation: checkmark 0.5s ease-in-out 0.3s both;
        }

        @keyframes checkmark {
            0% {
                transform: scale(0);
            }

            50% {
                transform: scale(1.2);
            }

            100% {
                transform: scale(1);
            }
        }
    </style>
</head>

<body class="flex items-center justify-center p-4">

    <!-- Success Modal -->
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 success-modal">
            <div class="glass-card rounded-2xl p-8 max-w-md w-full mx-4 text-center">
                <!-- Xentro Mall Logo -->
                <div class="mb-6">
                    <img src="img/logo.jpg" alt="Xentro Mall Logo" class="w-32 h-auto mx-auto mb-4 rounded-lg">
                </div>

                <!-- Success Icon -->
                <div
                    class="checkmark inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-emerald-400 to-green-500 rounded-full mb-4">
                    <i class="fas fa-check text-white text-4xl"></i>
                </div>

                <!-- Success Message -->
                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    <?php echo ucfirst(htmlspecialchars($_GET['status'])); ?> Successfully!
                </h2>
                <p class="text-gray-600 mb-4">
                    Application #<?php echo htmlspecialchars($_GET['id']); ?> has been
                    <span class="font-semibold"><?php echo htmlspecialchars($_GET['status']); ?></span>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-envelope mr-1"></i>
                    Email notification sent to tenant
                </p>

                <!-- Redirect Message -->
                <div class="mt-6 text-sm text-gray-500">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Redirecting to dashboard in <span id="countdown">3</span> seconds...
                </div>
            </div>
        </div>

        <script>
            // Countdown and redirect
            let seconds = 3;
            const countdownElement = document.getElementById('countdown');

            const countdown = setInterval(() => {
                seconds--;
                countdownElement.textContent = seconds;

                if (seconds <= 0) {
                    clearInterval(countdown);
                    window.location.href = 'admin_dashboard.php';
                }
            }, 1000);
        </script>
    <?php endif; ?>
    <div class="w-full max-w-md">
        <!-- Back Button -->
        <button onclick="window.location.href='admin_dashboard.php'"
            class="btn-back text-white px-6 py-3 rounded-xl mb-6 flex items-center gap-2 font-semibold">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Dashboard</span>
        </button>

        <!-- Main Card -->
        <div class="glass-card rounded-2xl p-6 shadow-2xl">
            <!-- Header -->
            <div class="text-center mb-6">
                <div
                    class="inline-flex items-center justify-center w-12 h-12 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-full mb-3">
                    <i class="fas fa-clipboard-check text-white text-lg"></i>
                </div>
                <h1 class="text-2xl font-bold gradient-text mb-1">Application Review</h1>
                <p class="text-gray-600 text-sm">Review and update application status</p>
            </div>

            <!-- Form -->
            <form action="approval.php" method="post" class="space-y-4">
                <!-- Application ID -->
                <div>
                    <label for="id" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-hashtag text-blue-500 mr-2"></i>Application ID
                    </label>
                    <input type="text" name="application_id" id="id"
                        value="<?= isset($_GET['id']) ? htmlspecialchars($_GET['id']) : '' ?>" readonly
                        class="w-full px-4 py-3 bg-gray-100 border border-gray-300 rounded-xl focus:outline-none cursor-not-allowed">
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-check-circle text-emerald-500 mr-2"></i>Status
                    </label>
                    <select
                        class="input-field w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        name="status" id="status" required>
                        <option value="" selected disabled>Select Status</option>
                        <option value="approved" class="text-green-600">✓ Approved</option>
                        <option value="declined" class="text-red-600">✗ Declined</option>
                    </select>
                </div>

                <!-- Remarks -->
                <div>
                    <label for="remarks" class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-comment-dots text-blue-500 mr-2"></i>Remarks
                    </label>
                    <textarea name="remarks" id="remarks" rows="3" placeholder="Enter your remarks or feedback here..."
                        required
                        class="input-field w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent resize-none"></textarea>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="submit"
                    class="btn-gradient w-full text-white font-bold py-3 rounded-xl flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i>
                    <span>Submit Review</span>
                </button>
            </form>
        </div>

        <!-- Footer Note -->
        <div class="text-center mt-4 text-white text-xs">
            <i class="fas fa-info-circle mr-1"></i>
            The tenant will be notified via email
        </div>
    </div>
</body>

</html>