<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        try {
            // Update user table
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$_POST['email'], $userId]);
            
            // Update tenant details
            $stmt = $pdo->prepare("
                UPDATE tenant_details 
                SET mobile = ?, 
                    business_address = ?,
                    emergency_contact = ?,
                    emergency_phone = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $_POST['mobile'],
                $_POST['business_address'],
                $_POST['emergency_contact'],
                $_POST['emergency_phone'],
                $userId
            ]);
            
            $message = 'Profile updated successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error updating profile: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($newPassword !== $confirmPassword) {
            $message = 'New passwords do not match!';
            $messageType = 'error';
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if (password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    $message = 'Password changed successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Current password is incorrect!';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error changing password: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Fetch user information
try {
    $stmtUser = $pdo->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    $stmtTenant = $pdo->prepare("
        SELECT td.*, s.stall_number, s.description as stall_description, s.monthly_rate
        FROM tenant_details td
        LEFT JOIN stalls s ON td.stall_id = s.id
        WHERE td.user_id = ?
    ");
    $stmtTenant->execute([$userId]);
    $tenant = $stmtTenant->fetch();

} catch (Exception $e) {
    $message = 'Error fetching profile: ' . $e->getMessage();
    $messageType = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
        .glass-effect { backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95); }
        .tab-active { border-bottom: 3px solid #10b981; color: #10b981; }
        .input-group { transition: all 0.3s ease; }
        .input-group:focus-within { transform: translateY(-2px); }
    </style>
</head>
<body class="min-h-screen gradient-bg">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Header -->
        <div class="glass-effect rounded-2xl p-6 mb-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-cog text-green-600"></i> Settings
                    </h1>
                    <p class="text-gray-600 mt-2">Manage your profile and account settings</p>
                </div>
                <a href="tenant_dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200'; ?>">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="glass-effect rounded-2xl shadow-xl mb-6">
            <div class="flex border-b border-gray-200">
                <button onclick="showTab('profile')" id="profile-tab" class="px-6 py-4 font-semibold text-gray-700 hover:text-green-600 transition tab-active">
                    <i class="fas fa-user mr-2"></i>Personal Information
                </button>
                <button onclick="showTab('security')" id="security-tab" class="px-6 py-4 font-semibold text-gray-700 hover:text-green-600 transition">
                    <i class="fas fa-shield-alt mr-2"></i>Security
                </button>
                <button onclick="showTab('preferences')" id="preferences-tab" class="px-6 py-4 font-semibold text-gray-700 hover:text-green-600 transition">
                    <i class="fas fa-sliders-h mr-2"></i>Preferences
                </button>
            </div>

            <!-- Profile Tab -->
            <div id="profile-content" class="p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Account Information -->
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-user-circle text-green-600 mr-2"></i>Account Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                            </div>
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Business Information -->
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-briefcase text-green-600 mr-2"></i>Business Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Trade Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($tenant['tradename'] ?? ''); ?>" readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                            </div>
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number</label>
                                <input type="tel" name="mobile" value="<?php echo htmlspecialchars($tenant['mobile'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="input-group md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Business Address</label>
                                <textarea name="business_address" rows="3"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"><?php echo htmlspecialchars($tenant['business_address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Stall Information -->
                    <?php if ($tenant): ?>
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-store text-green-600 mr-2"></i>Stall Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stall Number</label>
                                <input type="text" value="<?php echo htmlspecialchars($tenant['stall_number']); ?>" readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Rate</label>
                                <input type="text" value="₱<?php echo number_format($tenant['monthly_rate'], 2); ?>" readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Member Since</label>
                                <input type="text" value="<?php echo date('F d, Y', strtotime($user['created_at'])); ?>" readonly
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Emergency Contact -->
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-phone-alt text-green-600 mr-2"></i>Emergency Contact
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($tenant['emergency_contact'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Contact Phone</label>
                                <input type="tel" name="emergency_phone" value="<?php echo htmlspecialchars($tenant['emergency_phone'] ?? ''); ?>"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Tab -->
            <div id="security-content" class="p-6 hidden">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-key text-green-600 mr-2"></i>Change Password
                        </h3>
                        <div class="space-y-4">
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password *</label>
                                <input type="password" name="current_password" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password *</label>
                                <input type="password" name="new_password" required minlength="8"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                            <div class="input-group">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password *</label>
                                <input type="password" name="confirm_password" required minlength="8"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition">
                            <i class="fas fa-shield-alt mr-2"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preferences Tab -->
            <div id="preferences-content" class="p-6 hidden">
                <div class="bg-gray-50 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-bell text-green-600 mr-2"></i>Notification Preferences
                    </h3>
                    <div class="space-y-4">
                        <label class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200">
                            <div>
                                <span class="font-medium text-gray-800">Email Notifications</span>
                                <p class="text-sm text-gray-600">Receive payment confirmations and updates via email</p>
                            </div>
                            <input type="checkbox" checked class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200">
                            <div>
                                <span class="font-medium text-gray-800">SMS Notifications</span>
                                <p class="text-sm text-gray-600">Receive important alerts via SMS</p>
                            </div>
                            <input type="checkbox" checked class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200">
                            <div>
                                <span class="font-medium text-gray-800">Payment Reminders</span>
                                <p class="text-sm text-gray-600">Get reminded before payment due dates</p>
                            </div>
                            <input type="checkbox" checked class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200">
                            <div>
                                <span class="font-medium text-gray-800">Renewal Notifications</span>
                                <p class="text-sm text-gray-600">Receive alerts when contract renewal is due</p>
                            </div>
                            <input type="checkbox" checked class="w-5 h-5 text-green-600 rounded focus:ring-green-500">
                        </label>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-palette text-green-600 mr-2"></i>Display Preferences
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Language</label>
                            <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option>English</option>
                                <option>Filipino</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Time Zone</label>
                            <select class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option>Philippines Time (UTC+8)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all content
            document.getElementById('profile-content').classList.add('hidden');
            document.getElementById('security-content').classList.add('hidden');
            document.getElementById('preferences-content').classList.add('hidden');
            
            // Remove active class from all tabs
            document.getElementById('profile-tab').classList.remove('tab-active');
            document.getElementById('security-tab').classList.remove('tab-active');
            document.getElementById('preferences-tab').classList.remove('tab-active');
            
            // Show selected content and activate tab
            document.getElementById(tabName + '-content').classList.remove('hidden');
            document.getElementById(tabName + '-tab').classList.add('tab-active');
        }
    </script>
</body>
</html>
