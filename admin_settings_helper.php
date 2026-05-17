<?php
/**
 * Admin Settings Helper Functions
 * Provides easy access to admin settings throughout the application
 */

require_once 'config.php';

/**
 * Get a specific admin setting value
 * @param string $key The setting key
 * @param mixed $default Default value if setting not found
 * @return mixed The setting value or default
 */
function getAdminSetting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM admin_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        error_log("Error fetching admin setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Get all admin settings as an associative array
 * @return array Array of all settings
 */
function getAllAdminSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, description FROM admin_settings");
        $settings = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'type' => $row['setting_type'],
                'description' => $row['description']
            ];
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error fetching all admin settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Update a specific admin setting
 * @param string $key The setting key
 * @param mixed $value The new value
 * @return bool Success status
 */
function updateAdminSetting($key, $value) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE admin_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    } catch (Exception $e) {
        error_log("Error updating admin setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Check if maintenance mode is enabled
 * @return bool True if maintenance mode is enabled
 */
function isMaintenanceModeEnabled() {
    return getAdminSetting('maintenance_mode', '0') === '1';
}

/**
 * Get formatted business hours
 * @return string Business hours
 */
function getBusinessHours() {
    return getAdminSetting('business_hours', '8:00 AM - 6:00 PM');
}

/**
 * Get site name
 * @return string Site name
 */
function getSiteName() {
    return getAdminSetting('site_name', 'XentroMall');
}

/**
 * Get admin email
 * @return string Admin email
 */
function getAdminEmail() {
    return getAdminSetting('admin_email', 'admin@xentromall.com');
}

/**
 * Get notification email
 * @return string Notification email
 */
function getNotificationEmail() {
    return getAdminSetting('notification_email', 'notifications@xentromall.com');
}

/**
 * Get rental rate per square meter
 * @return float Rental rate
 */
function getRentalRatePerSqm() {
    return (float) getAdminSetting('rental_rate_per_sqm', '500');
}

/**
 * Get late payment fee
 * @return float Late payment fee
 */
function getLatePaymentFee() {
    return (float) getAdminSetting('late_payment_fee', '100');
}

/**
 * Get security deposit in months
 * @return int Number of months
 */
function getSecurityDepositMonths() {
    return (int) getAdminSetting('security_deposit_months', '2');
}

/**
 * Get payment reminder days
 * @return int Number of days
 */
function getPaymentReminderDays() {
    return (int) getAdminSetting('payment_reminder_days', '7');
}

/**
 * Check if auto-approve payments is enabled
 * @return bool True if auto-approve is enabled
 */
function isAutoApprovePaymentsEnabled() {
    return getAdminSetting('auto_approve_payments', '0') === '1';
}

/**
 * Get maximum file upload size in MB
 * @return int Max file size in MB
 */
function getMaxFileSize() {
    return (int) getAdminSetting('max_file_size', '10');
}

/**
 * Get mall contact phone
 * @return string Contact phone
 */
function getContactPhone() {
    return getAdminSetting('contact_phone', '+63 123 456 7890');
}

/**
 * Get mall address
 * @return string Mall address
 */
function getMallAddress() {
    return getAdminSetting('mall_address', 'XentroMall, Main Street, City');
}

/**
 * Get admin signature file path
 * @return string|null
 */
function getAdminSignaturePath() {
    $path = getAdminSetting('admin_signature_path', '');
    return $path !== '' ? $path : null;
}

/**
 * Get admin title/position
 * @return string
 */
function getAdminTitle() {
    return getAdminSetting('admin_title', 'Mall Administrator');
}

/**
 * Get terms and conditions
 * @return string Terms and conditions
 */
function getTermsAndConditions() {
    return getAdminSetting('terms_and_conditions', 'Standard terms and conditions apply.');
}

/**
 * Get welcome message
 * @return string Welcome message
 */
function getWelcomeMessage() {
    return getAdminSetting('welcome_message', 'Welcome to XentroMall Admin Dashboard');
}

/**
 * Initialize default admin settings if they don't exist
 * This function should be called during application setup
 */
function initializeAdminSettings() {
    global $pdo;
    
    try {
        // Create admin_settings table if it doesn't exist
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
            ['welcome_message', 'Welcome to XentroMall Admin Dashboard', 'textarea', 'Welcome message for dashboard'],
            ['admin_signature_path', '', 'text', 'Path to the admin signature image file'],
            ['admin_title', 'Mall Administrator', 'text', 'Default admin title to display on contracts']
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO admin_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error initializing admin settings: " . $e->getMessage());
        return false;
    }
}