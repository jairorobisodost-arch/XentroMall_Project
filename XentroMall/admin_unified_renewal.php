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

// Handle renewal request approval/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['renewal_request_id'])) {
    $renewalId = $_POST['renewal_request_id'];
    $action = $_POST['action'];
    $adminFeedback = $_POST['admin_feedback'] ?? '';
    $adminId = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get renewal request details
        $stmt = $pdo->prepare("
            SELECT urr.*, u.username, u.email as user_email, td.tradename
            FROM unified_renewal_requests urr
            JOIN users u ON urr.user_id = u.id
            LEFT JOIN tenant_details td ON urr.tenant_detail_id = td.id
            WHERE urr.id = ?
        ");
        $stmt->execute([$renewalId]);
        $renewal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($renewal) {
            // Determine new status
            if ($action === 'approve') {
                $newStatus = ($renewal['request_type'] === 'renewal') ? 'payment_pending' : 'approved';
            } else {
                $newStatus = 'declined';
            }
            
            // Update renewal request status
            $stmtUpdate = $pdo->prepare("
                UPDATE unified_renewal_requests 
                SET status = ?, admin_feedback = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([$newStatus, $adminFeedback, $adminId, $renewalId]);
            
            // Log the action
            $stmtLog = $pdo->prepare("
                INSERT INTO unified_renewal_audit_log 
                (renewal_request_id, action, old_status, new_status, admin_id, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtLog->execute([$renewalId, $action, $renewal['status'], $newStatus, $adminId, $adminFeedback]);
            
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
                    if ($renewal['request_type'] === 'renewal') {
                        $mail->Subject = '✅ Your Renewal Request Has Been Approved - XentroMall';
                        $nextSteps = "
                            <div class='payment-info'>
                                <h3 style='color: #10b981; margin-top: 0;'>💳 Next Steps</h3>
                                <p style='margin: 10px 0;'><strong>1.</strong> Proceed to payment section in your dashboard</p>
                                <p style='margin: 10px 0;'><strong>2.</strong> Pay your monthly rent (₱" . number_format($renewal['monthly_rate'], 2) . ")</p>
                                <p style='margin: 10px 0;'><strong>3.</strong> Your contract will be extended after payment</p>
                            </div>";
                    } else {
                        $mail->Subject = '✅ Your Application Has Been Approved - XentroMall';
                        $nextSteps = "
                            <div class='payment-info'>
                                <h3 style='color: #10b981; margin-top: 0;'>💳 Next Steps</h3>
                                <p style='margin: 10px 0;'><strong>1.</strong> Proceed to payment section in your dashboard</p>
                                <p style='margin: 10px 0;'><strong>2.</strong> Pay 3-month advance rent (₱" . number_format($renewal['total_amount'], 2) . ")</p>
                                <p style='margin: 10px 0;'><strong>3.</strong> Your account will be created after payment</p>
                                <p style='margin: 10px 0;'><strong>4.</strong> You'll receive your contract and lease details</p>
                            </div>";
                    }
                    
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
                                <h1>✅ " . ($renewal['request_type'] === 'renewal' ? 'Renewal Request Approved!' : 'Application Approved!') . "</h1>
                                <p>XentroMall Management System</p>
                            </div>
                            <div class='content'>
                                <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                                    <strong>Great news!</strong> Your " . ($renewal['request_type'] === 'renewal' ? 'renewal request' : 'application') . " for <strong>" . htmlspecialchars($renewal['tradename']) . "</strong> has been <strong style='color: #10b981;'>APPROVED</strong>.
                                </p>
                                
                                <div class='info-box'>
                                    <h3 style='color: #10b981; margin-top: 0;'>📋 Request Details</h3>
                                    <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Trade Name:</span>
                                        <span style='color: #1f2937;'><strong>" . htmlspecialchars($renewal['tradename']) . "</strong></span>
                                    </div>
                                    <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Monthly Rate:</span>
                                        <span style='color: #1f2937;'><strong>₱" . number_format($renewal['monthly_rate'], 2) . "</strong></span>
                                    </div>
                                    <div style='display: flex; padding: 8px 0;'>
                                        <span style='font-weight: bold; color: #374151; width: 180px;'>Payment Type:</span>
                                        <span style='color: #1f2937;'><strong>" . ($renewal['request_type'] === 'renewal' ? 'Monthly Only' : '3-Month Advance') . "</strong></span>
                                    </div>
                                </div>
                                
                                " . $nextSteps . "
                                
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
                    $mail->Subject = '❌ Your ' . ($renewal['request_type'] === 'renewal' ? 'Renewal Request' : 'Application') . ' Has Been Declined - XentroMall';
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
                                <h1>❌ " . ($renewal['request_type'] === 'renewal' ? 'Renewal Request Declined' : 'Application Declined') . "</h1>
                                <p>XentroMall Management System</p>
                            </div>
                            <div class='content'>
                                <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                                    Your " . ($renewal['request_type'] === 'renewal' ? 'renewal request' : 'application') . " for <strong>" . htmlspecialchars($renewal['tradename']) . "</strong> has been <strong style='color: #ef4444;'>DECLINED</strong>.
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
                            <h3 style='color: #ef4444; margin-top: 0;'>📋 Request Details</h3>
                            <div style='display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>
                                <span style='font-weight: bold; color: #374151; width: 180px;'>Trade Name:</span>
                                <span style='color: #1f2937;'><strong>" . htmlspecialchars($renewal['tradename']) . "</strong></span>
                            </div>
                            <div style='display: flex; padding: 8px 0;'>
                                <span style='font-weight: bold; color: #374151; width: 180px;'>Submitted:</span>
                                <span style='color: #1f2937;'>" . date('F j, Y', strtotime($renewal['submitted_at'])) . "</span>
                            </div>
                        </div>
                        
                        <div style='text-align: center; margin: 30px 0;'>
                            <p style='font-size: 15px; color: #374151; margin-bottom: 15px;'>
                                You may resubmit your " . ($renewal['request_type'] === 'renewal' ? 'renewal request' : 'application') . " after addressing the feedback.
                            </p>
                            <a href='http://localhost/Jai/XentroMall/unified_renewal_form.php' class='action-button'>
                                Resubmit →
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
            
            $_SESSION['success_message'] = "Request " . $newStatus . " successfully!";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
    
    header("Location: admin_unified_renewal.php");
    exit;
}

// Fetch all renewal requests
try {
    $stmtRequests = $pdo->prepare("
        SELECT 
            urr.id,
            urr.user_id,
            urr.request_type,
            urr.tradename,
            urr.monthly_rate,
            urr.total_amount,
            urr.advance_months,
            urr.late_renewal_fee,
            urr.payment_type,
            urr.account_action,
            urr.business_structure,
            urr.status,
            urr.admin_feedback,
            urr.submitted_at,
            urr.admin_reviewed_at,
            u.username,
            u.email as user_email,
            admin.username as admin_name
        FROM unified_renewal_requests urr
        JOIN users u ON urr.user_id = u.id
        LEFT JOIN users admin ON urr.admin_reviewed_by = admin.id
        ORDER BY urr.submitted_at DESC
    ");
    $stmtRequests->execute();
    $renewalRequests = $stmtRequests->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $renewalRequests = [];
    $error_message = "Error fetching requests: " . $e->getMessage();
}

// Calculate statistics
$stats = [
    'total' => count($renewalRequests),
    'pending' => count(array_filter($renewalRequests, fn($r) => $r['status'] === 'pending')),
    'approved' => count(array_filter($renewalRequests, fn($r) => $r['status'] === 'approved')),
    'payment_pending' => count(array_filter($renewalRequests, fn($r) => $r['status'] === 'payment_pending')),
    'declined' => count(array_filter($renewalRequests, fn($r) => $r['status'] === 'declined')),
    'completed' => count(array_filter($renewalRequests, fn($r) => $r['status'] === 'completed')),
    'new_apps' => count(array_filter($renewalRequests, fn($r) => $r['request_type'] === 'new_application')),
    'renewals' => count(array_filter($renewalRequests, fn($r) => $r['request_type'] === 'renewal'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Unified Renewal Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #667eea; border-radius: 8px; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-4">
                        <a href="admin_dashboard.php" class="flex items-center gap-2 text-white hover:text-purple-100 transition-colors">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Dashboard</span>
                        </a>
                        <div class="h-6 w-px bg-purple-400"></div>
                        <h1 class="text-xl font-semibold">Unified Renewal Management</h1>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-purple-100">Admin Panel</span>
                        <div class="w-8 h-8 bg-purple-700 rounded-full flex items-center justify-center">
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
                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Requests</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $stats['total']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-list text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Pending Review</p>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">New Applications</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo $stats['new_apps']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow border border-gray-100 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Renewals</p>
                            <p class="text-2xl font-bold text-emerald-600"><?php echo $stats['renewals']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-sync-alt text-emerald-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests Table -->
            <div class="bg-white rounded-xl shadow border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">All Renewal Requests</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage new applications and renewal requests</p>
                </div>

                <div class="overflow-x-auto">
                    <?php if (empty($renewalRequests)): ?>
                        <div class="p-8 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-inbox text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Requests</h3>
                            <p class="text-gray-500">There are no renewal requests to display at this time.</p>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type & Tenant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Info</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($renewalRequests as $request): ?>
                                    <tr class="hover:bg-gray-50">
                                        <!-- Type & Tenant -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-full flex items-center justify-center <?php echo $request['request_type'] === 'renewal' ? 'bg-emerald-100' : 'bg-blue-100'; ?>">
                                                    <i class="fas <?php echo $request['request_type'] === 'renewal' ? 'fa-sync-alt text-emerald-600' : 'fa-file-alt text-blue-600'; ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($request['tradename']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($request['username']); ?></div>
                                                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-xs font-medium <?php echo $request['request_type'] === 'renewal' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                        <?php echo $request['request_type'] === 'renewal' ? '🔄 Renewal' : '📝 New App'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Payment Info -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm">
                                                <div class="font-medium text-gray-900">₱<?php echo number_format($request['total_amount'], 2); ?></div>
                                                <div class="text-xs text-gray-500">
                                                    <?php 
                                                    if ($request['request_type'] === 'renewal') {
                                                        echo 'Monthly Only';
                                                        if ($request['late_renewal_fee'] > 0) {
                                                            echo ' + Late Fee: ₱' . number_format($request['late_renewal_fee'], 2);
                                                        }
                                                    } else {
                                                        echo '3-Month Advance';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Account -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $request['account_action'] === 'create_new' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800'; ?>">
                                                <i class="fas <?php echo $request['account_action'] === 'create_new' ? 'fa-plus-circle' : 'fa-check-circle'; ?>"></i>
                                                <?php echo $request['account_action'] === 'create_new' ? 'Create New' : 'Use Existing'; ?>
                                            </span>
                                        </td>

                                        <!-- Status -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                                $statusClasses = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'approved' => 'bg-emerald-100 text-emerald-800',
                                                    'payment_pending' => 'bg-blue-100 text-blue-800',
                                                    'declined' => 'bg-red-100 text-red-800',
                                                    'completed' => 'bg-purple-100 text-purple-800'
                                                ];
                                                $statusIcons = [
                                                    'pending' => 'fas fa-clock',
                                                    'approved' => 'fas fa-check-circle',
                                                    'payment_pending' => 'fas fa-credit-card',
                                                    'declined' => 'fas fa-times-circle',
                                                    'completed' => 'fas fa-check-double'
                                                ];
                                            ?>
                                            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium <?php echo $statusClasses[$request['status']]; ?>">
                                                <i class="<?php echo $statusIcons[$request['status']]; ?>"></i>
                                                <span><?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?></span>
                                            </span>
                                        </td>

                                        <!-- Submitted -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($request['submitted_at'])); ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <div class="flex items-center gap-2">
                                                <button onclick="viewDocuments(<?php echo htmlspecialchars(json_encode($request)); ?>)" 
                                                        class="inline-flex items-center gap-1 px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-xs">
                                                    <i class="fas fa-file-alt"></i>
                                                    <span>Documents</span>
                                                </button>
                                                <?php if ($request['status'] === 'pending'): ?>
                                                    <button onclick="openApprovalModal(<?php echo htmlspecialchars(json_encode($request)); ?>)" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors text-xs">
                                                        <i class="fas fa-check"></i>
                                                        <span>Approve</span>
                                                    </button>
                                                    <button onclick="openDeclineModal(<?php echo htmlspecialchars(json_encode($request)); ?>)" 
                                                            class="inline-flex items-center gap-1 px-3 py-1 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors text-xs">
                                                        <i class="fas fa-times"></i>
                                                        <span>Decline</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="viewDetails(<?php echo htmlspecialchars(json_encode($request)); ?>)" 
                                                        class="inline-flex items-center gap-1 px-3 py-1 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors text-xs">
                                                        <i class="fas fa-eye"></i>
                                                        <span>View</span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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

    <!-- Documents Modal -->
    <div id="documentsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Submitted Documents</h3>
                    <p class="text-sm text-gray-500 mt-1">Review all documents before approval</p>
                </div>
                <button onclick="closeDocumentsModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                <div id="documentsContent" class="space-y-6">
                    <!-- Documents will be loaded here -->
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                <button onclick="closeDocumentsModal()" 
                        class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                    Close
                </button>
                <button id="approveFromDocsBtn" onclick="approveFromDocuments()" 
                        class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-check mr-2"></i>Approve Request
                </button>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Approve Request</h3>
                <p class="text-sm text-gray-500 mt-1">Review and approve this request</p>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="renewal_request_id" id="modalRenewalId">
                <input type="hidden" name="action" value="approve">
                
                <div id="modalRequestInfo" class="bg-gray-50 rounded-lg p-4 text-sm space-y-2"></div>
                
                <div>
                    <label for="admin_feedback" class="block text-sm font-medium text-gray-700 mb-2">Admin Feedback (Optional)</label>
                    <textarea name="admin_feedback" id="admin_feedback" rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                              placeholder="Add comments for the tenant..."></textarea>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeApprovalModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition-colors">
                        Approve Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Decline Modal -->
    <div id="declineModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Decline Request</h3>
                <p class="text-sm text-gray-500 mt-1">Provide feedback for the tenant</p>
            </div>
            
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="renewal_request_id" id="declineRenewalId">
                <input type="hidden" name="action" value="decline">
                
                <div id="declineRequestInfo" class="bg-gray-50 rounded-lg p-4 text-sm space-y-2"></div>
                
                <div>
                    <label for="decline_feedback" class="block text-sm font-medium text-gray-700 mb-2">Reason for Decline *</label>
                    <textarea name="admin_feedback" id="decline_feedback" rows="3" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500 focus:border-transparent"
                              placeholder="Explain why this request is being declined..."></textarea>
                </div>
                
                <div class="flex items-center justify-end gap-3 pt-4">
                    <button type="button" onclick="closeDeclineModal()" 
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Decline Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentRequest = null;
        
        function viewDocuments(request) {
            currentRequest = request;
            const content = document.getElementById('documentsContent');
            
            // Build documents HTML
            let documentsHtml = `
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-gray-900 mb-2">Request Information</h4>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><strong>Type:</strong> ${request.request_type === 'renewal' ? '🔄 Renewal' : '📝 New Application'}</div>
                        <div><strong>Tenant:</strong> ${request.tradename}</div>
                        <div><strong>Email:</strong> ${request.user_email}</div>
                        <div><strong>Business Type:</strong> ${request.business_type || 'N/A'}</div>
                        <div><strong>Amount:</strong> ₱${parseFloat(request.total_amount).toFixed(2)}</div>
                        <div><strong>Payment:</strong> ${request.request_type === 'renewal' ? 'Monthly Only' : '3-Month Advance'}</div>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <h4 class="font-semibold text-gray-900">Uploaded Documents</h4>
            `;
            
            // Add document links
            const documents = [
                {name: 'Letter of Intent', field: 'letter_of_intent'},
                {name: 'Business Profile', field: 'business_profile'},
                {name: 'Business Registration', field: 'business_registration'},
                {name: 'Valid ID', field: 'valid_id'},
                {name: 'BIR Registration', field: 'bir_registration'},
                {name: 'Secretary Certificate', field: 'secretary_certificate'},
                {name: 'Financial Statement', field: 'financial_statement'}
            ];
            
            documents.forEach(doc => {
                if (request[doc.field]) {
                    documentsHtml += `
                        <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file-alt text-blue-500"></i>
                                <div>
                                    <div class="font-medium text-gray-900">${doc.name}</div>
                                    <div class="text-sm text-gray-500">${request[doc.field]}</div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="${request[doc.field]}" target="_blank" 
                                   class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-xs">
                                    <i class="fas fa-external-link-alt mr-1"></i>View
                                </a>
                            </div>
                        </div>
                    `;
                }
            });
            
            if (!documents.some(doc => request[doc.field])) {
                documentsHtml += `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>No documents uploaded</p>
                    </div>
                `;
            }
            
            documentsHtml += `</div>`;
            content.innerHTML = documentsHtml;
            
            // Show/hide approve button based on status
            const approveBtn = document.getElementById('approveFromDocsBtn');
            if (request.status === 'pending') {
                approveBtn.style.display = 'inline-flex';
            } else {
                approveBtn.style.display = 'none';
            }
            
            document.getElementById('documentsModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeDocumentsModal() {
            document.getElementById('documentsModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function approveFromDocuments() {
            closeDocumentsModal();
            if (currentRequest) {
                openApprovalModal(currentRequest);
            }
        }
        
        function openApprovalModal(request) {
            document.getElementById('modalRenewalId').value = request.id;
            
            const info = `
                <div><strong>Type:</strong> ${request.request_type === 'renewal' ? '🔄 Renewal' : '📝 New Application'}</div>
                <div><strong>Tenant:</strong> ${request.tradename}</div>
                <div><strong>Amount:</strong> ₱${parseFloat(request.total_amount).toFixed(2)}</div>
                <div><strong>Payment:</strong> ${request.request_type === 'renewal' ? 'Monthly Only' : '3-Month Advance'}</div>
                <div><strong>Account:</strong> ${request.account_action === 'create_new' ? 'Create New' : 'Use Existing'}</div>
            `;
            document.getElementById('modalRequestInfo').innerHTML = info;
            
            document.getElementById('approvalModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function openDeclineModal(request) {
            document.getElementById('declineRenewalId').value = request.id;
            
            const info = `
                <div><strong>Type:</strong> ${request.request_type === 'renewal' ? '🔄 Renewal' : '📝 New Application'}</div>
                <div><strong>Tenant:</strong> ${request.tradename}</div>
                <div><strong>Email:</strong> ${request.user_email}</div>
            `;
            document.getElementById('declineRequestInfo').innerHTML = info;
            
            document.getElementById('declineModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeclineModal() {
            document.getElementById('declineModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function viewDetails(request) {
            // Show basic info in a simple modal
            const info = `
                <div class="space-y-4">
                    <div><strong>Type:</strong> ${request.request_type === 'renewal' ? '🔄 Renewal' : '📝 New Application'}</div>
                    <div><strong>Tenant:</strong> ${request.tradename}</div>
                    <div><strong>Email:</strong> ${request.user_email}</div>
                    <div><strong>Amount:</strong> ₱${parseFloat(request.total_amount).toFixed(2)}</div>
                    <div><strong>Status:</strong> ${request.status}</div>
                    <div><strong>Submitted:</strong> ${new Date(request.submitted_at).toLocaleDateString()}</div>
                    ${request.admin_feedback ? `<div><strong>Admin Feedback:</strong> ${request.admin_feedback}</div>` : ''}
                </div>
            `;
            
            // Create a simple modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
            modal.innerHTML = `
                <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Request Details</h3>
                    </div>
                    <div class="p-6">
                        ${info}
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                        <button onclick="this.closest('.fixed').remove()" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                            Close
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close on outside click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
        
        // Close modals when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) closeApprovalModal();
        });
        
        document.getElementById('declineModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeclineModal();
        });
        
        document.getElementById('documentsModal').addEventListener('click', function(e) {
            if (e.target === this) closeDocumentsModal();
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeApprovalModal();
                closeDeclineModal();
                closeDocumentsModal();
            }
        });
    </script>
</body>
</html>
