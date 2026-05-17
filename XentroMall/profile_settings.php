<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = '';
$messageType = '';

// Fetch current admin profile information
try {
    $stmt = $pdo->prepare('SELECT username, email, created_at FROM users WHERE id = :id AND role = :role');
    $stmt->execute(['id' => $_SESSION['user_id'], 'role' => 'admin']);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        header('Location: login.php');
        exit;
    }
} catch (Exception $e) {
    $message = 'Error fetching profile information: ' . $e->getMessage();
    $messageType = 'error';
}

// Create admin_profiles table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL UNIQUE,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            phone VARCHAR(20),
            address TEXT,
            bio TEXT,
            profile_image VARCHAR(255),
            notification_preferences JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Insert default profile if it doesn't exist
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_profiles (user_id, notification_preferences) VALUES (?, ?)");
    $defaultNotifications = json_encode([
        'email_notifications' => true,
        'payment_alerts' => true,
        'application_alerts' => true,
        'maintenance_alerts' => true,
        'system_alerts' => true
    ]);
    $stmt->execute([$_SESSION['user_id'], $defaultNotifications]);
    
} catch (Exception $e) {
    error_log("Admin profiles table creation error: " . $e->getMessage());
}

// Fetch extended profile information
$profile = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM admin_profiles WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($profile && $profile['notification_preferences']) {
        $profile['notification_preferences'] = json_decode($profile['notification_preferences'], true);
    }
} catch (Exception $e) {
    error_log("Error fetching admin profile: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Update basic user information
        if (isset($_POST['update_basic'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address');
            }
            
            // Check if username/email already exists for other users
            $stmt = $pdo->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?');
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception('Username or email already exists');
            }
            
            // Update user table
            $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
            $stmt->execute([$username, $email, $_SESSION['user_id']]);
            
            $admin['username'] = $username;
            $admin['email'] = $email;
            
            $message = 'Basic information updated successfully!';
            $messageType = 'success';
        }
        
        // Update extended profile information
        if (isset($_POST['update_profile'])) {
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $bio = trim($_POST['bio']);
            
            // Update or insert profile
            $stmt = $pdo->prepare('
                INSERT INTO admin_profiles (user_id, first_name, last_name, phone, address, bio) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                phone = VALUES(phone),
                address = VALUES(address),
                bio = VALUES(bio),
                updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([$_SESSION['user_id'], $firstName, $lastName, $phone, $address, $bio]);
            
            // Update local profile array
            $profile['first_name'] = $firstName;
            $profile['last_name'] = $lastName;
            $profile['phone'] = $phone;
            $profile['address'] = $address;
            $profile['bio'] = $bio;
            
            $message = 'Profile information updated successfully!';
            $messageType = 'success';
        }
        
        // Update notification preferences
        if (isset($_POST['update_notifications'])) {
            $notifications = [
                'email_notifications' => isset($_POST['email_notifications']),
                'payment_alerts' => isset($_POST['payment_alerts']),
                'application_alerts' => isset($_POST['application_alerts']),
                'maintenance_alerts' => isset($_POST['maintenance_alerts']),
                'system_alerts' => isset($_POST['system_alerts'])
            ];
            
            $stmt = $pdo->prepare('
                INSERT INTO admin_profiles (user_id, notification_preferences) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE 
                notification_preferences = VALUES(notification_preferences),
                updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([$_SESSION['user_id'], json_encode($notifications)]);
            
            $profile['notification_preferences'] = $notifications;
            
            $message = 'Notification preferences updated successfully!';
            $messageType = 'success';
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error updating profile: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Generate CSRF token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Profile Settings - XentroMall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
        }
        
        .gradient-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
        
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .tab-button {
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
            color: white;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #059669 0%, #0ea5e9 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="gradient-card rounded-3xl p-6 shadow-lg border border-gray-100 mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="gradient-primary p-3 rounded-2xl">
                        <i class="fas fa-user-cog text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Profile Settings</h1>
                        <p class="text-gray-600">Manage your personal information and preferences</p>
                    </div>
                </div>
                <a href="admin_dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo $messageType === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200'; ?>">
                <div class="flex items-center gap-3">
                    <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Overview -->
        <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100 mb-8">
            <div class="flex items-center gap-6">
                <div class="profile-avatar">
                    <?php 
                    $initials = '';
                    if (!empty($profile['first_name']) && !empty($profile['last_name'])) {
                        $initials = strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1));
                    } else {
                        $initials = strtoupper(substr($admin['username'], 0, 2));
                    }
                    echo $initials;
                    ?>
                </div>
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-900">
                        <?php 
                        if (!empty($profile['first_name']) && !empty($profile['last_name'])) {
                            echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']);
                        } else {
                            echo htmlspecialchars($admin['username']);
                        }
                        ?>
                    </h2>
                    <p class="text-gray-600 text-lg">@<?php echo htmlspecialchars($admin['username']); ?></p>
                    <p class="text-gray-500"><?php echo htmlspecialchars($admin['email']); ?></p>
                    <div class="flex items-center gap-4 mt-3 text-sm text-gray-500">
                        <span><i class="fas fa-calendar mr-2"></i>Joined <?php echo date('M Y', strtotime($admin['created_at'])); ?></span>
                        <?php if (!empty($profile['phone'])): ?>
                            <span><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($profile['phone']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-8">
            <div class="flex flex-wrap gap-2 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
                <button class="tab-button active px-6 py-3 rounded-xl font-medium" data-tab="basic">
                    <i class="fas fa-user mr-2"></i>Basic Info
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-medium" data-tab="profile">
                    <i class="fas fa-id-card mr-2"></i>Profile Details
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-medium" data-tab="notifications">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-medium" data-tab="security">
                    <i class="fas fa-shield-alt mr-2"></i>Security
                </button>
            </div>
        </div>

        <!-- Basic Information Tab -->
        <div id="basic-tab" class="tab-content">
            <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <i class="fas fa-user text-emerald-600"></i>
                    Basic Information
                </h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="update_basic" value="1">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="username" class="block font-semibold text-gray-700 mb-2">Username</label>
                            <input type="text" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($admin['username']); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   required />
                        </div>

                        <div>
                            <label for="email" class="block font-semibold text-gray-700 mb-2">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($admin['email']); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   required />
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="gradient-primary text-white px-6 py-3 rounded-xl font-semibold hover-lift flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Update Basic Info
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Profile Details Tab -->
        <div id="profile-tab" class="tab-content hidden">
            <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <i class="fas fa-id-card text-emerald-600"></i>
                    Profile Details
                </h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block font-semibold text-gray-700 mb-2">First Name</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                        </div>

                        <div>
                            <label for="last_name" class="block font-semibold text-gray-700 mb-2">Last Name</label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                        </div>

                        <div>
                            <label for="phone" class="block font-semibold text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                        </div>

                        <div>
                            <label for="address" class="block font-semibold text-gray-700 mb-2">Address</label>
                            <input type="text" id="address" name="address" 
                                   value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                        </div>

                        <div class="lg:col-span-2">
                            <label for="bio" class="block font-semibold text-gray-700 mb-2">Bio</label>
                            <textarea id="bio" name="bio" rows="4"
                                      class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                      placeholder="Tell us about yourself..."><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="gradient-primary text-white px-6 py-3 rounded-xl font-semibold hover-lift flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications-tab" class="tab-content hidden">
            <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <i class="fas fa-bell text-emerald-600"></i>
                    Notification Preferences
                </h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="update_notifications" value="1">
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-semibold text-gray-900">Email Notifications</h3>
                                <p class="text-sm text-gray-600">Receive general notifications via email</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="email_notifications" class="sr-only peer" 
                                       <?php echo ($profile['notification_preferences']['email_notifications'] ?? true) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-semibold text-gray-900">Payment Alerts</h3>
                                <p class="text-sm text-gray-600">Get notified about payment submissions and approvals</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="payment_alerts" class="sr-only peer" 
                                       <?php echo ($profile['notification_preferences']['payment_alerts'] ?? true) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-semibold text-gray-900">Application Alerts</h3>
                                <p class="text-sm text-gray-600">Receive notifications for new tenant applications</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="application_alerts" class="sr-only peer" 
                                       <?php echo ($profile['notification_preferences']['application_alerts'] ?? true) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-semibold text-gray-900">Maintenance Alerts</h3>
                                <p class="text-sm text-gray-600">Get notified about maintenance requests and work permits</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="maintenance_alerts" class="sr-only peer" 
                                       <?php echo ($profile['notification_preferences']['maintenance_alerts'] ?? true) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-semibold text-gray-900">System Alerts</h3>
                                <p class="text-sm text-gray-600">Receive important system notifications and updates</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="system_alerts" class="sr-only peer" 
                                       <?php echo ($profile['notification_preferences']['system_alerts'] ?? true) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="gradient-primary text-white px-6 py-3 rounded-xl font-semibold hover-lift flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Update Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tab -->
        <div id="security-tab" class="tab-content hidden">
            <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                    <i class="fas fa-shield-alt text-emerald-600"></i>
                    Security Settings
                </h2>
                
                <div class="space-y-6">
                    <div class="p-6 bg-gray-50 rounded-xl">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Change Password</h3>
                        <p class="text-gray-600 mb-4">Update your password to keep your account secure</p>
                        <a href="change_password.php" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors">
                            <i class="fas fa-key"></i>
                            Change Password
                        </a>
                    </div>

                    <div class="p-6 bg-gray-50 rounded-xl">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Account Information</h3>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p><strong>Account Created:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($admin['created_at'])); ?></p>
                            <p><strong>User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
                            <p><strong>Role:</strong> Administrator</p>
                        </div>
                    </div>

                    <div class="p-6 bg-red-50 border border-red-200 rounded-xl">
                        <h3 class="text-lg font-semibold text-red-900 mb-2">Danger Zone</h3>
                        <p class="text-red-700 mb-4">These actions cannot be undone. Please be careful.</p>
                        <div class="space-y-3">
                            <button class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors" onclick="alert('This feature is not implemented yet.')">
                                <i class="fas fa-exclamation-triangle"></i>
                                Reset All Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');

                    // Remove active class from all buttons
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');

                    // Hide all tab contents
                    tabContents.forEach(content => content.classList.add('hidden'));

                    // Show target tab content
                    document.getElementById(targetTab + '-tab').classList.remove('hidden');
                });
            });

            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;

                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('border-red-500');
                        } else {
                            field.classList.remove('border-red-500');
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            });
        });
    </script>
</body>
</html>