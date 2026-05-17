<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch tenant basic info
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "Tenant not found.";
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch ALL stalls/applications of this tenant
$stmtStalls = $pdo->prepare("
    SELECT 
        td.id, td.tradename, td.company_name, td.business_type, td.ownership,
        td.tin, td.business_address, td.contact_person, td.mobile, td.email,
        td.status, td.created_at, td.admin_feedback, td.documents,
        s.id as stall_id, s.stall_number, s.description, s.floor_area, s.monthly_rate, s.image_path
    FROM tenant_details td
    LEFT JOIN stalls s ON s.id = td.stall_id
    WHERE td.user_id = ?
    ORDER BY td.status DESC, td.created_at DESC
");
$stmtStalls->execute([$userId]);
$allStalls = $stmtStalls->fetchAll(PDO::FETCH_ASSOC);

// Count stalls by status
$approvedCount = count(array_filter($allStalls, fn($s) => $s['status'] === 'approved'));
$pendingCount = count(array_filter($allStalls, fn($s) => $s['status'] === 'pending'));
$declinedCount = count(array_filter($allStalls, fn($s) => $s['status'] === 'declined'));

// Fetch payment history
$stmtPayments = $pdo->prepare("
    SELECT * FROM payments 
    WHERE user_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 10
");
$stmtPayments->execute([$userId]);
$payments = $stmtPayments->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Profile - XentroMall Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-primary { background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
        .gradient-card { background: linear-gradient(135deg, #f0fdf4 0%, #eff6ff 100%); }
        .glass-effect { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95); }
        .stall-card { transition: all 0.3s ease; }
        .stall-card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex gap-6 max-w-[1400px] mx-auto px-4 lg:px-6 py-6">
        <!-- Sidebar -->
        <aside class="gradient-primary rounded-2xl w-72 p-4 flex flex-col gap-3 shadow-2xl text-white relative overflow-hidden">
            <!-- Background decoration -->
            <div class="absolute -top-20 -right-20 w-40 h-40 bg-white/10 rounded-full"></div>
            <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-white/5 rounded-full"></div>
            
            <!-- Logo and Brand -->
            <div class="flex items-center gap-4">
                <div class="bg-white/20 p-2 rounded-2xl backdrop-blur-sm">
                    <img src="img/logo.jpg" alt="XentroMall Logo" class="w-12 h-12 object-contain rounded-lg" />
                </div>
                <div>
                    <h1 class="font-bold text-2xl tracking-tight text-white">XentroMall</h1>
                    <p class="text-white/80 text-sm">Admin Portal</p>
                </div>
            </div>
            
            <!-- Navigation Links -->
            <nav class="flex flex-col gap-2 relative z-10">
                <a href="admin_dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group">
                    <i class="fas fa-home text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Dashboard</span>
                </a>
                <a href="admin_dashboard.php#tenants" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group">
                    <i class="fas fa-users text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Tenants</span>
                </a>
                <a href="admin_settings.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group">
                    <i class="fas fa-cog text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Settings</span>
                </a>
                
                <div class="h-px bg-white/30 my-2"></div>
                
                <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white/15 transition-all cursor-pointer group">
                    <i class="fas fa-sign-out-alt text-xl group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold">Logout</span>
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="flex-1">
            <!-- Header -->
            <div class="gradient-card rounded-2xl p-6 mb-6 shadow-xl border border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-blue-500 rounded-2xl flex items-center justify-center text-white font-bold text-2xl shadow-lg">
                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-800">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </h1>
                            <p class="text-gray-600 mt-1">
                                <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($user['email']); ?>
                            </p>
                        </div>
                    </div>
                    <a href="admin_dashboard.php" class="gradient-card px-6 py-3 rounded-xl hover:scale-105 transition-all font-medium shadow-md border border-gray-200 group">
                        <i class="fas fa-arrow-left mr-2 group-hover:scale-110 transition-transform"></i>
                        <span class="bg-gradient-to-r from-emerald-600 to-blue-600 bg-clip-text text-transparent font-semibold">Back to Dashboard</span>
                    </a>
                </div>
            </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="gradient-card rounded-2xl p-6 shadow-lg border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Total Applications</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo count($allStalls); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="gradient-card rounded-2xl p-6 shadow-lg border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Approved Stalls</p>
                        <p class="text-3xl font-bold text-emerald-600"><?php echo $approvedCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="gradient-card rounded-2xl p-6 shadow-lg border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $pendingCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="gradient-card rounded-2xl p-6 shadow-lg border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">Declined</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $declinedCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Stalls Section -->
        <div class="gradient-card rounded-2xl p-6 mb-6 shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-store-alt text-emerald-600 mr-2"></i> All Stalls & Applications
            </h2>

            <?php if (empty($allStalls)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No applications found for this tenant</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($allStalls as $stall): ?>
                        <div class="stall-card bg-white rounded-xl overflow-hidden shadow-lg border-2 <?php 
                            echo $stall['status'] === 'approved' ? 'border-emerald-500' : 
                                ($stall['status'] === 'pending' ? 'border-yellow-500' : 'border-red-500'); 
                        ?>">
                            <!-- Stall Image -->
                            <?php if ($stall['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($stall['image_path']); ?>" alt="Stall" class="w-full h-40 object-cover">
                            <?php else: ?>
                                <div class="w-full h-40 bg-gradient-to-br from-emerald-400 to-blue-500 flex items-center justify-center">
                                    <i class="fas fa-store text-white text-5xl"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Stall Details -->
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-xl font-bold text-gray-800">
                                        <?php echo htmlspecialchars($stall['stall_number'] ?? 'N/A'); ?>
                                    </h3>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php 
                                        echo $stall['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 
                                            ($stall['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                                    ?>">
                                        <?php echo ucfirst($stall['status']); ?>
                                    </span>
                                </div>

                                <div class="space-y-2 text-sm text-gray-700 mb-4">
                                    <p><i class="fas fa-store text-emerald-600 w-5"></i> <strong><?php echo htmlspecialchars($stall['tradename']); ?></strong></p>
                                    <p><i class="fas fa-building text-emerald-600 w-5"></i> <?php echo htmlspecialchars($stall['company_name']); ?></p>
                                    <p><i class="fas fa-tag text-emerald-600 w-5"></i> <?php echo htmlspecialchars($stall['business_type']); ?></p>
                                    <p><i class="fas fa-info-circle text-emerald-600 w-5"></i> <?php echo htmlspecialchars($stall['description'] ?? 'N/A'); ?></p>
                                    <p><i class="fas fa-ruler-combined text-emerald-600 w-5"></i> <?php echo htmlspecialchars($stall['floor_area'] ?? 'N/A'); ?> sq.m</p>
                                    <p class="font-bold text-emerald-700 text-lg mt-2">
                                        ₱<?php echo number_format($stall['monthly_rate'] ?? 0, 2); ?>/month
                                    </p>
                                </div>

                                <?php if ($stall['status'] === 'declined' && $stall['admin_feedback']): ?>
                                    <div class="mb-3 p-3 bg-red-50 rounded-lg border border-red-200">
                                        <p class="text-xs font-bold text-red-800 mb-1">Admin Feedback:</p>
                                        <p class="text-xs text-red-700"><?php echo htmlspecialchars($stall['admin_feedback']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="text-xs text-gray-500 mb-3">
                                    <i class="fas fa-calendar mr-1"></i>
                                    Applied: <?php echo date('M d, Y', strtotime($stall['created_at'])); ?>
                                </div>

                                <a href="view_documents.php?id=<?php echo $stall['id']; ?>" target="_blank" class="block w-full text-center gradient-card px-4 py-2 rounded-xl hover:scale-105 transition-all font-medium shadow-md border border-gray-200 group">
                                    <i class="fas fa-folder-open mr-2 group-hover:scale-110 transition-transform"></i>
                                    <span class="bg-gradient-to-r from-emerald-600 to-blue-600 bg-clip-text text-transparent font-semibold">View Documents</span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Payment History -->
        <div class="gradient-card rounded-2xl p-6 shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-history text-emerald-600 mr-2"></i> Payment History
            </h2>

            <?php if (empty($payments)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-receipt text-gray-300 text-4xl mb-3"></i>
                    <p class="text-gray-500">No payment history</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($payments as $payment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                    <td class="px-4 py-3 text-sm font-bold">₱<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-3 py-1 rounded-full text-xs font-bold <?php 
                                            echo $payment['status'] === 'approved' ? 'bg-emerald-100 text-emerald-800' : 
                                                ($payment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); 
                                        ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
