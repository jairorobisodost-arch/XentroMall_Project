<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment_reminder_helper.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Please login as Admin.");
}

$message = '';
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantUserId = $_POST['tenant_user_id'] ?? 0;
    $dueDate = $_POST['due_date'] ?? date('F j, Y', strtotime('+7 days'));
    $amount = $_POST['amount'] ?? 10000.00;

    if ($tenantUserId > 0) {
        $results = sendPaymentReminder($tenantUserId, $dueDate, (float)$amount);
        $message = "Test triggered for User ID: " . htmlspecialchars($tenantUserId);
    } else {
        $message = "Please select a valid tenant User ID.";
    }
}

// Fetch all tenants to populate the dropdown
$stmt = $pdo->prepare("SELECT u.id, u.username, td.tradename FROM users u JOIN tenant_details td ON u.id = td.user_id WHERE u.role = 'tenant'");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Payment Reminders (Admin Only)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 p-8">

<div class="max-w-3xl mx-auto bg-white p-8 rounded-xl shadow-md">
    <div class="flex items-center gap-3 mb-6">
        <i class="fas fa-paper-plane text-2xl text-blue-600"></i>
        <h1 class="text-2xl font-bold text-gray-800">Test SMS & Email Payment Reminders</h1>
    </div>

    <p class="mb-6 text-gray-600">Use this form to trigger a fake payment reminder text and email. Select one of your tenants from the dropdown and hit send.</p>

    <?php if ($message): ?>
        <div class="bg-blue-100 text-blue-800 p-4 rounded-lg mb-6 border border-blue-200">
            <strong><i class="fas fa-info-circle mr-2"></i> <?php echo $message; ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($results): ?>
        <div class="grid grid-cols-2 gap-4 mb-6">
            <!-- Email Result Box -->
            <div class="p-4 rounded-lg border <?php echo $results['email']['success'] ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>">
                <h3 class="font-bold flex items-center gap-2"><i class="fas fa-envelope"></i> Email Status</h3>
                <p class="mt-2 text-sm"><?php echo htmlspecialchars($results['email']['message']); ?></p>
            </div>
            
            <!-- SMS Result Box -->
            <div class="p-4 rounded-lg border <?php echo $results['sms']['success'] ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>">
                <h3 class="font-bold flex items-center gap-2"><i class="fas fa-sms"></i> SMS Status</h3>
                <p class="mt-2 text-sm"><?php echo htmlspecialchars($results['sms']['message']); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4 bg-gray-50 p-6 rounded-lg border border-gray-200">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Select Tenant</label>
            <select name="tenant_user_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">-- Choose a Tenant --</option>
                <?php foreach ($tenants as $t): ?>
                    <option value="<?php echo $t['id']; ?>">
                        User ID: <?php echo $t['id']; ?> - <?php echo htmlspecialchars($t['tradename'] ?: $t['username']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fake Due Date</label>
            <input type="text" name="due_date" value="<?php echo date('F j, Y', strtotime('+7 days')); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fake Amount Due (₱)</label>
            <input type="number" step="0.01" name="amount" value="15000.00" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="pt-4 flex gap-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition">
                <i class="fas fa-paper-plane mr-2"></i>Send Test SMS and Email
            </button>
            <a href="admin_dashboard.php" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium hover:bg-gray-300 transition text-center flex items-center justify-center">
                Back to Dashboard
            </a>
        </div>
    </form>
</div>

</body>
</html>
