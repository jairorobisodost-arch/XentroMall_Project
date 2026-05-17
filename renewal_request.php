<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$success = false;

// Get tenant's current stall and contract info
try {
    $stmt = $pdo->prepare("
        SELECT td.*, s.stall_number, s.monthly_rate, s.description, tc.contract_status, tc.created_at as contract_start
        FROM tenant_details td
        LEFT JOIN stalls s ON td.stall_id = s.id
        LEFT JOIN tenant_contracts tc ON td.id = tc.tenant_detail_id
        WHERE td.user_id = ? AND td.status = 'approved'
        ORDER BY tc.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $tenantInfo = $stmt->fetch();
    
    if (!$tenantInfo) {
        $_SESSION['error'] = 'No active contract found. Please contact admin.';
        header('Location: tenant_dashboard.php');
        exit;
    }
    
    // Calculate expiry date (1 year from contract start)
    $contractStart = new DateTime($tenantInfo['contract_start']);
    $expiryDate = clone $contractStart;
    $expiryDate->add(new DateInterval('P1Y'));
    $today = new DateTime();
    $daysRemaining = $today->diff($expiryDate)->days;
    
    // Check if renewal is already requested
    $stmtCheck = $pdo->prepare("
        SELECT * FROM renewal_requests 
        WHERE user_id = ? AND status IN ('pending', 'approved') 
        ORDER BY submitted_at DESC 
        LIMIT 1
    ");
    $stmtCheck->execute([$userId]);
    $existingRenewal = $stmtCheck->fetch();
    
} catch (Exception $e) {
    $message = 'Error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal'])) {
    $requestedYears = (int)($_POST['requested_years'] ?? 1);
    $stallId = $tenantInfo['stall_id'];
    $monthlyRate = (float)$tenantInfo['monthly_rate'];
    $totalAmount = $monthlyRate * 12 * $requestedYears; // Annual rate × years
    
    try {
        $pdo->beginTransaction();
        
        // Get tenant_id from tenants table
        $stmtTenant = $pdo->prepare("SELECT id FROM tenants WHERE user_id = ?");
        $stmtTenant->execute([$userId]);
        $tenantId = $stmtTenant->fetchColumn();
        
        // Insert renewal request
        $stmt = $pdo->prepare("
            INSERT INTO renewal_requests 
            (user_id, stall_id, tenant_id, requested_years, total_amount, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$userId, $stallId, $tenantId, $requestedYears, $totalAmount]);
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Renewal request submitted successfully! Admin will review your request.';
        header('Location: tenant_dashboard.php?page=renewal');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error submitting renewal: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Renewal Request - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); min-height: 100vh; }
        .hero-bg { position: fixed; inset: 0; z-index: -10; overflow: hidden; }
        .hero-bg::before { content: ""; position: absolute; inset: 0; background: url('img/bg.jpg') center/cover no-repeat; filter: brightness(0.55) saturate(1.1); transform: scale(1.02); }
        .hero-bg::after { content: ""; position: absolute; inset: 0; background: radial-gradient(1200px 600px at 10% -10%, rgba(56,189,248,.25), transparent 60%), radial-gradient(900px 500px at 90% 110%, rgba(34,197,94,.20), transparent 60%), linear-gradient(180deg, rgba(2,6,23,.65), rgba(2,6,23,.65)); mix-blend-mode: screen; }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <div class="hero-bg pointer-events-none" aria-hidden="true"></div>

    <main class="relative z-10 min-h-screen flex items-center justify-center p-6">
        <div class="w-full max-w-2xl">
            <!-- Card -->
            <div class="relative rounded-2xl bg-white/80 backdrop-blur-xl shadow-2xl ring-1 ring-black/5">
                <div class="absolute -inset-px rounded-2xl pointer-events-none" style="background: radial-gradient(800px 400px at 0% 0%, rgba(59,130,246,.08), transparent 50%), radial-gradient(700px 400px at 100% 100%, rgba(16,185,129,.08), transparent 50%);"></div>

                <div class="relative p-8 sm:p-10">
                    <!-- Header -->
                    <div class="text-center mb-6">
                        <div class="h-12 w-12 rounded-xl overflow-hidden ring-1 ring-black/10 shadow-sm bg-white/60 flex items-center justify-center mx-auto mb-4">
                            <img src="img/logo.jpg" alt="XentroMall" class="h-10 w-10 object-cover" />
                        </div>
                        <h1 class="text-2xl font-semibold text-slate-900 mb-2">Contract Renewal Request</h1>
                        <p class="text-sm text-slate-600">Submit your renewal request for contract extension</p>
                    </div>

                    <!-- Message -->
                    <?php if (!empty($message)): ?>
                        <div class="mb-5 rounded-lg border <?php echo $success ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'; ?> px-4 py-3 text-sm">
                            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($existingRenewal): ?>
                        <div class="mb-5 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            You already have a <?php echo htmlspecialchars($existingRenewal['status']); ?> renewal request. 
                            <?php if ($existingRenewal['status'] === 'pending'): ?>
                                Please wait for admin approval.
                            <?php elseif ($existingRenewal['status'] === 'approved'): ?>
                                Your renewal has been approved. Please submit payment.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Current Contract Info -->
                        <div class="mb-6 bg-gray-50 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 mb-3">Current Contract Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Stall Number</p>
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($tenantInfo['stall_number']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Monthly Rate</p>
                                    <p class="font-semibold text-gray-900">₱<?php echo number_format($tenantInfo['monthly_rate'], 2); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Contract Start</p>
                                    <p class="font-semibold text-gray-900"><?php echo date('F j, Y', strtotime($tenantInfo['contract_start'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Contract Expiry</p>
                                    <p class="font-semibold text-gray-900"><?php echo date('F j, Y', $expiryDate->getTimestamp()); ?></p>
                                </div>
                            </div>
                            <div class="mt-3 text-sm <?php echo $daysRemaining <= 90 ? 'text-yellow-600' : 'text-gray-600'; ?>">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo $daysRemaining; ?> days remaining until expiry
                            </div>
                        </div>

                        <!-- Renewal Form -->
                        <form action="renewal_request.php" method="post" class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Renewal Period</label>
                                <select name="requested_years" required class="w-full rounded-lg border border-slate-300 bg-white/90 px-4 py-3 text-slate-900 placeholder-slate-400 shadow-sm outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/20 transition">
                                    <option value="1">1 Year - ₱<?php echo number_format($tenantInfo['monthly_rate'] * 12, 2); ?></option>
                                    <option value="2">2 Years - ₱<?php echo number_format($tenantInfo['monthly_rate'] * 24, 2); ?></option>
                                    <option value="3">3 Years - ₱<?php echo number_format($tenantInfo['monthly_rate'] * 36, 2); ?></option>
                                </select>
                            </div>

                            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                                <h4 class="font-semibold text-emerald-900 mb-2">Renewal Summary</h4>
                                <div class="space-y-1 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-emerald-700">Annual Rate:</span>
                                        <span class="font-semibold text-emerald-900">₱<?php echo number_format($tenantInfo['monthly_rate'] * 12, 2); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-emerald-700">Selected Period:</span>
                                        <span class="font-semibold text-emerald-900" id="selectedPeriod">1 Year</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-emerald-300">
                                        <span class="text-emerald-700 font-semibold">Total Amount:</span>
                                        <span class="font-bold text-emerald-900 text-lg" id="totalAmount">₱<?php echo number_format($tenantInfo['monthly_rate'] * 12, 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="submit_renewal" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-600 to-emerald-500 px-4 py-3 text-white font-semibold shadow-lg hover:from-blue-700 hover:to-emerald-600 transition">
                                <i class="fas fa-file-contract"></i>
                                Submit Renewal Request
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Links -->
                    <div class="mt-6 text-center space-y-2">
                        <div class="text-sm text-slate-600">
                            <a href="tenant_dashboard.php" class="font-medium text-blue-600 hover:text-blue-700">← Back to Dashboard</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="mt-6 text-center text-xs text-slate-400">
                © <?php echo date('Y'); ?> XentroMall • All rights reserved
            </p>
        </div>
    </main>

    <script>
        // Update renewal summary when period changes
        document.querySelector('select[name="requested_years"]').addEventListener('change', function() {
            const years = parseInt(this.value);
            const monthlyRate = <?php echo $tenantInfo['monthly_rate']; ?>;
            const annualRate = monthlyRate * 12;
            const total = annualRate * years;
            
            document.getElementById('selectedPeriod').textContent = years + (years === 1 ? ' Year' : ' Years');
            document.getElementById('totalAmount').textContent = '₱' + total.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        });
    </script>
</body>
</html>
        <h1>Lease Renewal Request</h1>
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form action="" method="post">
            <!-- Display tenant_id automatically -->
            <input type="hidden" id="tenant_id" name="tenant_id" value="<?php echo htmlspecialchars($tenant_id); ?>" readonly />
            <label for="renewal_date">Desired Renewal Date:</label>
            <input type="date" id="renewal_date" name="renewal_date" required />
            <button type="submit">Submit Request</button>
        </form>
    </div>
</body>
</html>
