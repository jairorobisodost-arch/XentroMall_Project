<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal_payment'])) {
    try {
        $renewalId = isset($_POST['renewal_id']) ? (int)$_POST['renewal_id'] : 0;
        $paymentType = isset($_POST['type']) ? $_POST['type'] : 'contract_renewal';
        
        if ($renewalId <= 0) {
            $_SESSION['error'] = "Invalid renewal request.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }

        // Get renewal details based on type
        if ($paymentType === 'unified_renewal') {
            // Handle unified renewal requests
            $stmtRenewal = $pdo->prepare("
                SELECT urr.*, td.tradename, td.mobile, td.email as tenant_email, s.monthly_rate, s.stall_number
                FROM unified_renewal_requests urr
                LEFT JOIN tenant_details td ON urr.user_id = td.user_id
                LEFT JOIN stalls s ON urr.stall_id = s.id
                WHERE urr.id = ? AND urr.user_id = ? AND urr.status = 'approved'
            ");
            $stmtRenewal->execute([$renewalId, $userId]);
            $renewal = $stmtRenewal->fetch(PDO::FETCH_ASSOC);
            
            if (!$renewal) {
                $_SESSION['error'] = "Unified renewal request not found or not approved.";
                header('Location: tenant_dashboard.php?page=renewal');
                exit;
            }
        } else {
            // Handle old contract renewals (existing code)
            $stmtRenewal = $pdo->prepare("
                SELECT cr.*, td.tradename, s.monthly_rate, s.stall_number
                FROM contract_renewals cr
                INNER JOIN tenant_details td ON cr.user_id = td.user_id
                INNER JOIN stalls s ON td.stall_id = s.id
                WHERE cr.id = ? AND cr.user_id = ? AND cr.status = 'approved'
            ");
            $stmtRenewal->execute([$renewalId, $userId]);
            $renewal = $stmtRenewal->fetch(PDO::FETCH_ASSOC);

            if (!$renewal) {
                $_SESSION['error'] = "Renewal request not found or not approved.";
                header('Location: tenant_dashboard.php?page=renewal');
                exit;
            }
        }

        // Handle payment proof upload
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/renewal_payments/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_payment_' . basename($_FILES['payment_proof']['name']);
            $targetPath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $targetPath)) {
                // Update renewal with payment proof based on type
                if ($paymentType === 'unified_renewal') {
                    // Update unified renewal request
                    $stmtUpdate = $pdo->prepare("
                        UPDATE unified_renewal_requests 
                        SET payment_proof = ?
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$targetPath, $renewalId]);
                } else {
                    // Update old contract renewal
                    $stmtUpdate = $pdo->prepare("
                        UPDATE contract_renewals 
                        SET payment_proof = ? 
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$targetPath, $renewalId]);
                }

                // Create payment record for monthly rent (NOT 3 months advance)
                $monthlyRate = $renewal['monthly_rate'];
                $currentMonth = date('F Y');

                $stmtPayment = $pdo->prepare("
                    INSERT INTO payments (
                        user_id, stall_id, amount, payment_method,
                        reference_number, billing_month, payment_image, payment_date, status,
                        rent_amount, utilities_amount, rent_due, rent_balance, payment_type
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending', ?, 0, ?, 0, ?)
                ");

                $stmtPayment->execute([
                    $userId,
                    $renewal['stall_id'] ?? null,
                    $monthlyRate,
                    'Receipt Upload',
                    isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '',
                    $currentMonth,
                    $targetPath,
                    $monthlyRate,
                    $monthlyRate,
                    'renewal_monthly'
                ]);

                // Send notification to admin
                $adminEmail = 'mallxentro5@gmail.com';
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'mallxentro5@gmail.com';
                    $mail->Password = 'iwld cjlr kmcy bxab';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;

                    $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
                    $mail->addAddress($adminEmail, 'XentroMall Admin');

                    $mail->isHTML(true);
                    $mail->Subject = "💰 Renewal Payment Submitted - " . $renewal['tradename'];
                    $mail->Body = "
                    <h2>Renewal Payment Received</h2>
                    <p><strong>Tenant:</strong> " . htmlspecialchars($renewal['tradename']) . "</p>
                    <p><strong>Stall:</strong> " . htmlspecialchars($renewal['stall_number']) . "</p>
                    <p><strong>Monthly Rent:</strong> ₱" . number_format($monthlyRate, 2) . "</p>
                    <p><strong>Payment Type:</strong> Renewal - Monthly Payment (NOT 3 months advance)</p>
                    <p><strong>Billing Month:</strong> " . $currentMonth . "</p>
                    <p><strong>Payment Proof:</strong> Uploaded</p>
                    <p>Please review the payment proof and approve in the admin dashboard.</p>
                    ";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Email failed: " . $e->getMessage());
                }

                $_SESSION['success'] = "✅ Renewal payment submitted successfully! Monthly payment of ₱" . number_format($monthlyRate, 2) . " recorded. Waiting for admin approval.";
                header('Location: tenant_dashboard.php?page=renewal');
                exit;
            } else {
                $_SESSION['error'] = "Failed to upload payment proof.";
                header('Location: tenant_dashboard.php?page=renewal');
                exit;
            }
        } else {
            $_SESSION['error'] = "Please upload payment proof.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
    }
} else {
    // Display payment form for GET requests
    $renewalId = isset($_GET['renewal_id']) ? (int)$_GET['renewal_id'] : 0;
    $paymentType = isset($_GET['type']) ? $_GET['type'] : 'contract_renewal';
    $amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;
    
    if ($renewalId <= 0 || $amount <= 0) {
        $_SESSION['error'] = "Invalid payment request.";
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
    }
    
    // Get renewal details
    if ($paymentType === 'unified_renewal') {
        $stmtRenewal = $pdo->prepare("
            SELECT urr.*, td.tradename, td.mobile, td.email as tenant_email, s.monthly_rate, s.stall_number
            FROM unified_renewal_requests urr
            LEFT JOIN tenant_details td ON urr.user_id = td.user_id
            LEFT JOIN stalls s ON urr.stall_id = s.id
            WHERE urr.id = ? AND urr.user_id = ? AND urr.status = 'approved'
        ");
        $stmtRenewal->execute([$renewalId, $userId]);
        $renewal = $stmtRenewal->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmtRenewal = $pdo->prepare("
            SELECT cr.*, td.tradename, s.monthly_rate, s.stall_number
            FROM contract_renewals cr
            INNER JOIN tenant_details td ON cr.user_id = td.user_id
            INNER JOIN stalls s ON td.stall_id = s.id
            WHERE cr.id = ? AND cr.user_id = ? AND cr.status = 'approved'
        ");
        $stmtRenewal->execute([$renewalId, $userId]);
        $renewal = $stmtRenewal->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$renewal) {
        $_SESSION['error'] = "Renewal request not found or not approved.";
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
    }
    
    // Display payment form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Renewal Payment - XentroMall</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl w-full space-y-8">
                <div class="bg-white shadow-xl rounded-2xl p-8">
                    <div class="text-center mb-8">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-credit-card text-green-600 text-2xl"></i>
                        </div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Renewal Payment</h1>
                        <p class="text-gray-600">Complete your renewal payment to finalize the process</p>
                    </div>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                                <p class="text-red-700"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-gray-50 rounded-lg p-6 mb-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Payment Details</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Business Name:</span>
                                <span class="font-semibold"><?php echo htmlspecialchars($renewal['tradename']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Request Type:</span>
                                <span class="font-semibold"><?php echo ucfirst($renewal['request_type'] ?? 'Renewal'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Amount:</span>
                                <span class="font-bold text-green-600">₱<?php echo number_format($amount, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment Type:</span>
                                <span class="font-semibold">Monthly Renewal Payment</span>
                            </div>
                        </div>
                    </div>

                    <form action="submit_renewal_payment.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="renewal_id" value="<?php echo $renewalId; ?>">
                        <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                        <input type="hidden" name="type" value="<?php echo $paymentType; ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-hashtag mr-2"></i>Reference Number (Optional)
                            </label>
                            <input type="text" name="reference_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-600 focus:border-transparent" placeholder="e.g. GCash Ref No.">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-file-image mr-2"></i>Payment Proof
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-gray-400 transition-colors">
                                <input type="file" name="payment_proof" id="payment_proof" accept="image/*,.pdf" required class="hidden">
                                <label for="payment_proof" class="cursor-pointer">
                                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600">Click to upload payment proof</p>
                                    <p class="text-sm text-gray-500 mt-1">PNG, JPG, PDF up to 10MB</p>
                                </label>
                                <div id="file-preview" class="mt-4 hidden">
                                    <img id="preview-image" class="max-h-32 mx-auto rounded" alt="Preview">
                                    <p id="file-name" class="text-sm text-gray-600 mt-2"></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-yellow-600 mt-1 mr-3"></i>
                                <div class="text-sm text-yellow-800">
                                    <p class="font-semibold mb-1">Important:</p>
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Upload a clear photo or PDF of your payment receipt</li>
                                        <li>Make sure the payment amount and date are visible</li>
                                        <li>Payment will be reviewed and approved by admin</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <button type="submit" name="submit_renewal_payment" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                                <i class="fas fa-check mr-2"></i>Submit Payment
                            </button>
                            <a href="tenant_dashboard.php?page=renewal" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-3 px-6 rounded-lg text-center transition-colors">
                                <i class="fas fa-arrow-left mr-2"></i>Back
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('payment_proof').addEventListener('change', function(e) {
                const file = e.target.files[0];
                const preview = document.getElementById('file-preview');
                const previewImage = document.getElementById('preview-image');
                const fileName = document.getElementById('file-name');
                
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (file.type.startsWith('image/')) {
                            previewImage.src = e.target.result;
                            previewImage.classList.remove('hidden');
                        } else {
                            previewImage.classList.add('hidden');
                        }
                        fileName.textContent = file.name;
                        preview.classList.remove('hidden');
                    };
                    reader.readAsDataURL(file);
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
