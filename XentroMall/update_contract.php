<?php
session_start();
require 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenant_id = $_POST['tenant_id'] ?? '';
    $lease_start_date = $_POST['lease_start_date'] ?? '';
    $lease_expiration_date = $_POST['lease_expiration_date'] ?? '';
    
    // Debug logging
    error_log("Contract Update - Tenant ID: $tenant_id, Start: $lease_start_date, End: $lease_expiration_date");
    
    if (empty($tenant_id) || empty($lease_start_date) || empty($lease_expiration_date)) {
        $_SESSION['error_message'] = 'All fields are required.';
        header('Location: admin_dashboard.php');
        exit;
    }
    
    // Validate dates
    $start = new DateTime($lease_start_date);
    $end = new DateTime($lease_expiration_date);
    
    if ($end <= $start) {
        $_SESSION['error_message'] = 'End date must be after start date.';
        header('Location: admin_dashboard.php');
        exit;
    }
    
    try {
        // Get tenant info from tenant_details (tenant_id is actually tenant_details.id)
        $stmt = $pdo->prepare('SELECT td.user_id, td.email, u.email as user_email 
                               FROM tenant_details td 
                               LEFT JOIN users u ON u.id = td.user_id 
                               WHERE td.id = :id');
        $stmt->execute(['id' => $tenant_id]);
        $tenant = $stmt->fetch();
        
        if (!$tenant) {
            $_SESSION['error_message'] = 'Tenant not found in tenant_details.';
            header('Location: admin_dashboard.php');
            exit;
        }
        
        // Use user_id to find/create tenant record
        $tenant_lease_id = null;
        
        // First, try to find tenant by user_id in users table
        if ($tenant['user_id']) {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = :user_id AND role = "tenant"');
            $stmt->execute(['user_id' => $tenant['user_id']]);
            $user_record = $stmt->fetch();
            
            if ($user_record) {
                // Check if tenant record exists in tenants table
                $stmt = $pdo->prepare('SELECT id FROM tenants WHERE email = :email');
                $stmt->execute(['email' => $user_record['email']]);
                $tenant_record = $stmt->fetch();
                
                if (!$tenant_record) {
                    // Create tenant record if it doesn't exist
                    $stmt = $pdo->prepare('INSERT INTO tenants (username, email, password, role, created_at) 
                                          SELECT username, email, password, role, created_at FROM users WHERE id = :user_id');
                    $stmt->execute(['user_id' => $tenant['user_id']]);
                    $tenant_lease_id = $pdo->lastInsertId();
                    error_log("Created new tenant record from user_id: {$tenant['user_id']}, tenant_id: $tenant_lease_id");
                } else {
                    $tenant_lease_id = $tenant_record['id'];
                }
            }
        }
        
        if ($tenant_lease_id) {
            
            // Check if lease record exists
            $stmt = $pdo->prepare('SELECT tenant_id FROM tenant_lease_dates WHERE tenant_id = :tenant_id');
            $stmt->execute(['tenant_id' => $tenant_lease_id]);
            $lease = $stmt->fetch();
            
            if ($lease) {
                // Update existing lease
                $stmt = $pdo->prepare('
                    UPDATE tenant_lease_dates 
                    SET lease_start_date = :start_date, 
                        lease_expiration_date = :end_date,
                        status = :status
                    WHERE tenant_id = :tenant_id
                ');
                $stmt->execute([
                    'start_date' => $lease_start_date,
                    'end_date' => $lease_expiration_date,
                    'status' => 'active',
                    'tenant_id' => $tenant_lease_id
                ]);
                error_log("✅ Contract UPDATED for tenant_id: $tenant_lease_id (user_id: {$tenant['user_id']}) - Start: $lease_start_date, End: $lease_expiration_date");
                $_SESSION['success_message'] = 'Contract dates updated successfully! Tenant will see the changes in their dashboard.';
            } else {
                // Insert new lease record
                $stmt = $pdo->prepare('
                    INSERT INTO tenant_lease_dates (tenant_id, lease_start_date, lease_expiration_date, status) 
                    VALUES (:tenant_id, :start_date, :end_date, :status)
                ');
                $stmt->execute([
                    'tenant_id' => $tenant_lease_id,
                    'start_date' => $lease_start_date,
                    'end_date' => $lease_expiration_date,
                    'status' => 'active'
                ]);
                error_log("✅ Contract INSERTED for tenant_id: $tenant_lease_id (user_id: {$tenant['user_id']}) - Start: $lease_start_date, End: $lease_expiration_date");
                $_SESSION['success_message'] = 'Contract dates created successfully! Tenant will see the changes in their dashboard.';
            }
        } else {
            error_log("❌ Failed to resolve tenant_lease_id for tenant_details.id: $tenant_id, user_id: {$tenant['user_id']}");
            $_SESSION['error_message'] = 'Could not find or create tenant lease record. Please check if tenant exists in users table.';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error updating contract: ' . $e->getMessage();
    }
    
    header('Location: admin_dashboard.php');
    exit;
} else {
    header('Location: admin_dashboard.php');
    exit;
}
?>
