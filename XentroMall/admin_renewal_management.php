<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle renewal application approval/decline (NEW SYSTEM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['renewal_application_id'])) {
    $renewalId = $_POST['renewal_application_id'];
    $action = $_POST['action'];
    $adminFeedback = $_POST['admin_feedback'] ?? '';
    $adminId = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get renewal application details
        $stmt = $pdo->prepare("
            SELECT ra.*, u.username, u.email as user_email, td.tradename, s.stall_number 
            FROM renewal_applications ra
            JOIN users u ON ra.user_id = u.id
            JOIN tenant_details td ON ra.tenant_detail_id = td.id
            LEFT JOIN stalls s ON td.stall_id = s.id
            WHERE ra.id = ?
        ");
        $stmt->execute([$renewalId]);
        $renewal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($renewal) {
            // Update renewal application status
            $newStatus = ($action === 'approve') ? 'approved' : 'declined';
            $stmtUpdate = $pdo->prepare("
                UPDATE renewal_applications 
                SET status = ?, admin_feedback = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$newStatus, $adminFeedback, $adminId, $renewalId]);
            
            // Log the action
            $stmtLog = $pdo->prepare("
                INSERT INTO renewal_applications_log 
                (renewal_application_id, action, old_status, new_status, admin_id, notes)
                VALUES (?, ?, 'pending', ?, ?, ?)
            ");
            $stmtLog->execute([$renewalId, $action, $newStatus, $adminId, $adminFeedback]);
            
            // Send email notification to tenant
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

                $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
                $mail->addAddress($renewal['user_email'], $renewal['username']);
                $mail->isHTML(true);
                
                if ($action === 'approve') {
                    $mail->Subject = '✅ Your Renewal Application Has Been Approved - XentroMall';
                    $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                            .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                            .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                            .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #10b981; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                            .payment-info { background: #f0fdf4; border: 2px solid #10b981; padding: 20px; border-radius: 10px; margin: 20px 0; }
                            .action-button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>✅ Renewal Application Approved!</h1>
                                <p>XentroMall Management System</p>
                            </div>
                            <div class='content'>
                                <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                                    <strong>Great news!</strong> Your contract renewal application for <strong>{$renewal['tradename']}</strong> has been <strong style='color: #10b981;'>APPROVED</strong>.
                                </p>
                                
                                <div class='info-box'>
                                    <h3 style='color: #10b981; margin-top: 0;'>📋 Renewal Details</h3>
                                    <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Trade Name:</span>
                                        <span style='color: #1f2937;'><strong>{$renewal['tradename']}</strong></span>
                                    </div>
                                    <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Stall Number:</span>
                                        <span style='color: #1f2937;'>{$renewal['stall_number']}</span>
                                    </div>
                                    <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Monthly Payment:</span>
                                        <span style='color: #1f2937;'><strong>₱" . number_format($renewal['monthly_rate'], 2) . "</strong></span>
                                    </div>
                                    <div style='display: flex; padding: 8px 0;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Payment Type:</span>
                                        <span style='color: #1f2937;'><strong>Monthly Only</strong> (No advance required)</span>
                                    </div>
                                </div>
                                
                                <div class='payment-info'>
                                    <h3 style='color: #10b981; margin-top: 0;'>💳 Next Steps</h3>
                                    <p style='margin: 10px 0;'><strong>1.</strong> Proceed to payment section in your dashboard</p>
                                    <p style='margin: 10px 0;'><strong>2.</strong> Pay your monthly rent (₱" . number_format($renewal['monthly_rate'], 2) . ")</p>
                                    <p style='margin: 10px 0;'><strong>3.</strong> Your contract will be extended after payment</p>
                                </div>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='http://localhost/Jai/XentroMall/tenant_dashboard.php' class='action-button'>
                                        Go to Dashboard →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>";
                } else {
                    $mail->Subject = '❌ Your Renewal Application Has Been Declined - XentroMall';
                    $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                            .header { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                            .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                            .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                            .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #ef4444; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                            .feedback-box { background: #fef2f2; border: 2px solid #ef4444; padding: 20px; border-radius: 10px; margin: 20px 0; }
                            .action-button { display: inline-block; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>❌ Renewal Application Declined</h1>
                                <p>XentroMall Management System</p>
                            </div>
                            <div class='content'>
                                <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                                    Your contract renewal application for <strong>{$renewal['tradename']}</strong> has been <strong style='color: #ef4444;'>DECLINED</strong>.
                                </p>";
                    
                    if (!empty($adminFeedback)) {
                        $mail->Body .= "
                        <div class='feedback-box'>
                            <h3 style='color: #ef4444; margin-top: 0;'>📝 Admin Feedback</h3>
                            <p style='margin: 10px 0; font-style: italic;'>\"" . htmlspecialchars($adminFeedback) . "\"</p>
                        </div>";
                    }
                    
                    $mail->Body .= "
                        <div class='info-box'>
                            <h3 style='color: #ef4444; margin-top: 0;'>📋 Application Details</h3>
                            <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                <span style='font-weight: bold; color: #374151; width: 180px;'>Trade Name:</span>
                                <span style='color: #1f2937;'><strong>{$renewal['tradename']}</strong></span>
                            </div>
                            <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                <span style='font-weight: bold; color: #374151; width: 180px;'>Stall Number:</span>
                                <span style='color: #1f2937;'>{$renewal['stall_number']}</span>
                            </div>
                            <div style='display: flex; padding: 8px 0;'>
                                <span style='font-weight: bold; color: #374151; width: 180px;'>Submitted:</span>
                                <span style='color: #1f2937;'>" . date('F j, Y', strtotime($renewal['submitted_at'])) . "</span>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <p style='font-size: 15px; color: #374151; margin-bottom: 15px;'>
                                You may resubmit your renewal application after addressing the feedback.
                            </p>
                            <a href='http://localhost/Jai/XentroMall/tenant_dashboard.php?page=renewal' class='action-button'>
                                Resubmit Application →
                            </a>
                        </div>
                    </div>
                </div>
            </body>
            </html>";
                }
                
                $mail->send();
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
            }
            
            $_SESSION['success_message'] = "Renewal application {$newStatus} successfully!";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error processing renewal application: " . $e->getMessage();
    }
    
    header("Location: admin_renewal_management.php");
    exit;
}

// Handle old renewal requests (LEGACY SYSTEM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !isset($_POST['renewal_application_id'])) {
    $renewalId = $_POST['renewal_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $permitValidFrom = $_POST['permit_valid_from'] ?? '';
    $permitValidTo = $_POST['permit_valid_to'] ?? '';
    $timeFrom = $_POST['time_from'] ?? '';
    $timeTo = $_POST['time_to'] ?? '';
    
    if ($renewalId && in_array($action, ['approve', 'reject'])) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'approve') {
                // Update renewal request status
                $stmt = $pdo->prepare("UPDATE renewal_requests SET status = 'approved', permit_valid_from = ?, permit_valid_to = ?, time_from = ?, time_to = ?, processed_at = NOW() WHERE id = ?");
                $stmt->execute([$permitValidFrom, $permitValidTo, $timeFrom, $timeTo, $renewalId]);
                
                // Update tenant lease dates if provided
                if ($permitValidFrom && $permitValidTo) {
                    $renewalStmt = $pdo->prepare("SELECT tenant_id FROM renewal_requests WHERE id = ?");
                    $renewalStmt->execute([$renewalId]);
                    $renewal = $renewalStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($renewal) {
                        $leaseStmt = $pdo->prepare("INSERT INTO tenant_lease_dates (tenant_id, lease_start_date, lease_expiration_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lease_start_date = VALUES(lease_start_date), lease_expiration_date = VALUES(lease_expiration_date)");
                        $leaseStmt->execute([$renewal['tenant_id'], $permitValidFrom, $permitValidTo]);
                    }
                }
                
                $_SESSION['success_message'] = "Renewal request approved successfully.";
            } else {
                // Reject renewal
                $stmt = $pdo->prepare("UPDATE renewal_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
                $stmt->execute([$renewalId]);
                $_SESSION['success_message'] = "Renewal request rejected.";
            }
            
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
    }
    
    header("Location: admin_renewal_management.php");
    exit;
}

// Fetch NEW renewal applications (with document submission workflow)
try {
    $stmtNewApps = $pdo->prepare("
        SELECT 
            ra.id,
            ra.user_id,
            ra.tenant_detail_id,
            ra.monthly_rate,
            ra.status,
            ra.admin_feedback,
            ra.submitted_at,
            ra.admin_reviewed_at,
            ra.business_structure,
            ra.business_registration_type,
            ra.tin_number,
            u.username,
            u.email as user_email,
            td.tradename,
            td.company_name,
            s.stall_number,
            s.floor_area,
            admin.username as admin_name
        FROM renewal_applications ra
        JOIN users u ON ra.user_id = u.id
        JOIN tenant_details td ON ra.tenant_detail_id = td.id
        LEFT JOIN stalls s ON td.stall_id = s.id
        LEFT JOIN users admin ON ra.admin_reviewed_by = admin.id
        ORDER BY ra.submitted_at DESC
    ");
    $stmtNewApps->execute();
    $renewalApplications = $stmtNewApps->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $renewalApplications = [];
    $error_message = "Error fetching renewal applications: " . $e->getMessage();
}

// Fetch OLD renewal requests (legacy system)
try {
    $stmt = $pdo->prepare("
        SELECT 
            rr.id,
            rr.tenant_id,
            rr.renewal_date,
            rr.submitted_at,
            rr.status,
            rr.permit_valid_from,
            rr.permit_valid_to,
            rr.time_from,
            rr.time_to,
            rr.processed_at,
            u.username,
            u.email,
            td.tradename,
            td.company_name,
            td.business_address,
            td.contact_person,
            td.mobile,
            s.stall_number,
            s.monthly_rate,
            s.floor_area,
            tld.lease_start_date,
            tld.lease_expiration_date,
            app_sub.extended_bir_registration,
            MIN(p.payment_date) as first_payment_date,
            MAX(p.payment_date) as last_payment_date
        FROM renewal_requests rr
        JOIN users u ON rr.tenant_id = u.id
        LEFT JOIN tenant_details td ON rr.tenant_id = td.user_id
        LEFT JOIN stalls s ON td.stall_id = s.id
        LEFT JOIN tenant_lease_dates tld ON rr.tenant_id = tld.tenant_id
        LEFT JOIN application_submissions app_sub ON rr.tenant_id = app_sub.user_id
        LEFT JOIN payments p ON rr.tenant_id = p.user_id AND p.status = 'approved'
        GROUP BY rr.id
        ORDER BY rr.submitted_at DESC
    ");
    $stmt->execute();
    $renewalRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $renewalRequests = [];
    $error_message = "Error fetching renewal requests: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Renewal Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #10b981; border-radius: 8px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-emerald-600 to-emerald-500 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-4">
                        <a href="admin_dashboard.php" class="flex items-center gap-2 text-white hover:text-emerald-100 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Dashboard</span>
                        </a>
                        <div class="h-6 w-px bg-emerald-400"></div>
                        <h1 class="text-xl font-semibold">Renewal Management</h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-emerald-100">Admin Panel</span>
                        <div class="w-8 h-8 bg-emerald-700 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="mb-6 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 border border-emerald-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="mb-6 rounded-lg bg-red-50 text-red-700 px-4 py-3 border border-red-200">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- NEW Renewal Applications Stats -->
                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">New Applications</p>
                            <p class="text-2xl font-bold text-emerald-600"><?php echo count($renewalApplications); ?></p>
                            <p class="text-xs text-emerald-600">Document-based</p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-contract text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Pending New</p>
                            <p class="text-2xl font-bold text-yellow-600">
                                <?php echo count(array_filter($renewalApplications, fn($r) => $r['status'] === 'pending')); ?>
                            </p>
                            <p class="text-xs text-gray-500">Monthly payment</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Legacy Requests</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo count($renewalRequests); ?></p>
                            <p class="text-xs text-blue-600">Simple renewal</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-sync-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Pending Legacy</p>
                            <p class="text-2xl font-bold text-orange-600">
                                <?php echo count(array_filter($renewalRequests, fn($r) => empty($r['status']) || $r['status'] === 'pending')); ?>
                            </p>
                            <p class="text-xs text-gray-500">Old system</p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-history text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NEW Renewal Applications Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-file-contract text-emerald-600 mr-2"></i>
                        New Renewal Applications
                    </h2>
                    <span class="bg-emerald-100 text-emerald-800 px-3 py-1 rounded-full text-sm font-medium">
                        Monthly Payment • Document-based • Existing Account
                    </span>
                </div>

                <?php if (!empty($renewalApplications)): ?>
                    <div class="space-y-4">
                        <?php foreach ($renewalApplications as $app): ?>
                            <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
                                <div class="p-6">
                                    <!-- Application Header -->
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-sync-alt text-emerald-600 text-xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($app['tradename']); ?></h3>
                                                <p class="text-sm text-gray-600">Stall <?php echo htmlspecialchars($app['stall_number']); ?> • <?php echo htmlspecialchars($app['username']); ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'approved' => 'bg-green-100 text-green-800',
                                                'declined' => 'bg-red-100 text-red-800',
                                                'resubmitted' => 'bg-blue-100 text-blue-800'
                                            ];
                                            $colorClass = $statusColors[$app['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                                                <?php echo ucfirst($app['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Application Details -->
                                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                                        <div class="bg-gray-50 rounded-lg p-3">
                                            <p class="text-xs text-gray-500 mb-1">Monthly Rate</p>
                                            <p class="text-lg font-bold text-gray-900">₱<?php echo number_format($app['monthly_rate'], 2); ?></p>
                                        </div>
                                        <div class="bg-emerald-50 rounded-lg p-3">
                                            <p class="text-xs text-emerald-600 mb-1">Payment Type</p>
                                            <p class="text-sm font-semibold text-emerald-900">Monthly Only</p>
                                        </div>
                                        <div class="bg-blue-50 rounded-lg p-3">
                                            <p class="text-xs text-blue-600 mb-1">Account</p>
                                            <p class="text-sm font-semibold text-blue-900">Existing</p>
                                        </div>
                                        <div class="bg-purple-50 rounded-lg p-3">
                                            <p class="text-xs text-purple-600 mb-1">Business Structure</p>
                                            <?php
                                            $structureInfo = [
                                                'sole_proprietorship' => ['name' => 'Sole Prop', 'color' => 'blue'],
                                                'corporation' => ['name' => 'Corporation', 'color' => 'green'],
                                                'partnership' => ['name' => 'Partnership', 'color' => 'purple'],
                                                'llc' => ['name' => 'LLC', 'color' => 'orange'],
                                                'cooperative' => ['name' => 'Cooperative', 'color' => 'yellow'],
                                                'unknown' => ['name' => 'Unknown', 'color' => 'gray']
                                            ];
                                            $structure = $structureInfo[$app['business_structure']] ?? $structureInfo['unknown'];
                                            ?>
                                            <p class="text-sm font-semibold text-<?php echo $structure['color']; ?>-900"><?php echo $structure['name']; ?></p>
                                        </div>
                                        <div class="bg-orange-50 rounded-lg p-3">
                                            <p class="text-xs text-orange-600 mb-1">Submitted</p>
                                            <p class="text-sm font-semibold text-orange-900"><?php echo date('M j, Y', strtotime($app['submitted_at'])); ?></p>
                                        </div>
                                    </div>

                                    <!-- Business Structure Details -->
                                    <?php if ($app['business_structure'] !== 'unknown' || $app['business_registration_type'] || $app['tin_number']): ?>
                                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-4">
                                        <h4 class="text-sm font-semibold text-indigo-900 mb-2">
                                            <i class="fas fa-building mr-1"></i>Business Structure Detection
                                        </h4>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                            <?php if ($app['business_structure'] !== 'unknown'): ?>
                                            <div>
                                                <span class="text-indigo-600">Structure:</span>
                                                <span class="font-semibold text-indigo-900 ml-1"><?php echo ucfirst(str_replace('_', ' ', $app['business_structure'])); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($app['business_registration_type']): ?>
                                            <div>
                                                <span class="text-indigo-600">Registration:</span>
                                                <span class="font-semibold text-indigo-900 ml-1"><?php echo htmlspecialchars($app['business_registration_type']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($app['tin_number']): ?>
                                            <div>
                                                <span class="text-indigo-600">TIN:</span>
                                                <span class="font-semibold text-indigo-900 ml-1"><?php echo htmlspecialchars($app['tin_number']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Documents Indicator -->
                                    <div class="flex items-center space-x-2 text-sm text-gray-600 mb-4">
                                        <i class="fas fa-file-alt text-gray-400"></i>
                                        <span>Documents: Letter of Intent, Business Profile, Registration, Valid ID, BIR, Financial Statement</span>
                                    </div>

                                    <!-- Admin Feedback (if declined) -->
                                    <?php if ($app['status'] === 'declined' && !empty($app['admin_feedback'])): ?>
                                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                                            <p class="text-sm text-red-800">
                                                <strong>Admin Feedback:</strong> <?php echo htmlspecialchars($app['admin_feedback']); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Action Buttons -->
                                    <?php if ($app['status'] === 'pending'): ?>
                                        <form action="admin_renewal_management.php" method="post" class="space-y-4">
                                            <input type="hidden" name="renewal_application_id" value="<?php echo $app['id']; ?>">
                                            
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Admin Feedback (Optional)</label>
                                                <textarea name="admin_feedback" rows="2" 
                                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                                          placeholder="Add comments for the tenant..."></textarea>
                                            </div>
                                            
                                            <div class="flex space-x-3">
                                                <button type="submit" name="action" value="approve" 
                                                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition font-medium">
                                                    <i class="fas fa-check mr-2"></i>Approve
                                                </button>
                                                <button type="submit" name="action" value="decline" 
                                                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium">
                                                    <i class="fas fa-times mr-2"></i>Decline
                                                </button>
                                                <button type="button" onclick="viewDocuments(<?php echo $app['id']; ?>)" 
                                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                                                    <i class="fas fa-eye mr-2"></i>View Documents
                                                </button>
                                            </div>
                                        </form>
                                    <?php elseif ($app['status'] === 'approved'): ?>
                                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                            <p class="text-sm text-green-800">
                                                <i class="fas fa-check-circle mr-2"></i>
                                                Approved by <?php echo htmlspecialchars($app['admin_name']); ?> on <?php echo date('M j, Y', strtotime($app['admin_reviewed_at'])); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow border border-gray-100 p-8 text-center">
                        <i class="fas fa-check-circle text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No New Renewal Applications</h3>
                        <p class="text-gray-600">No document-based renewal applications have been submitted yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Legacy Renewal Requests Section -->
                            <p class="text-sm text-gray-500">Approved</p>
                            <p class="text-2xl font-bold text-emerald-600">
                                <?php echo count(array_filter($renewalRequests, fn($r) => $r['status'] === 'approved')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Rejected</p>
                            <p class="text-2xl font-bold text-red-600">
                                <?php echo count(array_filter($renewalRequests, fn($r) => $r['status'] === 'rejected')); ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-times-circle text-red-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Renewal Requests Table -->
            <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Renewal Requests</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage tenant renewal requests and Extended BIR submissions</p>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($renewalRequests)): ?>
                        <div class="p-8 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-sync-alt text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Renewal Requests</h3>
                            <p class="text-gray-500">There are no renewal requests to display at this time.</p>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant Info</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stall Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Lease</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Renewal Request</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Extended BIR</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($renewalRequests as $request): ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- Tenant Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                                    <i class="fas fa-user text-emerald-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($request['username']); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($request['email']); ?>
                                                    </div>
                                                    <?php if ($request['contact_person']): ?>
                                                        <div class="text-xs text-gray-400">
                                                            <?php echo htmlspecialchars($request['contact_person']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Stall Details -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <div class="font-medium">
                                                    <?php echo $request['stall_number'] ? htmlspecialchars($request['stall_number']) : 'Not Assigned'; ?>
                                                </div>
                                                <?php if ($request['floor_area']): ?>
                                                    <div class="text-gray-500"><?php echo htmlspecialchars($request['floor_area']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($request['monthly_rate']): ?>
                                                    <div class="text-emerald-600 font-medium">₱<?php echo number_format($request['monthly_rate'], 2); ?>/month</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Current Lease -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm">
                                                <?php if ($request['lease_start_date'] && $request['lease_expiration_date']): ?>
                                                    <div class="text-gray-900">
                                                        <div><strong>Start:</strong> <?php echo date('M j, Y', strtotime($request['lease_start_date'])); ?></div>
                                                        <div><strong>End:</strong> <?php echo date('M j, Y', strtotime($request['lease_expiration_date'])); ?></div>
                                                    </div>
                                                    <?php 
                                                        $daysRemaining = ceil((strtotime($request['lease_expiration_date']) - time()) / (60 * 60 * 24));
                                                        $statusColor = $daysRemaining <= 30 ? 'text-red-600' : ($daysRemaining <= 90 ? 'text-yellow-600' : 'text-green-600');
                                                    ?>
                                                    <div class="<?php echo $statusColor; ?> font-medium text-xs mt-1">
                                                        <?php echo $daysRemaining > 0 ? $daysRemaining . ' days left' : 'Expired'; ?>
                                                    </div>
                                                <?php elseif ($request['first_payment_date']): ?>
                                                    <div class="text-gray-500">
                                                        <div><strong>Started:</strong> <?php echo date('M j, Y', strtotime($request['first_payment_date'])); ?></div>
                                                        <?php if ($request['last_payment_date'] !== $request['first_payment_date']): ?>
                                                            <div><strong>Last Payment:</strong> <?php echo date('M j, Y', strtotime($request['last_payment_date'])); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-gray-400 text-xs">No lease data</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Renewal Request -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm">
                                                <div class="text-gray-900 font-medium">
                                                    <?php echo date('M j, Y', strtotime($request['renewal_date'])); ?>
                                                </div>
                                                <div class="text-gray-500 text-xs">
                                                    Requested: <?php echo date('M j, Y g:i A', strtotime($request['submitted_at'])); ?>
                                                </div>
                                                <?php if ($request['permit_valid_from'] && $request['permit_valid_to']): ?>
                                                    <div class="text-emerald-600 text-xs mt-1">
                                                        <div><strong>Valid:</strong> <?php echo date('M j, Y', strtotime($request['permit_valid_from'])); ?> - <?php echo date('M j, Y', strtotime($request['permit_valid_to'])); ?></div>
                                                        <?php if ($request['time_from'] && $request['time_to']): ?>
                                                            <div><strong>Time:</strong> <?php echo date('g:i A', strtotime($request['time_from'])); ?> - <?php echo date('g:i A', strtotime($request['time_to'])); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Extended BIR -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($request['extended_bir_registration']): ?>
                                                <a href="<?php echo htmlspecialchars($request['extended_bir_registration']); ?>" 
                                                   target="_blank" 
                                                   class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium hover:bg-blue-200 transition-colors">
                                                    <i class="fas fa-file-alt"></i>
                                                    <span>View BIR</span>
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-2 px-3 py-1 bg-gray-100 text-gray-500 rounded-full text-xs">
                                                    <i class="fas fa-minus"></i>
                                                    <span>No BIR</span>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                                $status = $request['status'] ?? 'pending';
                                                $statusClasses = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                                    'rejected' => 'bg-red-100 text-red-800'
                                                ];
                                                $statusIcons = [
                                                    'pending' => 'fas fa-clock',
                                                    'approved' => 'fas fa-check-circle',
                                                    'rejected' => 'fas fa-times-circle'
                                                ];
                                            ?>
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClasses[$status]; ?>">
                                                <i class="<?php echo $statusIcons[$status]; ?>"></i>
                                                <span><?php echo ucfirst($status); ?></span>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?php if (empty($request['status']) || $request['status'] === 'pending'): ?>
                                                <div class="flex items-center gap-2">
                                                    <button onclick="openApprovalModal(<?php echo htmlspecialchars(json_encode($request)); ?>)" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                                                        <i class="fas fa-check text-xs"></i>
                                                        <span>Approve</span>
                                                    </button>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to reject this renewal request?')">
                                                        <input type="hidden" name="renewal_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                                                            <i class="fas fa-times text-xs"></i>
                                                            <span>Reject</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs">
                                                    <?php echo $request['processed_at'] ? 'Processed: ' . date('M j, Y', strtotime($request['processed_at'])) : 'Processed'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Approve Renewal Request</h3>
                <p class="text-sm text-gray-500 mt-1">Set permit validity dates and times</p>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="renewal_id" id="modalRenewalId">
                <input type="hidden" name="action" value="approve">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="permit_valid_from" class="block text-sm font-medium text-gray-700 mb-1">Permit Valid From</label>
                        <input type="date" name="permit_valid_from" id="permit_valid_from" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="permit_valid_to" class="block text-sm font-medium text-gray-700 mb-1">Permit Valid To</label>
                        <input type="date" name="permit_valid_to" id="permit_valid_to" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="time_from" class="block text-sm font-medium text-gray-700 mb-1">Time From</label>
                        <input type="time" name="time_from" id="time_from" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="time_to" class="block text-sm font-medium text-gray-700 mb-1">Time To</label>
                        <input type="time" name="time_to" id="time_to" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                    </div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-blue-900 mb-2">Tenant Information</h4>
                    <div id="modalTenantInfo" class="text-sm text-blue-800"></div>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeApprovalModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                        Approve Renewal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApprovalModal(request) {
            document.getElementById('modalRenewalId').value = request.id;
            
            // Set default dates (current date to 1 year from now)
            const today = new Date();
            const nextYear = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
            
            document.getElementById('permit_valid_from').value = today.toISOString().split('T')[0];
            document.getElementById('permit_valid_to').value = nextYear.toISOString().split('T')[0];
            
            // Set default times
            document.getElementById('time_from').value = '08:00';
            document.getElementById('time_to').value = '18:00';
            
            // Display tenant info
            const tenantInfo = `
                <div><strong>Tenant:</strong> ${request.username}</div>
                <div><strong>Email:</strong> ${request.email}</div>
                <div><strong>Stall:</strong> ${request.stall_number || 'Not Assigned'}</div>
                <div><strong>Requested Date:</strong> ${new Date(request.renewal_date).toLocaleDateString()}</div>
            `;
            document.getElementById('modalTenantInfo').innerHTML = tenantInfo;
            
            document.getElementById('approvalModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeApprovalModal();
            }
        });
    </script>
</body>
</html>