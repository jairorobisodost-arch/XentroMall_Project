<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Create admin_settings table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('text', 'email', 'number', 'boolean', 'textarea') DEFAULT 'text',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default settings if they don't exist
    $defaultSettings = [
        ['site_name', 'XentroMall', 'text', 'Name of the mall/site'],
        ['admin_email', 'admin@xentromall.com', 'email', 'Primary admin email address'],
        ['notification_email', 'notifications@xentromall.com', 'email', 'Email for system notifications'],
        ['max_file_size', '10', 'number', 'Maximum file upload size in MB'],
        ['maintenance_mode', '0', 'boolean', 'Enable/disable maintenance mode'],
        ['auto_approve_payments', '0', 'boolean', 'Automatically approve payments'],
        ['payment_reminder_days', '7', 'number', 'Days before payment due to send reminder'],
        ['rental_rate_per_sqm', '500', 'number', 'Default rental rate per square meter'],
        ['late_payment_fee', '100', 'number', 'Late payment penalty fee'],
        ['security_deposit_months', '2', 'number', 'Number of months for security deposit'],
        ['business_hours', '8:00 AM - 6:00 PM', 'text', 'Mall business hours'],
        ['contact_phone', '+63 123 456 7890', 'text', 'Mall contact phone number'],
        ['mall_address', 'XentroMall, Main Street, City', 'textarea', 'Complete mall address'],
        ['terms_and_conditions', 'Standard terms and conditions apply.', 'textarea', 'Terms and conditions for tenants'],
        ['welcome_message', 'Welcome to XentroMall Admin Dashboard', 'textarea', 'Welcome message for dashboard']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO admin_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
} catch (Exception $e) {
    error_log("Settings table creation error: " . $e->getMessage());
}

// Fetch current settings from database
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, description FROM admin_settings ORDER BY setting_key");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = [
            'value' => $row['setting_value'],
            'type' => $row['setting_type'],
            'description' => $row['description']
        ];
    }
} catch (Exception $e) {
    error_log("Settings fetch error: " . $e->getMessage());
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key === 'csrf_token') continue;
            
            // Sanitize and validate input
            $cleanValue = trim($value);
            
            // Update setting in database
            $stmt = $pdo->prepare("UPDATE admin_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
            $stmt->execute([$cleanValue, $key]);
            
            // Update local settings array
            if (isset($settings[$key])) {
                $settings[$key]['value'] = htmlspecialchars($cleanValue);
            }
        }
        
        $pdo->commit();
        $message = 'Settings updated successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error updating settings: ' . $e->getMessage();
        $messageType = 'error';
        error_log("Settings update error: " . $e->getMessage());
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
    <title>Admin Settings - XentroMall</title>
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
        
        .setting-group {
            border-left: 4px solid #e5e7eb;
            padding-left: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .setting-group:hover {
            border-left-color: #059669;
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
                        <i class="fas fa-cogs text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Admin Settings</h1>
                        <p class="text-gray-600">Configure system settings and preferences</p>
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

        <!-- Tabs -->
        <div class="mb-8">
            <div class="flex flex-wrap gap-2 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
                <button class="tab-button active px-6 py-3 rounded-xl font-medium" data-tab="general">
                    <i class="fas fa-globe mr-2"></i>General
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-medium" data-tab="financial">
                    <i class="fas fa-dollar-sign mr-2"></i>Financial
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-medium" data-tab="notifications">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </button>
                <button class="tab-button px-6 py-3 rounded-xl font-medium" data-tab="system">
                    <i class="fas fa-server mr-2"></i>System
                </button>
            </div>
        </div>

        <!-- Settings Form -->
        <form method="POST" action="settings.php" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <!-- General Settings Tab -->
            <div id="general-tab" class="tab-content">
                <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                        <i class="fas fa-globe text-emerald-600"></i>
                        General Settings
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="setting-group">
                            <label for="site_name" class="block font-semibold text-gray-700 mb-2">Site Name</label>
                            <input type="text" id="site_name" name="site_name" 
                                   value="<?php echo htmlspecialchars($settings['site_name']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   required />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['site_name']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <label for="business_hours" class="block font-semibold text-gray-700 mb-2">Business Hours</label>
                            <input type="text" id="business_hours" name="business_hours" 
                                   value="<?php echo htmlspecialchars($settings['business_hours']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['business_hours']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <label for="contact_phone" class="block font-semibold text-gray-700 mb-2">Contact Phone</label>
                            <input type="text" id="contact_phone" name="contact_phone" 
                                   value="<?php echo htmlspecialchars($settings['contact_phone']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['contact_phone']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group lg:col-span-2">
                            <label for="mall_address" class="block font-semibold text-gray-700 mb-2">Mall Address</label>
                            <textarea id="mall_address" name="mall_address" rows="3"
                                      class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"><?php echo htmlspecialchars($settings['mall_address']['value'] ?? ''); ?></textarea>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['mall_address']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group lg:col-span-2">
                            <label for="welcome_message" class="block font-semibold text-gray-700 mb-2">Welcome Message</label>
                            <textarea id="welcome_message" name="welcome_message" rows="3"
                                      class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"><?php echo htmlspecialchars($settings['welcome_message']['value'] ?? ''); ?></textarea>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['welcome_message']['description'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Settings Tab -->
            <div id="financial-tab" class="tab-content hidden">
                <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                        <i class="fas fa-dollar-sign text-emerald-600"></i>
                        Financial Settings
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="setting-group">
                            <label for="rental_rate_per_sqm" class="block font-semibold text-gray-700 mb-2">Rental Rate per SqM (₱)</label>
                            <input type="number" id="rental_rate_per_sqm" name="rental_rate_per_sqm" 
                                   value="<?php echo htmlspecialchars($settings['rental_rate_per_sqm']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   min="0" step="0.01" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['rental_rate_per_sqm']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <label for="late_payment_fee" class="block font-semibold text-gray-700 mb-2">Late Payment Fee (₱)</label>
                            <input type="number" id="late_payment_fee" name="late_payment_fee" 
                                   value="<?php echo htmlspecialchars($settings['late_payment_fee']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   min="0" step="0.01" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['late_payment_fee']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <label for="security_deposit_months" class="block font-semibold text-gray-700 mb-2">Security Deposit (Months)</label>
                            <input type="number" id="security_deposit_months" name="security_deposit_months" 
                                   value="<?php echo htmlspecialchars($settings['security_deposit_months']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   min="1" max="12" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['security_deposit_months']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <label for="payment_reminder_days" class="block font-semibold text-gray-700 mb-2">Payment Reminder (Days)</label>
                            <input type="number" id="payment_reminder_days" name="payment_reminder_days" 
                                   value="<?php echo htmlspecialchars($settings['payment_reminder_days']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   min="1" max="30" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['payment_reminder_days']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group lg:col-span-2">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="auto_approve_payments" name="auto_approve_payments" 
                                       value="1" <?php echo ($settings['auto_approve_payments']['value'] ?? '0') == '1' ? 'checked' : ''; ?>
                                       class="w-5 h-5 text-emerald-600 rounded focus:ring-emerald-500" />
                                <label for="auto_approve_payments" class="font-semibold text-gray-700">Auto-approve Payments</label>
                            </div>
                            <p class="text-sm text-gray-500 mt-1 ml-8"><?php echo htmlspecialchars($settings['auto_approve_payments']['description'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Settings Tab -->
            <div id="notifications-tab" class="tab-content hidden">
                <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                        <i class="fas fa-bell text-emerald-600"></i>
                        Notification Settings
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="setting-group">
                            <label for="admin_email" class="block font-semibold text-gray-700 mb-2">Admin Email</label>
                            <input type="email" id="admin_email" name="admin_email" 
                                   value="<?php echo htmlspecialchars($settings['admin_email']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   required />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['admin_email']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <label for="notification_email" class="block font-semibold text-gray-700 mb-2">Notification Email</label>
                            <input type="email" id="notification_email" name="notification_email" 
                                   value="<?php echo htmlspecialchars($settings['notification_email']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   required />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['notification_email']['description'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Settings Tab -->
            <div id="system-tab" class="tab-content hidden">
                <div class="gradient-card rounded-3xl p-8 shadow-lg border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                        <i class="fas fa-server text-emerald-600"></i>
                        System Settings
                    </h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="setting-group">
                            <label for="max_file_size" class="block font-semibold text-gray-700 mb-2">Max File Size (MB)</label>
                            <input type="number" id="max_file_size" name="max_file_size" 
                                   value="<?php echo htmlspecialchars($settings['max_file_size']['value'] ?? ''); ?>" 
                                   class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   min="1" max="100" />
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['max_file_size']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group">
                            <div class="flex items-center gap-3">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       value="1" <?php echo ($settings['maintenance_mode']['value'] ?? '0') == '1' ? 'checked' : ''; ?>
                                       class="w-5 h-5 text-emerald-600 rounded focus:ring-emerald-500" />
                                <label for="maintenance_mode" class="font-semibold text-gray-700">Maintenance Mode</label>
                            </div>
                            <p class="text-sm text-gray-500 mt-1 ml-8"><?php echo htmlspecialchars($settings['maintenance_mode']['description'] ?? ''); ?></p>
                        </div>

                        <div class="setting-group lg:col-span-2">
                            <label for="terms_and_conditions" class="block font-semibold text-gray-700 mb-2">Terms and Conditions</label>
                            <textarea id="terms_and_conditions" name="terms_and_conditions" rows="6"
                                      class="w-full border border-gray-300 rounded-xl p-3 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"><?php echo htmlspecialchars($settings['terms_and_conditions']['value'] ?? ''); ?></textarea>
                            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($settings['terms_and_conditions']['description'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end">
                <button type="submit" class="gradient-primary text-white px-8 py-4 rounded-2xl font-semibold hover-lift flex items-center gap-3 shadow-lg">
                    <i class="fas fa-save"></i>
                    Save All Settings
                </button>
            </div>
        </form>
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
            const form = document.querySelector('form');
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
    </script>
</body>
</html>
