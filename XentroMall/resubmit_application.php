<?php
session_start();
require 'config.php';
require 'application_helper.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tenant') {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$tenantDetailId = $_GET['id'] ?? 0;

// Fetch the declined application
$stmt = $pdo->prepare("
    SELECT td.*, s.stall_number, s.description, s.monthly_rate, s.floor_area
    FROM tenant_details td
    INNER JOIN stalls s ON td.stall_id = s.id
    WHERE td.id = ? AND td.user_id = ? AND td.status = 'declined'
");
$stmt->execute([$tenantDetailId, $userId]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    $_SESSION['error_message'] = "Application not found or not eligible for resubmission.";
    header('Location: tenant_dashboard.php');
    exit;
}

// Check attempt limit (5 attempts max)
$submissionCount = (int)($application['submission_count'] ?? 1);
if ($submissionCount >= 5) {
    $_SESSION['error_message'] = "You have reached the maximum number of attempts (5) for this application. Please contact admin for assistance.";
    header('Location: tenant_dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resubmit'])) {
    $uploadDir = 'uploads/' . $userId . '_' . time() . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadedFiles = [];
    $uploadSuccess = true;
    
    // Handle file uploads
    foreach ($_FILES as $key => $file) {
        if ($file['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($file['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $uploadedFiles[] = $targetPath;
            } else {
                $uploadSuccess = false;
                break;
            }
        }
    }
    
    if ($uploadSuccess && count($uploadedFiles) > 0) {
        try {
            // Update application status to pending and update documents
            $stmt = $pdo->prepare("
                UPDATE tenant_details 
                SET status = 'pending', 
                    documents = ?, 
                    admin_feedback = NULL,
                    created_at = NOW(),
                    submission_count = COALESCE(submission_count, 1) + 1
                WHERE id = ?
            ");
            $stmt->execute([$uploadDir, $tenantDetailId]);
            
            // Log to history
            logApplicationStatus($pdo, $tenantDetailId, $userId, 'pending', 'Resubmitted with new documents', $submissionCount + 1);
            
            // Send email notification to admin
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'mallxentro5@gmail.com';
                $mail->Password = 'iyaw xyvu lfqe xfzw';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall TMS');
                $mail->addAddress('mallxentro5@gmail.com');
                
                $mail->isHTML(true);
                $mail->Subject = "🔄 Application Resubmitted - " . $application['stall_number'];
                $mail->Body = "
                <h2>Application Resubmitted</h2>
                <p><strong>Tenant:</strong> {$_SESSION['username']}</p>
                <p><strong>Stall:</strong> {$application['stall_number']}</p>
                <p><strong>Tradename:</strong> {$application['tradename']}</p>
                <p><strong>Status:</strong> Pending Review</p>
                <p>The tenant has resubmitted their application with new documents. Please review in the admin dashboard.</p>
                ";
                
                $mail->send();
            } catch (Exception $e) {
                // Email failed but continue
            }
            
            $_SESSION['success_message'] = "Application resubmitted successfully! Waiting for admin review.";
            header('Location: tenant_dashboard.php');
            exit;
            
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error resubmitting application: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Please upload at least one document.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resubmit Application - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gradient-to-br from-emerald-50 via-white to-blue-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-redo text-orange-600 mr-3"></i>Resubmit Application
                    </h1>
                    <p class="text-gray-600 mt-2">Upload new documents to resubmit your application</p>
                </div>
                <a href="tenant_dashboard.php" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Stall Info -->
        <div class="bg-gradient-to-r from-orange-500 to-red-500 rounded-2xl shadow-xl p-6 mb-6 text-white">
            <h2 class="text-2xl font-bold mb-4"><i class="fas fa-store mr-2"></i><?php echo htmlspecialchars($application['stall_number']); ?></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-orange-100 text-sm">Tradename</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($application['tradename']); ?></p>
                </div>
                <div>
                    <p class="text-orange-100 text-sm">Company</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($application['company_name']); ?></p>
                </div>
                <div>
                    <p class="text-orange-100 text-sm">Floor Area</p>
                    <p class="font-semibold"><?php echo htmlspecialchars($application['floor_area']); ?> sq.m</p>
                </div>
                <div>
                    <p class="text-orange-100 text-sm">Monthly Rate</p>
                    <p class="font-semibold">₱<?php echo number_format($application['monthly_rate'], 2); ?></p>
                </div>
            </div>
            
            <?php if ($application['admin_feedback']): ?>
            <div class="mt-4 p-4 bg-white/20 rounded-lg backdrop-blur">
                <p class="text-sm font-semibold mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Admin Feedback:</p>
                <p class="text-sm"><?php echo htmlspecialchars($application['admin_feedback']); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Resubmit Form -->
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-xl p-8">
            <h3 class="text-xl font-bold text-gray-900 mb-6">
                <i class="fas fa-upload text-orange-600 mr-2"></i>Upload New Documents
            </h3>
            
            <p class="text-gray-600 mb-6">
                Please upload the required documents based on your business structure: <strong><?php echo htmlspecialchars($application['business_type']); ?></strong>
            </p>

            <div class="space-y-4">
                <!-- Letter of Intent -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-file-alt mr-1"></i> Letter of Intent <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="letter_of_intent" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                </div>

                <?php if ($application['business_type'] === 'Corporation'): ?>
                    <!-- Corporation Documents -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-1"></i> Company Profile <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="company_profile" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-1"></i> SEC Registration <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="sec_registration" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-1"></i> Secretary's Certificate <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="secretary_certificate" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                    </div>
                <?php elseif ($application['business_type'] === 'Sole Proprietorship'): ?>
                    <!-- Sole Proprietorship Documents -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-1"></i> DTI Permit <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="dti_permit" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                    </div>
                <?php elseif ($application['business_type'] === 'Franchisee'): ?>
                    <!-- Franchisee Documents -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-1"></i> Enrollment Letter <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="enrollment_letter" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fas fa-file-alt mr-1"></i> Franchise Agreement <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="franchise_agreement" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                    </div>
                <?php endif; ?>

                <!-- Common Documents -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-file-alt mr-1"></i> BIR Form 2303 <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="bir_form" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-1"></i> Valid ID #1 <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="valid_id_1" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-id-card mr-1"></i> Valid ID #2 <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="valid_id_2" accept=".pdf,.jpg,.jpeg,.png" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-600 focus:border-transparent">
                </div>
            </div>

            <div class="mt-8 flex gap-4">
                <button type="submit" name="resubmit" class="flex-1 px-6 py-3 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white rounded-lg font-bold transition transform hover:scale-105 shadow-lg">
                    <i class="fas fa-paper-plane mr-2"></i>Resubmit Application
                </button>
                <a href="tenant_dashboard.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-bold transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>
