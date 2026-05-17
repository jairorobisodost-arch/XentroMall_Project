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

// Check if user has at least one approved application
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM tenant_details WHERE user_id = ? AND status = 'approved'");
$stmtCheck->execute([$userId]);
$hasApprovedStall = $stmtCheck->fetchColumn() > 0;

if (!$hasApprovedStall) {
    $_SESSION['error_message'] = "You need to have at least one approved stall before applying for additional stalls.";
    header('Location: tenant_dashboard.php');
    exit;
}

// Fetch user's existing stalls
$stmtMyStalls = $pdo->prepare("
    SELECT td.*, s.stall_number, s.description, s.floor_area, s.monthly_rate, s.image_path
    FROM tenant_details td
    LEFT JOIN stalls s ON td.stall_id = s.id
    WHERE td.user_id = ?
    ORDER BY td.created_at DESC
");
$stmtMyStalls->execute([$userId]);
$myStalls = $stmtMyStalls->fetchAll(PDO::FETCH_ASSOC);

// Get stall IDs that user already has or applied for
$myStallIds = array_filter(array_column($myStalls, 'stall_id'));

// Fetch available stalls (not occupied and not in user's applications)
$placeholders = str_repeat('?,', count($myStallIds) - 1) . '?';
$query = "SELECT * FROM stalls WHERE status = 'available'";
if (!empty($myStallIds)) {
    $query .= " AND id NOT IN ($placeholders)";
}
$query .= " ORDER BY stall_number";

$stmtAvailable = $pdo->prepare($query);
if (!empty($myStallIds)) {
    $stmtAvailable->execute($myStallIds);
} else {
    $stmtAvailable->execute();
}
$availableStalls = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_stall'])) {
    $selectedStallId = $_POST['stall_id'] ?? '';
    
    if ($selectedStallId) {
        try {
            // Check if user already applied for this stall
            $stmtCheckDup = $pdo->prepare("SELECT COUNT(*) FROM tenant_details WHERE user_id = ? AND stall_id = ?");
            $stmtCheckDup->execute([$userId, $selectedStallId]);
            
            if ($stmtCheckDup->fetchColumn() > 0) {
                $_SESSION['error_message'] = "You already have an application for this stall!";
            } else {
                // Get user's first approved application data
                $stmtFirstApp = $pdo->prepare("SELECT * FROM tenant_details WHERE user_id = ? AND status = 'approved' LIMIT 1");
                $stmtFirstApp->execute([$userId]);
                $firstApp = $stmtFirstApp->fetch(PDO::FETCH_ASSOC);
                
                if ($firstApp) {
                    // Create new application with same tenant details but different stall
                    $stmt = $pdo->prepare("
                        INSERT INTO tenant_details (
                            user_id, tradename, store_premises, store_location, ownership,
                            company_name, business_address, tin, office_tel, tenant_representative,
                            contact_person, position, contact_tel, mobile, email, prepared_by,
                            business_type, documents, status, stall_id, first_name, last_name
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $userId,
                        $firstApp['tradename'],
                        $firstApp['store_premises'],
                        $firstApp['store_location'],
                        $firstApp['ownership'],
                        $firstApp['company_name'],
                        $firstApp['business_address'],
                        $firstApp['tin'],
                        $firstApp['office_tel'],
                        $firstApp['tenant_representative'],
                        $firstApp['contact_person'],
                        $firstApp['position'],
                        $firstApp['contact_tel'],
                        $firstApp['mobile'],
                        $firstApp['email'],
                        $firstApp['prepared_by'],
                        $firstApp['business_type'],
                        $firstApp['documents'],
                        $selectedStallId,
                        $firstApp['first_name'],
                        $firstApp['last_name']
                    ]);
                    
                    $_SESSION['success_message'] = "✅ Additional stall application submitted! Waiting for admin approval.";
                    
                    // Send email notification to admin
                    $mail = new PHPMailer(true);
                    try {
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
                        
                        $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
                        $mail->addAddress('mallxentro5@gmail.com', 'XentroMall Admin');
                        
                        // Get stall details
                        $stmtStall = $pdo->prepare("SELECT * FROM stalls WHERE id = ?");
                        $stmtStall->execute([$selectedStallId]);
                        $stallInfo = $stmtStall->fetch(PDO::FETCH_ASSOC);
                        
                        $existingCount = count($myStalls);
                        
                        $mail->isHTML(true);
                        $mail->Subject = "🏢 Additional Stall Application - " . $firstApp['tradename'];
                        $mail->Body = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                                .info-box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #667eea; }
                                .badge { background: #667eea; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>🏢 Additional Stall Application</h1>
                                    <p>XentroMall Management System</p>
                                </div>
                                <div class='content'>
                                    <p><strong>An existing tenant is applying for an additional stall!</strong></p>
                                    
                                    <div class='info-box'>
                                        <h3>👤 Tenant Information</h3>
                                        <p><strong>Trade Name:</strong> {$firstApp['tradename']}</p>
                                        <p><strong>Company:</strong> {$firstApp['company_name']}</p>
                                        <p><strong>Contact:</strong> {$firstApp['mobile']}</p>
                                        <p><strong>Email:</strong> {$firstApp['email']}</p>
                                    </div>
                                    
                                    <div class='info-box'>
                                        <h3>🏪 Requested Stall</h3>
                                        <p><strong>Stall Number:</strong> {$stallInfo['stall_number']}</p>
                                        <p><strong>Description:</strong> {$stallInfo['description']}</p>
                                        <p><strong>Floor Area:</strong> {$stallInfo['floor_area']} sq.m</p>
                                        <p><strong>Monthly Rate:</strong> ₱" . number_format($stallInfo['monthly_rate'], 2) . "</p>
                                    </div>
                                    
                                    <div class='info-box'>
                                        <h3>📊 Current Status</h3>
                                        <p><strong>Existing Stalls:</strong> <span class='badge'>$existingCount stall(s)</span></p>
                                        <p><strong>Application Type:</strong> <span class='badge'>Additional Stall</span></p>
                                    </div>
                                    
                                    <p style='margin-top: 20px;'>Please review this application in the admin dashboard.</p>
                                </div>
                            </div>
                        </body>
                        </html>";
                        
                        $mail->send();
                    } catch (Exception $e) {
                        error_log("Email notification failed: " . $e->getMessage());
                    }
                    
                    header("Location: tenant_dashboard.php");
                    exit;
                } else {
                    $_SESSION['error_message'] = "No approved application found.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Additional Stall - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
        .glass-effect { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95); }
        .stall-card { transition: all 0.3s ease; }
        .stall-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="glass-effect rounded-2xl p-6 mb-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-store-alt text-green-600"></i> Apply for Additional Stall
                    </h1>
                    <p class="text-gray-600 mt-2">Expand your business with another stall location</p>
                </div>
                <a href="tenant_dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg glass-effect">
                <p class="font-bold"><i class="fas fa-exclamation-circle mr-2"></i> Error</p>
                <p><?= htmlspecialchars($_SESSION['error_message']) ?></p>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- My Current Stalls -->
        <div class="glass-effect rounded-2xl p-6 mb-6 shadow-xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-building text-blue-600"></i> My Current Stalls (<?= count($myStalls) ?>)
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($myStalls as $stall): ?>
                    <div class="bg-white rounded-lg p-4 border-2 <?= $stall['status'] === 'approved' ? 'border-green-500' : 'border-yellow-500' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-lg"><?= htmlspecialchars($stall['stall_number'] ?? 'N/A') ?></span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?= $stall['status'] === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                <?= ucfirst($stall['status']) ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600"><i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars($stall['description'] ?? 'N/A') ?></p>
                        <p class="text-sm text-gray-600"><i class="fas fa-ruler-combined mr-1"></i> <?= htmlspecialchars($stall['floor_area'] ?? 'N/A') ?> sq.m</p>
                        <p class="text-sm font-bold text-green-600 mt-2">₱<?= number_format($stall['monthly_rate'] ?? 0, 2) ?>/month</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Available Stalls -->
        <div class="glass-effect rounded-2xl p-6 shadow-xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-plus-circle text-green-600"></i> Available Stalls for Application (<?= count($availableStalls) ?>)
            </h2>
            
            <?php if (empty($availableStalls)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No available stalls at the moment</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($availableStalls as $stall): ?>
                        <div class="stall-card bg-white rounded-xl overflow-hidden shadow-lg">
                            <?php if ($stall['image_path']): ?>
                                <img src="<?= htmlspecialchars($stall['image_path']) ?>" alt="Stall" class="w-full h-48 object-cover">
                            <?php else: ?>
                                <div class="w-full h-48 bg-gradient-to-br from-green-400 to-blue-500 flex items-center justify-center">
                                    <i class="fas fa-store text-white text-6xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($stall['stall_number']) ?></h3>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold">Available</span>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-info-circle text-green-600 w-5"></i>
                                        <?= htmlspecialchars($stall['description']) ?>
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-ruler-combined text-green-600 w-5"></i>
                                        <?= htmlspecialchars($stall['floor_area']) ?> sq.m
                                    </p>
                                    <p class="text-lg font-bold text-green-600 mt-3">
                                        ₱<?= number_format($stall['monthly_rate'], 2) ?>/month
                                    </p>
                                </div>
                                
                                <button onclick="openApplicationModal(<?= $stall['id'] ?>, '<?= addslashes(htmlspecialchars($stall['stall_number'])) ?>')"
                                        class="w-full bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white font-bold py-3 px-4 rounded-lg transition transform hover:scale-105">
                                    <i class="fas fa-paper-plane mr-2"></i> Apply for This Stall
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Application Type Modal -->
    <div id="applicationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
        <div class="glass-effect rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">
                <i class="fas fa-clipboard-list text-green-600"></i> Choose Application Type
            </h3>
            <p class="text-gray-600 mb-6">Select how you want to apply for <strong id="modalStallNumber"></strong>:</p>
            
            <div class="space-y-4">
                <!-- Existing Business Option -->
                <button onclick="applySameBusiness()" class="w-full bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-bold py-4 px-6 rounded-lg transition transform hover:scale-105 shadow-lg">
    <i class="fas fa-copy mr-2"></i> Apply Previous Business
    <p class="text-sm text-green-100 mt-1">Use existing business details (one-click)</p>
</button>
                
                <!-- New Business Option -->
                <button onclick="applyNewBusiness()" class="w-full bg-gradient-to-r from-blue-500 to-green-500 hover:from-blue-600 hover:to-green-600 text-white font-bold py-4 px-6 rounded-lg transition transform hover:scale-105 shadow-lg">
                    <i class="fas fa-file-alt mr-2"></i> New/Different Business
                    <p class="text-sm text-blue-100 mt-1">Fill up new business details & upload documents</p>
                </button>
            </div>
            
            <button onclick="closeApplicationModal()" class="w-full mt-4 bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg transition">
                <i class="fas fa-times mr-2"></i> Cancel
            </button>
        </div>
    </div>

    <script>
        let selectedStallId = null;
        let selectedStallNumber = '';

        function openApplicationModal(stallId, stallNumber) {
            selectedStallId = stallId;
            selectedStallNumber = stallNumber;
            document.getElementById('modalStallNumber').textContent = stallNumber;
            document.getElementById('applicationModal').style.display = 'flex';
        }

        function closeApplicationModal() {
            document.getElementById('applicationModal').style.display = 'none';
            selectedStallId = null;
            selectedStallNumber = '';
        }

        function applySameBusiness() {
            if (confirm('Apply for Stall ' + selectedStallNumber + ' using your existing business details?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="stall_id" value="${selectedStallId}">
                    <input type="hidden" name="apply_stall" value="1">
                    <input type="hidden" name="application_type" value="same_business">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function applyNewBusiness() {
            // Redirect to new business application form
            window.location.href = 'apply_new_business_stall.php?stall_id=' + selectedStallId;
        }

        // Close modal when clicking outside
        document.getElementById('applicationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApplicationModal();
            }
        });
    </script>
</body>
</html>
