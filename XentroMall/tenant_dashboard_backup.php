<?php
session_start();
require 'config.php';
require_once 'contract_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;
$currentPage = $_GET['page'] ?? '';

// Fetch tenant application status, stall_id, and start date
$stmtTenant = $pdo->prepare("SELECT td.status, td.stall_id, p.payment_date as start_date
                             FROM tenant_details td 
                             LEFT JOIN payments p ON td.user_id = p.user_id AND p.status = 'approved'
                             WHERE td.user_id = ? 
                             ORDER BY p.payment_date ASC 
                             LIMIT 1");
$stmtTenant->execute([$userId]);
$tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);

// Handle case when no tenant data is found
if (!$tenantData) {
    $tenantData = ['status' => 'N/A', 'stall_id' => null, 'start_date' => null];
}

$applicationStatus = $tenantData['status'] ?? 'N/A';
$stallId = $tenantData['stall_id'] ?? null;
$startDate = $tenantData['start_date'] ? date('F j, Y', strtotime($tenantData['start_date'])) : 'Not started yet';

// CHECK IF TENANT HAS APPROVED PAYMENT (Permission System)
$stmtApprovedPayment = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'approved'");
$stmtApprovedPayment->execute([$userId]);
$hasApprovedPayment = (int)$stmtApprovedPayment->fetchColumn() > 0;

// If new tenant (no payment) trying to access non-payment pages, redirect
if (!$hasApprovedPayment && !in_array($currentPage, ['', 'payment'])) {
    // Only allow Payment page access
    if ($currentPage !== '' && $currentPage !== 'payment') {
        $_SESSION['warning_message'] = 'Please complete your payment first to access other features.';
        header('Location: tenant_payment.php');
        exit;
    }
}

// Fetch stall details if stall_id exists
$stallDetails = null;
if ($stallId) {
    $stmtStall = $pdo->prepare("SELECT * FROM stalls WHERE id = ?");
    $stmtStall->execute([$stallId]);
    $stallDetails = $stmtStall->fetch(PDO::FETCH_ASSOC);
}

// Fetch tenant latest payment status
$stmtPaymentStatus = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? AND status IN ('approved', 'declined') ORDER BY payment_date DESC LIMIT 1");
$stmtPaymentStatus->execute([$userId]);
$paymentStatus = $stmtPaymentStatus->fetchColumn();

// If no approved/declined payment found, check for pending status
if (!$paymentStatus) {
    $stmtPending = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? ORDER BY payment_date DESC LIMIT 1");
    $stmtPending->execute([$userId]);
    $paymentStatus = $stmtPending->fetchColumn() ?? 'N/A';
}

ensureTenantContractsTable($pdo);
$tenantContracts = [];
try {
    $stmtTenantContracts = $pdo->prepare("
        SELECT tc.id, tc.contract_status, tc.created_at, tc.version, tc.contract_path
        FROM tenant_contracts tc
        INNER JOIN tenant_details td ON td.id = tc.tenant_detail_id
        WHERE td.user_id = ?
        ORDER BY tc.created_at DESC
    ");
    $stmtTenantContracts->execute([$userId]);
    $tenantContracts = $stmtTenantContracts->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tenantContracts = [];
}

// Fetch contract information
$contractData = null;
$contractStatus = 'no_contract';
$daysRemaining = 0;
$canRenew = false;
$lateFee = 0;

// Get tenant_id from tenants table using user's email
$stmtUserEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUserEmail->execute([$userId]);
$userEmail = $stmtUserEmail->fetchColumn();

$stmtTenantId = $pdo->prepare("SELECT id as tenant_id FROM tenants WHERE email = ?");
$stmtTenantId->execute([$userEmail]);
$tenantIdData = $stmtTenantId->fetch();

if ($tenantIdData && $tenantIdData['tenant_id']) {
    $stmtContract = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
    $stmtContract->execute([$tenantIdData['tenant_id']]);
    $contractData = $stmtContract->fetch(PDO::FETCH_ASSOC);
    
    if ($contractData) {
        $expirationDate = new DateTime($contractData['lease_expiration_date']);
        $today = new DateTime();
        $interval = $today->diff($expirationDate);
        $daysRemaining = $interval->days;
        
        // Determine contract status
        if ($today > $expirationDate) {
            // Expired
            $daysRemaining = -$daysRemaining;
            if (abs($daysRemaining) <= 30) {
                $contractStatus = 'grace_period';
                $canRenew = true;
                // Get late fee from settings
                $stmtFee = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'late_renewal_fee'");
                $stmtFee->execute();
                $lateFee = $stmtFee->fetchColumn() ?: 500;
            } else {
                $contractStatus = 'terminated';
                $canRenew = false;
            }
        } elseif ($daysRemaining <= 30) {
            $contractStatus = 'expiring_soon';
            $canRenew = true;
        } elseif ($daysRemaining <= 7) {
            $contractStatus = 'urgent';
            $canRenew = true;
        } else {
            $contractStatus = 'active';
            $canRenew = ($daysRemaining <= 90); // Can renew 90 days before expiration
        }
    }
}

// Count approved stalls for additional stall button
$stmtApprovedCount = $pdo->prepare("SELECT COUNT(*) FROM tenant_details WHERE user_id = ? AND status = 'approved'");
$stmtApprovedCount->execute([$userId]);
$approvedStallsCount = $stmtApprovedCount->fetchColumn();

// Check for pending renewal request
$pendingRenewal = null;
$approvedRenewal = null;
if ($tenantIdData && $tenantIdData['tenant_id']) {
    // Check for pending renewal (waiting for admin approval)
    $stmtPendingRenewal = $pdo->prepare("SELECT * FROM contract_renewals WHERE tenant_id = ? AND status = 'pending' ORDER BY submitted_at DESC LIMIT 1");
    $stmtPendingRenewal->execute([$tenantIdData['tenant_id']]);
    $pendingRenewal = $stmtPendingRenewal->fetch(PDO::FETCH_ASSOC);
    
    // Check for approved renewal (waiting for payment)
    $stmtApprovedRenewal = $pdo->prepare("SELECT * FROM contract_renewals WHERE tenant_id = ? AND status = 'approved' AND payment_proof IS NULL ORDER BY submitted_at DESC LIMIT 1");
    $stmtApprovedRenewal->execute([$tenantIdData['tenant_id']]);
    $approvedRenewal = $stmtApprovedRenewal->fetch(PDO::FETCH_ASSOC);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal'])) {
    $tenantId = $_SESSION['user_id'] ?? '';
    $renewalDate = $_POST['renewal_date'] ?? '';
    
    if ($tenantId && $renewalDate) {
        try {
            $pdo->beginTransaction();

            // Insert renewal request
            $stmt = $pdo->prepare("INSERT INTO renewal_requests (id, renewal_date) VALUES (?, ?)");
            $stmt->execute([$tenantId, $renewalDate]);

            // Handle Extended BIR upload if provided
            $extendedBirPath = null;
            if (isset($_FILES['extended_bir']) && $_FILES['extended_bir']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/applications/' . $tenantId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $tmpName = $_FILES['extended_bir']['tmp_name'];
                $fileName = basename($_FILES['extended_bir']['name']);
                $targetFile = $uploadDir . uniqid() . '_extended_bir_' . $fileName;
                
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $extendedBirPath = $targetFile;
                    
                    // Insert or update extended BIR submission
                    try {
                        $stmt = $pdo->prepare("INSERT INTO application_submissions (user_id, extended_bir_registration) VALUES (?, ?) ON DUPLICATE KEY UPDATE extended_bir_registration = VALUES(extended_bir_registration)");
                        $stmt->execute([$tenantId, $extendedBirPath]);
                    } catch (PDOException $e) {
                        // If table doesn't exist or has different structure, try alternative approach
                        error_log("Extended BIR submission error: " . $e->getMessage());
                    }
                }
            }

            $pdo->commit();

            if ($extendedBirPath) {
                $_SESSION['renewal_success_message'] = "Renewal request submitted successfully with Extended BIR document.";
            } else {
                $_SESSION['renewal_success_message'] = "Renewal request submitted successfully.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['renewal_error_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['renewal_error_message'] = "Please provide all required fields.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: tenant_dashboard.php?page=renewal");
    exit;
}

// Handle Extended BIR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_extended_bir'])) {
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? '';
    $businessName = $_POST['business_name'] ?? '';
    $tinNumber = $_POST['tin_number'] ?? '';
    $businessAddress = $_POST['business_address'] ?? '';
    $contactPerson = $_POST['contact_person'] ?? '';
    $contactNumber = $_POST['contact_number'] ?? '';
    $submissionType = $_POST['submission_type'] ?? 'application';
    
    // Get user email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userEmail = $stmt->fetchColumn() ?? '';
    
    $uploadDir = 'uploads/extended_bir/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($_FILES['extended_bir_document']) && $_FILES['extended_bir_document']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['extended_bir_document']['tmp_name'];
        $fileName = basename($_FILES['extended_bir_document']['name']);
        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
        
        if (move_uploaded_file($tmpName, $targetFile)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO extended_bir (user_id, username, email, business_name, tin_number, business_address, contact_person, contact_number, document_path, submission_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $username, $userEmail, $businessName, $tinNumber, $businessAddress, $contactPerson, $contactNumber, $targetFile, $submissionType]);
                $_SESSION['bir_success_message'] = "Extended BIR Registration submitted successfully.";
            } catch (PDOException $e) {
                error_log("Database insert error (extended_bir): " . $e->getMessage());
                $_SESSION['bir_error_message'] = "There was an error submitting your extended BIR registration. Please try again later.";
            }
        } else {
            $_SESSION['bir_error_message'] = "Failed to upload extended BIR document.";
        }
    } else {
        $_SESSION['bir_error_message'] = "No extended BIR document uploaded or upload error.";
    }
    
    header("Location: tenant_dashboard.php?page=extended_bir");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Tenant Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    body { font-family: 'Inter', sans-serif; }
    .scrollbar-thin::-webkit-scrollbar { height: 6px; width: 6px; }
    .scrollbar-thin::-webkit-scrollbar-thumb { background-color: #10b981; border-radius: 8px; }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-emerald-700 w-64 flex flex-col text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out fixed inset-y-0 left-0 z-40 lg:static lg:inset-auto">
      <div class="flex items-center gap-3 px-6 py-5 border-b border-emerald-600">
        <img alt="Logo" class="w-10 h-10 rounded-full" src="img/logo.jpg" />
        <span class="font-bold text-2xl tracking-tight">
          TMS
          <span class="ml-2 inline-block w-2.5 h-2.5 bg-green-400 rounded-full border-2 border-white" title="Online"></span>
        </span>
      </div>
      <nav class="flex flex-col flex-grow px-4 py-6 space-y-1 overflow-y-auto scrollbar-thin">
        <p class="text-emerald-200 text-sm font-semibold mb-2 px-2">Tenant Menu</p>
        
        <!-- Dashboard - Always accessible -->
        <a href="tenant_dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === '') ? 'bg-emerald-600 text-white' : 'hover:bg-emerald-600'; ?>">
          <i class="fas fa-home w-5"></i>
          Dashboard
        </a>
        
        <!-- Area Management - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? '?page=space' : 'javascript:void(0)'; ?>" 
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === 'space') ? 'bg-emerald-600 text-white' : ($hasApprovedPayment ? 'hover:bg-emerald-600' : 'opacity-50 cursor-not-allowed'); ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-store w-5"></i>
          Area Management
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-red-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
        
        <!-- Work Permit - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? 'work_permit_form.php' : 'javascript:void(0)'; ?>"
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo $hasApprovedPayment ? 'hover:bg-emerald-600' : 'opacity-50 cursor-not-allowed'; ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-hard-hat w-5"></i>
          Work Permit
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-red-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
        
        <!-- Renewal - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? '?page=renewal' : 'javascript:void(0)'; ?>"
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === 'renewal') ? 'bg-emerald-600 text-white' : ($hasApprovedPayment ? 'hover:bg-emerald-600' : 'opacity-50 cursor-not-allowed'); ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-sync-alt w-5"></i>
          Renewal
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-red-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
        
        <!-- Payment - Always accessible (PRIORITY) -->
        <a href="tenant_payment.php" class="flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo strpos($_SERVER['PHP_SELF'], 'tenant_payment.php') !== false ? 'bg-emerald-600 text-white' : 'hover:bg-emerald-600'; ?> <?php echo !$hasApprovedPayment ? 'font-bold bg-red-600/30' : ''; ?>">
          <i class="fas fa-credit-card w-5"></i>
          Payment
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-red-500 px-2 py-1 rounded font-semibold">PENDING</span>
          <?php endif; ?>
        </a>
        
        <!-- View Status - Locked if no payment -->
        <a href="<?php echo $hasApprovedPayment ? '?page=status' : 'javascript:void(0)'; ?>"
           onclick="<?php echo !$hasApprovedPayment ? "event.preventDefault(); alert('Please complete your payment first.'); return false;" : ''; ?>"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition text-sm font-medium <?php echo ($currentPage === 'status') ? 'bg-emerald-600 text-white' : ($hasApprovedPayment ? 'hover:bg-emerald-600' : 'opacity-50 cursor-not-allowed'); ?>"
           title="<?php echo !$hasApprovedPayment ? 'Locked - Complete payment first' : ''; ?>">
          <i class="fas fa-info-circle w-5"></i>
          View Status
          <?php if (!$hasApprovedPayment): ?>
            <span class="ml-auto text-xs bg-red-500 px-2 py-1 rounded">Locked</span>
          <?php endif; ?>
        </a>
      </nav>
    </aside>

    <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden"></div>

    <!-- Main content -->
    <div class="flex flex-col flex-grow">
      <!-- Topbar -->
      <header class="flex items-center justify-between px-6 py-4 bg-emerald-700 text-white shadow">
        <button id="sidebarToggle" aria-label="Toggle menu" aria-expanded="false" class="text-white text-xl lg:hidden" type="button">
          <i class="fas fa-bars"></i>
        </button>
        <form aria-label="Site search" class="hidden md:flex items-center bg-emerald-600 rounded-full px-4 py-2 w-full max-w-lg" role="search">
          <input aria-label="Search input" class="bg-transparent placeholder:text-emerald-200 text-white text-sm focus:outline-none flex-grow" placeholder="Search..." type="search"/>
          <button aria-label="Search" class="text-emerald-200 hover:text-white ml-2" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </form>
        <div class="flex items-center gap-5 relative">
          <button id="notificationButton" aria-label="Notifications" class="relative text-white hover:text-emerald-100" type="button">
            <i class="far fa-bell text-lg"></i>
            <?php
            $userId = $_SESSION['user_id'] ?? 0;
            $stmtNotifications = $pdo->prepare(
                "SELECT id, message, 'notif' AS type, created_at, NULL AS category FROM notifications WHERE user_id = ? AND is_read = 0
                 UNION ALL
                 SELECT CONCAT('ann_', id) AS id, CONCAT(title, ': ', description) AS message, 'announcement' AS type, created_at, category FROM announcements WHERE date >= CURDATE()
                 ORDER BY created_at DESC"
            );
            $stmtNotifications->execute([$userId]);
            $notifications = $stmtNotifications->fetchAll();
            $unreadCount = count($notifications);
            if ($unreadCount > 0) {
                echo '<span id="notificationBadge" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 bg-red-500 rounded-full text-[11px] leading-[18px] text-center border-2 border-emerald-700">' . $unreadCount . '</span>';
            }
            ?>
          </button>

          <!-- Notification Dropdown -->
          <div id="notificationDropdown" class="hidden absolute right-0 top-10 w-96 bg-white rounded-xl shadow-xl py-2 z-40 border border-gray-100 max-h-[480px] overflow-y-auto scrollbar-thin">
            <div class="sticky top-0 bg-white px-4 py-3 border-b border-gray-100 flex items-center justify-between">
              <h3 class="text-base font-semibold text-gray-900">Notifications</h3>
              <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full"><?php echo $unreadCount; ?> New</span>
            </div>
            <?php if ($unreadCount > 0): ?>
              <div class="divide-y divide-gray-100">
                <?php foreach ($notifications as $notification): ?>
                  <div class="notification-item px-4 py-3 hover:bg-gray-50 transition cursor-pointer" 
                       data-id="<?php echo htmlspecialchars($notification['id']); ?>" 
                       data-type="<?php echo htmlspecialchars($notification['type']); ?>"
                       onclick="showNotificationModal(
                         '<?php echo addslashes(htmlspecialchars($notification['category'] ?? 'Notification')); ?>',
                         '<?php echo addslashes(htmlspecialchars($notification['message'])); ?>',
                         '<?php echo date('M d, Y h:i A', strtotime($notification['created_at'] ?? 'now')); ?>'
                       )">
                    <div class="flex items-start gap-3">
                      <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-bell text-sm"></i>
                      </div>
                      <div class="flex-1">
                        <div class="flex items-center justify-between">
                          <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['category'] ?? 'Notification'); ?></p>
                          <span class="text-[11px] text-gray-500"><?php echo date('h:i A', strtotime($notification['created_at'] ?? 'now')); ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3">
                <button onclick="showAllNotifications()" class="w-full py-2 text-sm font-medium text-emerald-700 hover:text-emerald-800">View all notifications</button>
              </div>
            <?php else: ?>
              <div class="px-4 py-8 text-center">
                <div class="mx-auto w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mb-2">
                  <i class="far fa-bell text-xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 text-sm">No new notifications</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Notification Modal -->
          <div id="notificationModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-md overflow-hidden">
              <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Notification</h3>
                <button onclick="closeModal('notificationModal')" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times text-xl"></i>
                </button>
              </div>
              <div class="p-6 space-y-2 max-h-[70vh] overflow-y-auto scrollbar-thin">
                <span id="modalCategory" class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800"></span>
                <p id="modalDate" class="text-sm text-gray-500"></p>
                <p id="modalMessage" class="text-gray-700 whitespace-pre-line"></p>
              </div>
              <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 text-right">
                <button onclick="closeModal('notificationModal')" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Close</button>
              </div>
            </div>
          </div>

          <!-- All Notifications Modal -->
          <div id="allNotificationsModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[85vh] overflow-hidden flex flex-col">
              <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">All Notifications</h3>
                <button onclick="closeModal('allNotificationsModal')" class="text-gray-400 hover:text-gray-500">
                  <i class="fas fa-times text-xl"></i>
                </button>
              </div>
              <div class="overflow-y-auto flex-1 divide-y divide-gray-100 scrollbar-thin">
                <?php foreach ($notifications as $notification): ?>
                  <div class="p-4 hover:bg-gray-50 cursor-pointer" 
                       onclick="showNotificationModal(
                         '<?php echo addslashes(htmlspecialchars($notification['category'] ?? 'Notification')); ?>',
                         '<?php echo addslashes(htmlspecialchars($notification['message'])); ?>',
                         '<?php echo date('M d, Y h:i A', strtotime($notification['created_at'] ?? 'now')); ?>'
                       )">
                    <div class="flex items-start gap-3">
                      <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fas fa-bell text-sm"></i>
                      </div>
                      <div class="flex-1">
                        <div class="flex items-center justify-between">
                          <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($notification['category'] ?? 'Notification'); ?></p>
                          <span class="text-[11px] text-gray-500"><?php echo date('M d, h:i A', strtotime($notification['created_at'] ?? 'now')); ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 text-right">
                <button onclick="closeModal('allNotificationsModal')" class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">Close</button>
              </div>
            </div>
          </div>

          <script>
            function showNotificationModal(category, message, date) {
              document.getElementById('modalCategory').textContent = category;
              document.getElementById('modalMessage').textContent = message;
              document.getElementById('modalDate').textContent = date;
              document.getElementById('notificationModal').classList.remove('hidden');
              document.body.style.overflow = 'hidden';
            }
            function showAllNotifications() {
              document.getElementById('allNotificationsModal').classList.remove('hidden');
              document.body.style.overflow = 'hidden';
            }
            function closeModal(modalId) {
              document.getElementById(modalId).classList.add('hidden');
              document.body.style.overflow = 'auto';
            }
            window.addEventListener('click', (event) => {
              if (event.target.id === 'notificationModal' || event.target.id === 'allNotificationsModal') {
                closeModal(event.target.id);
              }
            });
            document.addEventListener('keydown', (event) => {
              if (event.key === 'Escape') {
                closeModal('notificationModal');
                closeModal('allNotificationsModal');
              }
            });
          </script>

          <div class="relative" id="userMenuButton" tabindex="0" aria-haspopup="true" aria-expanded="false">
            <div class="flex items-center gap-2 cursor-pointer group">
              <div class="relative">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-medium shadow">
                  <?php echo strtoupper(substr(htmlspecialchars($_SESSION['username'] ?? 'U'), 0, 1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-emerald-700"></span>
              </div>
              <div class="text-left hidden sm:block">
                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
                <p class="text-xs text-emerald-200">Tenant</p>
              </div>
              <i class="fas fa-chevron-down text-white text-xs transition-transform duration-200 group-hover:rotate-180"></i>
            </div>
            <div id="userDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white rounded-xl shadow-xl py-2 z-30 border border-gray-100">
              <div class="px-4 py-3 border-b border-gray-100">
                <p class="text-sm text-gray-500">Signed in as</p>
                <p class="text-sm font-semibold text-gray-900 truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
              </div>
              <a href="profile_settings.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-cog w-5 text-gray-400 mr-3"></i>
                <span>Settings</span>
              </a>
              <a href="about.php" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50">
                <i class="fas fa-user-circle w-5 text-gray-400 mr-3"></i>
                <span>Personal Info</span>
              </a>
              <div class="border-t border-gray-100 my-1"></div>
              <a href="logout.php" class="flex items-center px-4 py-3 text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>
          <script>
            const userMenuButton = document.getElementById('userMenuButton');
            const userDropdown = document.getElementById('userDropdown');
            userMenuButton.addEventListener('click', () => {
              userDropdown.classList.toggle('hidden');
              const expanded = userMenuButton.getAttribute('aria-expanded') === 'true';
              userMenuButton.setAttribute('aria-expanded', (!expanded).toString());
            });

            const notificationButton = document.getElementById('notificationButton');
            const notificationDropdown = document.getElementById('notificationDropdown');
            notificationButton.addEventListener('click', (e) => {
              e.stopPropagation();
              notificationDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', (event) => {
              if (!userMenuButton.contains(event.target)) {
                userDropdown.classList.add('hidden');
                userMenuButton.setAttribute('aria-expanded', 'false');
              }
              if (!notificationButton.contains(event.target)) {
                notificationDropdown.classList.add('hidden');
              }
            });

            // Mark notification as read on click
            document.querySelectorAll('.notification-item').forEach(item => {
              item.addEventListener('click', () => {
                const notificationId = item.getAttribute('data-id');
                const notificationType = item.getAttribute('data-type');
                if (notificationType === 'notif') {
                  fetch('mark_notification_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + encodeURIComponent(notificationId)
                  }).then(response => {
                    if (response.ok) {
                      item.remove();
                      const badge = document.getElementById('notificationBadge');
                      if (badge) badge.remove();
                      if (document.querySelectorAll('.notification-item').length === 0) {
                        notificationDropdown.innerHTML = '<div class="px-4 py-6 text-center text-gray-500">No new notifications</div>';
                      }
                    }
                  });
                } else {
                  item.remove();
                  const badge = document.getElementById('notificationBadge');
                  if (badge && document.querySelectorAll('.notification-item').length === 0) {
                    notificationDropdown.innerHTML = '<div class="px-4 py-6 text-center text-gray-500">No new notifications</div>';
                    badge.remove();
                  }
                }
              });
            });
          </script>
        </div>
      </header>

      <!-- Content -->
      <main class="p-6 space-y-6 overflow-auto scrollbar-thin">
        <div class="max-w-6xl mx-auto space-y-6">
        <?php
          $page = $_GET['page'] ?? '';
          switch ($page) {
            case 'renewal':
                $successMessage = $_SESSION['success'] ?? '';
                $errorMessage = $_SESSION['error'] ?? '';
                unset($_SESSION['success'], $_SESSION['error']);
        ?>
              <div class="bg-white rounded-xl shadow border border-gray-100 p-6 max-w-2xl mx-auto">
                <h1 class="text-2xl font-semibold text-gray-900 mb-1">Contract Renewal</h1>
                <p class="text-sm text-gray-500 mb-6">Manage your contract renewal and view contract details.</p>
                
                <?php if (!empty($successMessage)): ?>
                  <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 border border-emerald-200"><?php echo htmlspecialchars($successMessage); ?></div>
                <?php endif; ?>
                <?php if (!empty($errorMessage)): ?>
                  <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 border border-red-200"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>

                <?php if ($contractData): ?>
                  <!-- Contract Status Card -->
                  <div class="mb-6 p-5 rounded-lg border-2 <?php 
                    echo $contractStatus === 'active' ? 'bg-green-50 border-green-300' : 
                         ($contractStatus === 'expiring_soon' ? 'bg-yellow-50 border-yellow-300' : 
                         ($contractStatus === 'urgent' ? 'bg-orange-50 border-orange-300' : 
                         ($contractStatus === 'grace_period' ? 'bg-red-50 border-red-300' : 'bg-gray-50 border-gray-300')));
                  ?>">
                    <div class="flex items-center justify-between mb-4">
                      <h3 class="text-lg font-semibold <?php 
                        echo $contractStatus === 'active' ? 'text-green-900' : 
                             ($contractStatus === 'expiring_soon' ? 'text-yellow-900' : 
                             ($contractStatus === 'urgent' ? 'text-orange-900' : 
                             ($contractStatus === 'grace_period' ? 'text-red-900' : 'text-gray-900')));
                      ?>">Contract Status</h3>
                      <span class="px-3 py-1 rounded-full text-sm font-semibold <?php 
                        echo $contractStatus === 'active' ? 'bg-green-200 text-green-800' : 
                             ($contractStatus === 'expiring_soon' ? 'bg-yellow-200 text-yellow-800' : 
                             ($contractStatus === 'urgent' ? 'bg-orange-200 text-orange-800' : 
                             ($contractStatus === 'grace_period' ? 'bg-red-200 text-red-800' : 'bg-gray-200 text-gray-800')));
                      ?>">
                        <?php 
                          echo $contractStatus === 'active' ? '🟢 Active' : 
                               ($contractStatus === 'expiring_soon' ? '🟡 Expiring Soon' : 
                               ($contractStatus === 'urgent' ? '🟠 Urgent Renewal' : 
                               ($contractStatus === 'grace_period' ? '🔴 Grace Period' : '⚫ Terminated')));
                        ?>
                      </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                      <div>
                        <span class="text-gray-600 font-medium">Contract Start:</span>
                        <p class="text-gray-900 font-semibold mt-1"><?php echo date('F j, Y', strtotime($contractData['lease_start_date'])); ?></p>
                      </div>
                      <div>
                        <span class="text-gray-600 font-medium">Contract End:</span>
                        <p class="text-gray-900 font-semibold mt-1"><?php echo date('F j, Y', strtotime($contractData['lease_expiration_date'])); ?></p>
                      </div>
                      <div class="md:col-span-2 pt-3 border-t">
                        <span class="text-gray-600 font-medium">Days Remaining:</span>
                        <p class="text-2xl font-bold mt-1 <?php 
                          echo $daysRemaining > 30 ? 'text-green-600' : 
                               ($daysRemaining > 0 ? 'text-orange-600' : 'text-red-600');
                        ?>">
                          <?php 
                            if ($daysRemaining > 0) {
                              echo $daysRemaining . ' days';
                            } elseif ($daysRemaining < 0) {
                              echo 'Expired ' . abs($daysRemaining) . ' days ago';
                            } else {
                              echo 'Expires today!';
                            }
                          ?>
                        </p>
                      </div>
                    </div>
                    
                    <?php if ($contractStatus === 'grace_period'): ?>
                      <div class="mt-4 p-3 bg-red-100 border border-red-300 rounded-lg">
                        <p class="text-sm text-red-800">
                          <i class="fas fa-exclamation-triangle mr-2"></i>
                          <strong>Warning:</strong> Your contract has expired. You have <?php echo 30 - abs($daysRemaining); ?> days remaining in the grace period to renew.
                          Late renewal fee: <strong>₱<?php echo number_format($lateFee, 2); ?></strong>
                        </p>
                      </div>
                    <?php elseif ($contractStatus === 'urgent' || $contractStatus === 'expiring_soon'): ?>
                      <div class="mt-4 p-3 bg-yellow-100 border border-yellow-300 rounded-lg">
                        <p class="text-sm text-yellow-800">
                          <i class="fas fa-info-circle mr-2"></i>
                          Your contract is expiring soon. Please submit a renewal request to avoid interruption.
                        </p>
                      </div>
                    <?php endif; ?>
                  </div>

                  <?php if ($approvedRenewal): ?>
                    <!-- Approved Renewal - Payment & Extended BIR Submission -->
                    <div class="mb-6 space-y-4">
                      <!-- Approval Notice -->
                      <div class="p-5 bg-green-50 border-2 border-green-300 rounded-lg">
                        <h3 class="text-lg font-semibold text-green-900 mb-2">
                          <i class="fas fa-check-circle mr-2"></i>Renewal Request Approved!
                        </h3>
                        <p class="text-sm text-green-800 mb-3">
                          Your renewal request has been approved by the admin. Please submit your payment and Extended BIR documents to complete the renewal process.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                          <div>
                            <span class="text-green-700 font-medium">Approved On:</span>
                            <p class="text-green-900 font-semibold"><?php echo date('F j, Y', strtotime($approvedRenewal['processed_at'] ?? $approvedRenewal['submitted_at'])); ?></p>
                          </div>
                          <div>
                            <span class="text-green-700 font-medium">Total Amount Due:</span>
                            <p class="text-green-900 font-semibold text-lg">₱<?php echo number_format($approvedRenewal['total_amount'], 2); ?></p>
                          </div>
                        </div>
                      </div>

                      <!-- Combined Payment & Extended BIR Submission Form -->
                      <div class="p-5 bg-gradient-to-br from-emerald-50 to-blue-50 border-2 border-emerald-300 rounded-lg shadow-sm">
                        <div class="flex items-center gap-3 mb-4">
                          <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center">
                            <i class="fas fa-file-invoice-dollar text-white text-xl"></i>
                          </div>
                          <div>
                            <h3 class="text-lg font-semibold text-gray-900">Complete Renewal Submission</h3>
                            <p class="text-sm text-gray-600">Upload payment proof and Extended BIR document</p>
                          </div>
                        </div>

                        <form action="submit_renewal_complete.php" method="post" enctype="multipart/form-data" class="space-y-4">
                          <input type="hidden" name="renewal_id" value="<?php echo $approvedRenewal['id']; ?>" />
                          
                          <!-- Payment Amount Display -->
                          <div class="p-4 bg-white border border-gray-300 rounded-lg">
                            <h4 class="text-sm font-semibold text-gray-700 mb-3">Payment Details</h4>
                            <div class="space-y-2 text-sm">
                              <div class="flex justify-between">
                                <span class="text-gray-600">Amount to Pay:</span>
                                <span class="text-emerald-600 font-bold text-lg">₱<?php echo number_format($approvedRenewal['total_amount'], 2); ?></span>
                              </div>
                            </div>
                          </div>

                          <!-- Payment Proof Upload -->
                          <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                              <i class="fas fa-receipt mr-2"></i>Payment Proof <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="payment_proof" required accept="image/*,.pdf"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                            <p class="text-xs text-gray-500 mt-1">Upload receipt or proof of payment (Image or PDF)</p>
                          </div>

                          <!-- Extended BIR Document Upload -->
                          <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                              <i class="fas fa-file-pdf mr-2"></i>Extended BIR Document <span class="text-red-500">*</span>
                            </label>
                            <input type="file" name="bir_document" required accept=".pdf,image/*"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                            <p class="text-xs text-gray-500 mt-1">Upload Extended BIR document (PDF or Image)</p>
                          </div>

                          <button type="submit" class="w-full px-5 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold transition shadow-md">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Submit Payment & BIR Documents
                          </button>
                        </form>
                      </div>
                    </div>

                  <?php elseif ($pendingRenewal): ?>
                    <!-- Pending Renewal Request -->
                    <div class="mb-6 p-5 bg-blue-50 border-2 border-blue-300 rounded-lg">
                      <h3 class="text-lg font-semibold text-blue-900 mb-3">
                        <i class="fas fa-clock mr-2"></i>Pending Renewal Request
                      </h3>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div>
                          <span class="text-blue-700 font-medium">Submitted:</span>
                          <p class="text-blue-900 font-semibold"><?php echo date('F j, Y', strtotime($pendingRenewal['submitted_at'])); ?></p>
                        </div>
                        <div>
                          <span class="text-blue-700 font-medium">Total Amount:</span>
                          <p class="text-blue-900 font-semibold">₱<?php echo number_format($pendingRenewal['total_amount'], 2); ?></p>
                        </div>
                        <?php if ($pendingRenewal['late_renewal_fee'] > 0): ?>
                          <div class="md:col-span-2">
                            <span class="text-blue-700 font-medium">Late Fee:</span>
                            <p class="text-red-600 font-semibold">₱<?php echo number_format($pendingRenewal['late_renewal_fee'], 2); ?></p>
                          </div>
                        <?php endif; ?>
                      </div>
                      <p class="mt-3 text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-1"></i>
                        Your renewal request is being reviewed by the admin. You will be notified once it's processed.
                      </p>
                    </div>
                  <?php elseif ($canRenew): ?>
                    <!-- Renewal Form - Only shows when within 90 days -->
                    <div class="p-5 bg-gradient-to-br from-emerald-50 to-blue-50 border-2 border-emerald-300 rounded-lg shadow-sm">
                      <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center">
                          <i class="fas fa-sync-alt text-white text-xl"></i>
                        </div>
                        <div>
                          <h3 class="text-lg font-semibold text-gray-900">Submit Renewal Request</h3>
                          <p class="text-sm text-gray-600">Renew your contract for another year</p>
                        </div>
                      </div>
                      
                      <form action="submit_renewal.php" method="post" enctype="multipart/form-data" class="space-y-4">
                        <!-- Amount Display -->
                        <div class="p-4 bg-white border border-gray-300 rounded-lg">
                          <h4 class="text-sm font-semibold text-gray-700 mb-3">Renewal Payment</h4>
                          <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                              <span class="text-gray-600">Monthly Rate:</span>
                              <span class="font-semibold">₱<?php echo number_format($stallDetails['monthly_rate'] ?? 0, 2); ?></span>
                            </div>
                            <?php if ($lateFee > 0): ?>
                              <div class="flex justify-between text-red-600">
                                <span>Late Renewal Fee:</span>
                                <span class="font-semibold">₱<?php echo number_format($lateFee, 2); ?></span>
                              </div>
                            <?php endif; ?>
                            <div class="flex justify-between pt-2 border-t border-gray-300 text-base">
                              <span class="font-bold">Total Amount:</span>
                              <span class="font-bold text-emerald-600">₱<?php echo number_format(($stallDetails['monthly_rate'] ?? 0) + $lateFee, 2); ?></span>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Payment Proof Upload -->
                        <div>
                          <label for="payment_proof" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-upload mr-1"></i>Upload Payment Proof
                          </label>
                          <input type="file" id="payment_proof" name="payment_proof" accept="image/*,.pdf" required
                                 class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                          <p class="text-xs text-gray-500 mt-1">Upload proof of payment (Image or PDF)</p>
                        </div>
                        
                        <button type="submit" class="w-full px-5 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold transition">
                          <i class="fas fa-paper-plane mr-2"></i>
                          Submit Renewal Request
                        </button>
                      </form>
                    </div>
                  <?php elseif ($contractStatus === 'terminated'): ?>
                    <!-- Terminated Contract -->
                    <div class="p-5 bg-red-50 border-2 border-red-300 rounded-lg text-center">
                      <i class="fas fa-ban text-red-400 text-3xl mb-3"></i>
                      <h3 class="text-lg font-semibold text-red-900 mb-2">Contract Terminated</h3>
                      <p class="text-red-700">Your contract has been terminated. Please contact the admin for assistance.</p>
                    </div>
                  <?php else: ?>
                    <!-- Renewal Request Form - NO PAYMENT YET -->
                    <div class="p-5 bg-gradient-to-br from-emerald-50 to-blue-50 border-2 border-emerald-300 rounded-lg shadow-sm">
                      <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center">
                          <i class="fas fa-sync-alt text-white text-xl"></i>
                        </div>
                        <div>
                          <h3 class="text-lg font-semibold text-gray-900">Request Contract Renewal</h3>
                          <p class="text-sm text-gray-600">Submit a renewal request to extend your contract</p>
                        </div>
                      </div>
                      
                      <!-- Renewal Details -->
                      <div class="p-4 bg-white border border-gray-300 rounded-lg mb-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Renewal Information</h4>
                        <div class="space-y-2 text-sm">
                          <div class="flex justify-between">
                            <span class="text-gray-600">Current Contract End:</span>
                            <span class="font-semibold"><?php echo date('M j, Y', strtotime($contractData['lease_expiration_date'])); ?></span>
                          </div>
                          <div class="flex justify-between">
                            <span class="text-gray-600">New Contract Duration:</span>
                            <span class="font-semibold">12 months</span>
                          </div>
                          <div class="flex justify-between">
                            <span class="text-gray-600">Monthly Rate:</span>
                            <span class="font-semibold">₱<?php echo number_format($stallDetails['monthly_rate'] ?? 0, 2); ?></span>
                          </div>
                          <?php if ($lateFee > 0): ?>
                            <div class="flex justify-between text-red-600">
                              <span>Late Renewal Fee:</span>
                              <span class="font-semibold">₱<?php echo number_format($lateFee, 2); ?></span>
                            </div>
                          <?php endif; ?>
                          <div class="flex justify-between pt-2 border-t border-gray-300">
                            <span class="text-gray-700 font-semibold">Total Amount Due:</span>
                            <span class="text-emerald-600 font-bold text-lg">₱<?php echo number_format((($stallDetails['monthly_rate'] ?? 0) * 12) + $lateFee, 2); ?></span>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Info Notice -->
                      <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg mb-4">
                        <p class="text-sm text-blue-800">
                          <i class="fas fa-info-circle mr-2"></i>
                          After your renewal request is approved by the admin, you will be able to submit your payment and Extended BIR documents.
                        </p>
                      </div>
                      
                      <form action="submit_renewal.php" method="post" class="space-y-4">
                        <button type="submit" class="w-full px-5 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold transition shadow-md">
                          <i class="fas fa-paper-plane mr-2"></i>
                          Submit Renewal Request
                        </button>
                      </form>
                    </div>
                  <?php endif; ?>
                  
                <?php else: ?>
                  <!-- No Contract -->
                  <div class="p-8 bg-gray-50 border border-gray-200 rounded-lg text-center">
                    <i class="fas fa-file-contract text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">No Active Contract</h3>
                    <p class="text-gray-600">You don't have an active contract yet. Your contract will be created once your first payment is approved.</p>
                  </div>
                <?php endif; ?>
              </div>
        <?php
              break;

            case 'payment':
                $paymentSuccessMessage = '';
                $paymentErrorMessage = '';
                $userId = $_SESSION['user_id'] ?? null;
                $waterBill = 0; $electricBill = 0; $monthlyRate = 0;
                if ($userId) {
                    // Prefer per-tenant expenses if available
                    try {
                        $stmt = $pdo->prepare("SELECT water_bill, electric_bill FROM tenant_expenses WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$userId]);
                        $tExpense = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $tExpense = null; // table might not exist yet; fallback below
                    }
                    if ($tExpense) {
                        $waterBill = (float)($tExpense['water_bill'] ?? 0);
                        $electricBill = (float)($tExpense['electric_bill'] ?? 0);
                    }
                    // No fallback - bills will be 0 until admin sets them

                    $stmt = $pdo->prepare("SELECT stall_id FROM tenant_details WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($tenant) {
                        $stallId = $tenant['stall_id'];
                        $stmt = $pdo->prepare("SELECT monthly_rate FROM stalls WHERE id = ?");
                        $stmt->execute([$stallId]);
                        $stall = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($stall) { $monthlyRate = (float)$stall['monthly_rate']; }
                    }
                }
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
                    // Validate that user exists in database
                    $stmtCheckUser = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                    $stmtCheckUser->execute([$userId]);
                    if (!$stmtCheckUser->fetch()) {
                        $paymentErrorMessage = "Invalid user session. Please logout and login again.";
                    } elseif (isset($_FILES['payment_image']) && $_FILES['payment_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/payments/' . $userId . '/';
                        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
                        $tmpName = $_FILES['payment_image']['tmp_name'];
                        $fileName = basename($_FILES['payment_image']['name']);
                        $targetFile = $uploadDir . uniqid() . '_' . $fileName;
                        if (move_uploaded_file($tmpName, $targetFile)) {
                            try {
                                $stmtInsert = $pdo->prepare("INSERT INTO payments (user_id, payment_image) VALUES (?, ?)");
                                $stmtInsert->execute([$userId, $targetFile]);
                                $paymentSuccessMessage = "Payment image uploaded successfully.";
                            } catch (PDOException $e) {
                                $paymentErrorMessage = "Database error: " . $e->getMessage();
                            }
                        } else { $paymentErrorMessage = "Failed to move uploaded file."; }
                    } else { $paymentErrorMessage = "Please select a valid image file."; }
                }
        ?>
              <div class="bg-white rounded-xl shadow border border-gray-100 p-6 max-w-lg mx-auto">
                <h1 class="text-2xl font-semibold text-gray-900 mb-1">Upload Payment Image</h1>
                <p class="text-sm text-gray-500 mb-4">Please upload your proof of payment for processing.</p>

                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg mb-6">
                  <p class="font-medium mb-1">Payment Remarks</p>
                  <ul class="list-disc list-inside text-sm">
                    <li>Rent</li>
                    <li>Water Bill</li>
                    <li>Electricity Bill</li>
                  </ul>
                </div>

                <?php if ($paymentSuccessMessage): ?>
                  <div class="mb-4 rounded-lg bg-emerald-50 text-emerald-700 px-4 py-3 border border-emerald-200" role="alert">
                    <?php echo htmlspecialchars($paymentSuccessMessage); ?>
                  </div>
                <?php endif; ?>
                <?php if ($paymentErrorMessage): ?>
                  <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-3 border border-red-200" role="alert">
                    <?php echo htmlspecialchars($paymentErrorMessage); ?>
                  </div>
                <?php endif; ?>

                <form action="" method="post" enctype="multipart/form-data" class="space-y-5">
                  <div class="space-y-3 text-sm">
                    <p class="flex items-center gap-2"><i class="fas fa-tint text-blue-500"></i> <strong>Water Bill:</strong> ₱<span id="waterBill"><?php echo $waterBill; ?></span></p>
                    <p class="flex items-center gap-2"><i class="fas fa-bolt text-yellow-500"></i> <strong>Electric Bill:</strong> ₱<span id="electricBill"><?php echo $electricBill; ?></span></p>
                    <p class="flex items-center gap-2"><i class="fas fa-wallet text-emerald-600"></i> <strong>Monthly Rent:</strong> ₱<span id="monthlyRate"><?php echo $monthlyRate; ?></span></p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rent Payment Type</label>
                    <select id="paymentType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                      <option value="full">Full Payment</option>
                      <option value="partial">Partial Payment (Installment - Pay in 3)</option>
                    </select>
                  </div>

                  <p class="text-lg font-semibold">Total Amount: ₱<span id="totalAmount">0.00</span></p>

                  <div>
                    <label for="payment_image" class="block text-sm font-medium text-gray-700 mb-1">Upload Proof of Payment</label>
                    <input type="file" name="payment_image" id="payment_image" required class="w-full px-3 py-2 border border-gray-300 rounded-md" />
                  </div>

                  <button type="submit" name="submit_payment" class="w-full md:w-auto px-5 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium">Upload Image</button>
                </form>

                <script>
                  document.addEventListener('DOMContentLoaded', function () {
                    const waterBill = parseFloat(document.getElementById('waterBill').textContent) || 0;
                    const electricBill = parseFloat(document.getElementById('electricBill').textContent) || 0;
                    const monthlyRate = parseFloat(document.getElementById('monthlyRate').textContent) || 0;
                    const paymentType = document.getElementById('paymentType');
                    const totalAmount = document.getElementById('totalAmount');
                    function computeTotal() {
                      let rent = paymentType.value === 'partial' ? monthlyRate / 3 : monthlyRate;
                      let total = rent + waterBill + electricBill;
                      totalAmount.textContent = total.toFixed(2);
                    }
                    paymentType.addEventListener('change', computeTotal);
                    computeTotal();
                  });
                </script>
              </div>
        <?php
              break;

            
            case 'space':
        ?>
              <?php if ($stallDetails): ?>
                <div class="bg-white rounded-xl shadow border border-gray-100 p-6 max-w-2xl mx-auto">
                  <h1 class="text-2xl font-semibold text-gray-900 mb-4">Area Details</h1>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                      <p class="text-gray-500">Stall Number</p>
                      <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($stallDetails['stall_number']); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-500">Floor Area</p>
                      <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($stallDetails['floor_area']); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-500">Monthly Rate</p>
                      <p class="text-gray-900 font-medium">₱<?php echo number_format($stallDetails['monthly_rate'], 2); ?></p>
                    </div>
                    <div>
                      <p class="text-gray-500">Status</p>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $stallDetails['status'] == 'available' ? 'bg-emerald-100 text-emerald-800' : ($stallDetails['status'] == 'reserved' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $stallDetails['status'])); ?>
                      </span>
                    </div>
                    <div class="md:col-span-2">
                      <p class="text-gray-500">Description</p>
                      <p class="text-gray-900"><?php echo htmlspecialchars($stallDetails['description']); ?></p>
                    </div>
                    <?php if (!empty($stallDetails['image_path'])): ?>
                      <div class="md:col-span-2">
                        <p class="text-gray-500">Stall Image</p>
                        <img src="<?php echo htmlspecialchars($stallDetails['image_path']); ?>" alt="Stall Image" class="mt-2 rounded-lg border border-gray-200 max-h-60 object-cover w-full">
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="mt-6">
                    <a href="tenant_payment.php" class="w-full inline-flex justify-center md:w-auto px-5 py-2.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 text-sm font-medium">
                      <i class="fas fa-money-bill-wave mr-2"></i> Make Payment
                    </a>
                  </div>
                </div>
              <?php elseif ($applicationStatus === 'approved'): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg max-w-md mx-auto">
                  Your application has been approved but no stall has been assigned yet. Please contact support.
                </div>
              <?php endif; ?>
        <?php
              break;

            case 'status':
        ?>
              <div class="p-6 bg-white rounded-xl shadow border border-gray-100 max-w-4xl mx-auto">
                <h1 class="text-2xl font-semibold text-gray-900 mb-4 border-b border-gray-100 pb-2">Your Account Status</h1>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                  <!-- Status Cards -->
                  <div class="lg:col-span-2 space-y-4">
                    <!-- Application Status -->
                    <div class="flex items-center justify-between p-4 rounded-lg border <?php 
                      echo $applicationStatus === 'approved' ? 'bg-emerald-50 border-emerald-200' :
                           ($applicationStatus === 'pending' ? 'bg-yellow-50 border-yellow-200' :
                           ($applicationStatus === 'declined' ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200'));
                    ?>">
                      <div class="flex items-center">
                        <div class="p-2 rounded-full mr-3 <?php 
                          echo $applicationStatus === 'approved' ? 'bg-emerald-100 text-emerald-600' :
                               ($applicationStatus === 'pending' ? 'bg-yellow-100 text-yellow-600' :
                               ($applicationStatus === 'declined' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'));
                        ?>">
                          <?php 
                            if ($applicationStatus === 'approved') echo '<i class="fas fa-check-circle text-xl"></i>';
                            elseif ($applicationStatus === 'pending') echo '<i class="fas fa-clock text-xl"></i>';
                            elseif ($applicationStatus === 'declined') echo '<i class="fas fa-times-circle text-xl"></i>';
                            else echo '<i class="fas fa-question-circle text-xl"></i>';
                          ?>
                        </div>
                        <div>
                          <h3 class="font-semibold text-gray-800">Application Status</h3>
                          <p class="text-sm text-gray-600">
                            <?php 
                              $statusText = [
                                'approved' => 'Your application has been approved',
                                'pending' => 'Your application is under review',
                                'declined' => 'Your application was declined',
                                'default' => 'Application not submitted'
                              ];
                              echo $statusText[$applicationStatus] ?? $statusText['default'];
                            ?>
                          </p>
                        </div>
                      </div>
                      <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                        echo $applicationStatus === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                             ($applicationStatus === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                             ($applicationStatus === 'declined' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                      ?>"><?php echo htmlspecialchars(ucfirst($applicationStatus)); ?></span>
                    </div>

                    <!-- Payment Status -->
                    <div class="flex items-center justify-between p-4 rounded-lg border bg-blue-50 border-blue-200">
                      <div class="flex items-center">
                        <div class="p-2 rounded-full mr-3 <?php 
                          if (strtolower($paymentStatus) === 'approved') echo 'bg-emerald-100 text-emerald-600';
                          elseif (strtolower($paymentStatus) === 'declined') echo 'bg-red-100 text-red-600';
                          else echo 'bg-yellow-100 text-yellow-600';
                        ?>">
                          <?php 
                            if (strtolower($paymentStatus) === 'approved') echo '<i class="fas fa-check-circle text-xl"></i>';
                            elseif (strtolower($paymentStatus) === 'declined') echo '<i class="fas fa-times-circle text-xl"></i>';
                            else echo '<i class="fas fa-clock text-xl"></i>';
                          ?>
                        </div>
                        <div>
                          <h3 class="font-semibold text-gray-800">Payment Status</h3>
                          <p class="text-sm text-gray-600">
                            <?php 
                              $statusText = [
                                'approved' => 'Your payment has been processed',
                                'pending' => 'Your payment is being reviewed',
                                'declined' => 'Your payment was declined',
                                'default' => 'No payment submitted'
                              ];
                              echo $statusText[strtolower($paymentStatus)] ?? $statusText['default'];
                            ?>
                          </p>
                        </div>
                      </div>
                      <span class="px-3 py-1 rounded-full text-sm font-medium <?php 
                        echo strtolower($paymentStatus) === 'approved' ? 'bg-emerald-100 text-emerald-800' :
                             (strtolower($paymentStatus) === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                             (strtolower($paymentStatus) === 'declined' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'));
                      ?>"><?php echo htmlspecialchars(ucfirst($paymentStatus)); ?></span>
                    </div>
                  </div>

                  <!-- Assigned Stall Info (Right Side) -->
                  <div class="lg:col-span-1">
                    <?php if ($applicationStatus === 'approved' && $stallDetails): ?>
                      <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-lg h-full">
                        <h3 class="font-semibold text-emerald-800 mb-3 flex items-center">
                          <i class="fas fa-store mr-2"></i>
                          Your Assigned Stall
                        </h3>
                        <div class="space-y-2 text-sm">
                          <div class="flex justify-between items-center">
                            <span class="text-emerald-700 font-medium">Stall Number:</span>
                            <span class="text-emerald-900 font-semibold"><?php echo htmlspecialchars($stallDetails['stall_number']); ?></span>
                          </div>
                          <div class="flex justify-between items-center">
                            <span class="text-emerald-700 font-medium">Status:</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php 
                              echo $stallDetails['status'] == 'available' ? 'bg-emerald-100 text-emerald-800' : 
                                   ($stallDetails['status'] == 'reserved' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($stallDetails['status'] == 'not_available' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')); 
                            ?>">
                              <?php echo ucfirst(str_replace('_', ' ', $stallDetails['status'])); ?>
                            </span>
                          </div>
                          <?php if (!empty($stallDetails['floor_area'])): ?>
                            <div class="flex justify-between items-center">
                              <span class="text-emerald-700 font-medium">Floor Area:</span>
                              <span class="text-emerald-900"><?php echo htmlspecialchars($stallDetails['floor_area']); ?></span>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($stallDetails['monthly_rate'])): ?>
                            <div class="flex justify-between items-center">
                              <span class="text-emerald-700 font-medium">Monthly Rate:</span>
                              <span class="text-emerald-900 font-semibold">₱<?php echo number_format($stallDetails['monthly_rate'], 2); ?></span>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php elseif ($applicationStatus === 'approved'): ?>
                      <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg h-full">
                        <h3 class="font-semibold text-yellow-800 mb-2 flex items-center">
                          <i class="fas fa-exclamation-triangle mr-2"></i>
                          Stall Assignment
                        </h3>
                        <p class="text-yellow-700 text-sm">Your application has been approved but no stall has been assigned yet. Please contact support.</p>
                      </div>
                    <?php elseif ($applicationStatus === 'declined'): ?>
                      <div class="p-4 bg-red-50 border border-red-200 rounded-lg h-full">
                        <h3 class="font-semibold text-red-800 mb-2 flex items-center">
                          <i class="fas fa-exclamation-circle mr-2"></i>
                          Application Declined
                        </h3>
                        <p class="text-red-700 text-sm">Please contact support for more information about your application status.</p>
                      </div>
                    <?php else: ?>
                      <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg h-full">
                        <h3 class="font-semibold text-gray-800 mb-2 flex items-center">
                          <i class="fas fa-info-circle mr-2"></i>
                          Stall Information
                        </h3>
                        <p class="text-gray-600 text-sm">Stall information will be available once your application is approved.</p>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
        <?php
              break;

            default:
            ?>
              <section class="bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-2xl text-white p-6 shadow">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                  <div>
                    <h1 class="text-2xl md:text-3xl font-bold">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h1>
                    <p class="text-emerald-100 mt-1">Manage your space, payments, and requests in one place.</p>
                  </div>
                  <div class="flex gap-3">
                    <?php if ($approvedStallsCount > 0): ?>
                    <a href="apply_additional_stall.php" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg font-medium shadow-lg">
                      <i class="fas fa-plus-circle mr-1"></i> Add Stall
                    </a>
                    <?php endif; ?>
                    <a href="tenant_payment.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg font-medium">Make Payment</a>
                    <a href="work_permit_form.php" class="px-4 py-2 bg-white text-emerald-700 hover:bg-emerald-50 rounded-lg font-medium">Work Permit</a>
                  </div>
                </div>
              </section>

              <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Application Status</p>
                    <i class="fas fa-file-signature text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold"><?php echo htmlspecialchars(ucfirst($applicationStatus)); ?></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Payment Status</p>
                    <i class="fas fa-receipt text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold"><?php echo htmlspecialchars(ucfirst($paymentStatus)); ?></p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Stall</p>
                    <i class="fas fa-store text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold">
                    <?php echo $stallDetails ? htmlspecialchars($stallDetails['stall_number']) : 'Unassigned'; ?>
                  </p>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-4 shadow">
                  <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500">Lease Start</p>
                    <i class="fas fa-calendar-alt text-emerald-500"></i>
                  </div>
                  <p class="mt-2 text-lg font-semibold"><?php echo htmlspecialchars($startDate); ?></p>
                </div>
              </section>

              <?php
              // Fetch all user's stalls for multi-stall display with payment status
              $stmtAllStalls = $pdo->prepare("
                  SELECT td.*, s.stall_number, s.description, s.floor_area, s.monthly_rate, s.image_path,
                         (SELECT COUNT(*) FROM payments p WHERE p.user_id = td.user_id AND p.stall_id = s.id AND p.status = 'approved') as has_payment
                  FROM tenant_details td
                  LEFT JOIN stalls s ON td.stall_id = s.id
                  WHERE td.user_id = ?
                  ORDER BY td.status DESC, td.created_at DESC
              ");
              $stmtAllStalls->execute([$userId]);
              $allMyStalls = $stmtAllStalls->fetchAll(PDO::FETCH_ASSOC);
              $approvedStallsCount = count(array_filter($allMyStalls, fn($s) => $s['status'] === 'approved'));
              ?>

              <!-- My Stalls Section -->
              <?php if (count($allMyStalls) > 0): ?>
              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <div class="flex items-center justify-between mb-4">
                  <div>
                    <h2 class="text-lg font-semibold text-gray-900">My Stalls</h2>
                    <p class="text-sm text-gray-500">You have <?php echo count($allMyStalls); ?> stall application(s)</p>
                  </div>
                  <?php if ($approvedStallsCount > 0): ?>
                  <a href="apply_additional_stall.php" class="px-4 py-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 text-white rounded-lg font-medium transition transform hover:scale-105 shadow">
                    <i class="fas fa-plus-circle mr-2"></i>Apply for Additional Stall
                  </a>
                  <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <?php foreach ($allMyStalls as $stallApp): ?>
                    <div class="border-2 <?php echo $stallApp['status'] === 'approved' ? 'border-green-500 bg-green-50' : ($stallApp['status'] === 'pending' ? 'border-yellow-500 bg-yellow-50' : 'border-red-500 bg-red-50'); ?> rounded-lg p-4 hover:shadow-lg transition">
                      <?php if ($stallApp['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($stallApp['image_path']); ?>" alt="Stall" class="w-full h-32 object-cover rounded-lg mb-3">
                      <?php else: ?>
                        <div class="w-full h-32 bg-gradient-to-br from-emerald-400 to-blue-500 rounded-lg mb-3 flex items-center justify-center">
                          <i class="fas fa-store text-white text-4xl"></i>
                        </div>
                      <?php endif; ?>
                      
                      <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-lg text-gray-800"><?php echo htmlspecialchars($stallApp['stall_number'] ?? 'N/A'); ?></span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $stallApp['status'] === 'approved' ? 'bg-green-200 text-green-800' : ($stallApp['status'] === 'pending' ? 'bg-yellow-200 text-yellow-800' : 'bg-red-200 text-red-800'); ?>">
                          <?php echo ucfirst($stallApp['status']); ?>
                        </span>
                      </div>
                      
                      <div class="space-y-1 text-sm text-gray-700">
                        <p><i class="fas fa-info-circle text-emerald-600 w-4"></i> <?php echo htmlspecialchars($stallApp['description'] ?? 'N/A'); ?></p>
                        <p><i class="fas fa-ruler-combined text-emerald-600 w-4"></i> <?php echo htmlspecialchars($stallApp['floor_area'] ?? 'N/A'); ?> sq.m</p>
                        <p class="font-bold text-emerald-700 mt-2">₱<?php echo number_format($stallApp['monthly_rate'] ?? 0, 2); ?>/month</p>
                      </div>
                      
                      <?php if ($stallApp['status'] === 'declined' && $stallApp['admin_feedback']): ?>
                        <div class="mt-3 p-2 bg-red-100 rounded text-xs text-red-800">
                          <strong>Feedback:</strong> <?php echo htmlspecialchars($stallApp['admin_feedback']); ?>
                        </div>
                      <?php endif; ?>
                      
                      <?php if ($stallApp['status'] === 'approved'): ?>
                        <?php if ($stallApp['has_payment'] > 0): ?>
                          <div class="mt-3 block w-full px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-500 text-white text-center rounded-lg font-medium shadow text-sm">
                            <i class="fas fa-check-double mr-2"></i>Payment Completed
                          </div>
                        <?php else: ?>
                          <a href="tenant_payment.php" class="mt-3 block w-full px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white text-center rounded-lg font-medium transition transform hover:scale-105 shadow text-sm">
                            <i class="fas fa-check-circle mr-2"></i>Complete Transaction - Proceed to Payment
                          </a>
                        <?php endif; ?>
                      <?php elseif ($stallApp['status'] === 'declined'): ?>
                        <a href="resubmit_application.php?id=<?php echo $stallApp['id']; ?>" class="mt-3 block w-full px-4 py-2 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white text-center rounded-lg font-medium transition transform hover:scale-105 shadow text-sm">
                          <i class="fas fa-redo mr-2"></i>Resubmit Application
                        </a>
                      <?php endif; ?>
                      
                      <div class="mt-3 text-xs text-gray-500">
                        Applied: <?php echo date('M d, Y', strtotime($stallApp['created_at'])); ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </section>
              <?php endif; ?>

              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Quick Actions</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                  <a href="tenant_dashboard.php?page=space" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-map-marked-alt text-emerald-600"></i><span>Area</span>
                  </a>
                  <a href="work_permit_form.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-hard-hat text-emerald-600"></i><span>Work Permit</span>
                  </a>
                  <a href="tenant_dashboard.php?page=renewal" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-sync-alt text-emerald-600"></i><span>Renewal</span>
                  </a>
                  <a href="tenant_payment.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border hover:bg-gray-50">
                    <i class="fas fa-credit-card text-emerald-600"></i><span>Payment</span>
                  </a>
                  <?php if ($approvedStallsCount > 0): ?>
                  <a href="apply_additional_stall.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-lg border-2 border-purple-600 bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium">
                    <i class="fas fa-plus-circle text-purple-600"></i><span>Add Stall</span>
                  </a>
                  <?php endif; ?>
                </div>
              </section>

              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-lg font-semibold text-gray-900">Contracts</h2>
                  <?php if (count($tenantContracts) > 0): ?>
                    <span class="text-xs px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-semibold">
                      <?php echo count($tenantContracts); ?> record(s)
                    </span>
                  <?php endif; ?>
                </div>
                <?php if (count($tenantContracts) === 0): ?>
                  <p class="text-sm text-gray-500">No contracts generated yet. Once the admin prepares a contract, it will appear here for download.</p>
                <?php else: ?>
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="bg-gray-100 text-gray-600 uppercase text-xs tracking-widest">
                          <th class="px-4 py-3 text-left">Version</th>
                          <th class="px-4 py-3 text-left">Status</th>
                          <th class="px-4 py-3 text-left">Generated</th>
                          <th class="px-4 py-3 text-left">Actions</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        <?php foreach ($tenantContracts as $contractRow): ?>
                          <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-semibold text-gray-800">#<?php echo (int)$contractRow['version']; ?></td>
                            <td class="px-4 py-3">
                              <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold <?php echo $contractRow['contract_status'] === 'final' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                <i class="fas fa-file-contract"></i>
                                <?php echo strtoupper($contractRow['contract_status']); ?>
                              </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                              <?php echo date('M d, Y g:i A', strtotime($contractRow['created_at'])); ?>
                            </td>
                            <td class="px-4 py-3">
                              <div class="flex items-center gap-3">
                                <a href="view_contract.php?id=<?php echo $contractRow['id']; ?>" class="text-emerald-600 hover:text-emerald-700 font-medium flex items-center gap-1">
                                  <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (!empty($contractRow['contract_path']) && file_exists(__DIR__ . '/' . $contractRow['contract_path'])): ?>
                                  <a href="<?php echo htmlspecialchars($contractRow['contract_path']); ?>" download class="text-gray-500 hover:text-gray-700 text-sm flex items-center gap-1">
                                    <i class="fas fa-download"></i> Download
                                  </a>
                                <?php endif; ?>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </section>

              <section class="bg-white rounded-xl border border-gray-100 p-6 shadow">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-lg font-semibold text-gray-900">Recent Notifications</h2>
                  <a href="#" onclick="showAllNotifications(); return false;" class="text-sm text-emerald-700 hover:text-emerald-800">View all</a>
                </div>
                <?php
                  $recent = array_slice($notifications ?? [], 0, 5);
                  if (count($recent) === 0):
                ?>
                  <p class="text-sm text-gray-500">No recent notifications.</p>
                <?php else: ?>
                  <ul class="divide-y divide-gray-100">
                    <?php foreach ($recent as $n): ?>
                      <li class="py-3 flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                          <i class="fas fa-bell text-xs"></i>
                        </div>
                        <div class="flex-1">
                          <p class="text-sm text-gray-800"><?php echo htmlspecialchars($n['category'] ?? 'Notification'); ?></p>
                          <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($n['message']); ?></p>
                          <p class="text-xs text-gray-400 mt-1"><?php echo date('M d, Y h:i A', strtotime($n['created_at'] ?? 'now')); ?></p>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </section>
            <?php
              break;
          }
        ?>

        
      </div>
      </main>
    </div>
  </div>
<script>
  (function() {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggle || !overlay) return;

    function openSidebar() {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = 'auto';
    }
    toggle.addEventListener('click', function() {
      const isOpen = !sidebar.classList.contains('-translate-x-full');
      if (isOpen) closeSidebar(); else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeSidebar();
    });

    function handleResize() {
      if (window.innerWidth >= 1024) { // lg breakpoint
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = 'auto';
      } else {
        if (!overlay.classList.contains('hidden')) return; // keep state if open
        sidebar.classList.add('-translate-x-full');
      }
    }
    window.addEventListener('resize', handleResize);
    handleResize();
  })();
</script>
</body>
</html>
