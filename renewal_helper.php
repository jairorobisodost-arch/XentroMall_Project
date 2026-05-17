<?php
/**
 * Unified Renewal Helper Class
 * 
 * Provides utility functions for renewal request operations
 */

class UnifiedRenewalHelper {
    
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Determine if user is an existing tenant
     * 
     * @param int $userId
     * @return bool
     */
    public function isExistingTenant($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM payments
            WHERE user_id = ? AND status = 'approved'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
    
    /**
     * Get request type for user
     * 
     * @param int $userId
     * @return string 'new_application' or 'renewal'
     */
    public function getRequestType($userId) {
        return $this->isExistingTenant($userId) ? 'renewal' : 'new_application';
    }
    
    /**
     * Get tenant data for existing tenant
     * 
     * @param int $userId
     * @return array|null
     */
    public function getTenantData($userId) {
        $stmt = $this->pdo->prepare("
            SELECT td.id, td.tradename, td.stall_id, td.email, td.contact_person, 
                   td.mobile, td.company_name, td.business_address
            FROM tenant_details td
            WHERE td.user_id = ? AND td.status = 'approved'
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Get tenant ID from email
     * 
     * @param string $email
     * @return int|null
     */
    public function getTenantIdByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM tenants WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return $result['id'] ?? null;
    }
    
    /**
     * Get contract data for tenant
     * 
     * @param int $tenantId
     * @return array|null
     */
    public function getContractData($tenantId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM tenant_lease_dates WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetch();
    }
    
    /**
     * Calculate late renewal fee
     * 
     * @param int $tenantId
     * @return float
     */
    public function calculateLateFee($tenantId) {
        $contract = $this->getContractData($tenantId);
        
        if (!$contract) {
            return 0;
        }
        
        $expirationDate = new DateTime($contract['lease_expiration_date']);
        $today = new DateTime();
        
        if ($today > $expirationDate) {
            // Get late fee from settings
            $stmt = $this->pdo->prepare("
                SELECT setting_value FROM system_settings 
                WHERE setting_key = 'late_renewal_fee'
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            return (float)($result['setting_value'] ?? 500);
        }
        
        return 0;
    }
    
    /**
     * Calculate total amount for request
     * 
     * @param string $requestType 'new_application' or 'renewal'
     * @param float $monthlyRate
     * @param float $lateFee
     * @return float
     */
    public function calculateTotalAmount($requestType, $monthlyRate, $lateFee = 0) {
        if ($requestType === 'new_application') {
            return $monthlyRate * 3; // 3 months advance
        } else {
            return $monthlyRate + $lateFee; // Monthly + late fee
        }
    }
    
    /**
     * Get payment type for request
     * 
     * @param string $requestType
     * @return string
     */
    public function getPaymentType($requestType) {
        return $requestType === 'new_application' 
            ? 'three_months_advance' 
            : 'monthly';
    }
    
    /**
     * Get account action for request
     * 
     * @param string $requestType
     * @return string
     */
    public function getAccountAction($requestType) {
        return $requestType === 'new_application' 
            ? 'create_new' 
            : 'use_existing';
    }
    
    /**
     * Get advance months for request
     * 
     * @param string $requestType
     * @return int
     */
    public function getAdvanceMonths($requestType) {
        return $requestType === 'new_application' ? 3 : 0;
    }
    
    /**
     * Check if user has pending renewal
     * 
     * @param int $userId
     * @return array|null
     */
    public function getPendingRenewal($userId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM unified_renewal_requests
            WHERE user_id = ? AND status IN ('pending', 'approved', 'payment_pending')
            ORDER BY submitted_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    /**
     * Create renewal request
     * 
     * @param array $data
     * @return int|false Request ID or false on failure
     */
    public function createRenewalRequest($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO unified_renewal_requests (
                    user_id, tenant_id, tenant_detail_id,
                    request_type, tradename, company_name, business_address, 
                    contact_person, mobile, email,
                    business_structure, business_registration_type, tin_number,
                    stall_id, monthly_rate,
                    payment_type, total_amount, advance_months, late_renewal_fee,
                    account_action,
                    letter_of_intent, business_profile, business_registration, valid_id,
                    bir_registration, extended_bir_registration, financial_statement,
                    status, submitted_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['tenant_id'] ?? null,
                $data['tenant_detail_id'] ?? null,
                $data['request_type'],
                $data['tradename'],
                $data['company_name'] ?? null,
                $data['business_address'],
                $data['contact_person'],
                $data['mobile'],
                $data['email'],
                $data['business_structure'],
                $data['business_registration_type'] ?? null,
                $data['tin_number'] ?? null,
                $data['stall_id'],
                $data['monthly_rate'],
                $data['payment_type'],
                $data['total_amount'],
                $data['advance_months'],
                $data['late_renewal_fee'] ?? 0,
                $data['account_action'],
                $data['letter_of_intent'] ?? null,
                $data['business_profile'] ?? null,
                $data['business_registration'] ?? null,
                $data['valid_id'] ?? null,
                $data['bir_registration'] ?? null,
                $data['extended_bir_registration'] ?? null,
                $data['financial_statement'] ?? null,
                'pending'
            ]);
            
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating renewal request: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update renewal request status
     * 
     * @param int $renewalId
     * @param string $newStatus
     * @param int $adminId
     * @param string $feedback
     * @return bool
     */
    public function updateRenewalStatus($renewalId, $newStatus, $adminId, $feedback = '') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE unified_renewal_requests
                SET status = ?, admin_feedback = ?, admin_reviewed_at = NOW(), admin_reviewed_by = ?
                WHERE id = ?
            ");
            
            return $stmt->execute([$newStatus, $feedback, $adminId, $renewalId]);
        } catch (PDOException $e) {
            error_log("Error updating renewal status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log renewal action
     * 
     * @param int $renewalId
     * @param string $action
     * @param string $oldStatus
     * @param string $newStatus
     * @param int $adminId
     * @param string $notes
     * @return bool
     */
    public function logAction($renewalId, $action, $oldStatus, $newStatus, $adminId, $notes = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO unified_renewal_audit_log
                (renewal_request_id, action, old_status, new_status, admin_id, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([$renewalId, $action, $oldStatus, $newStatus, $adminId, $notes]);
        } catch (PDOException $e) {
            error_log("Error logging action: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get renewal request by ID
     * 
     * @param int $renewalId
     * @return array|null
     */
    public function getRenewalRequest($renewalId) {
        $stmt = $this->pdo->prepare("
            SELECT urr.*, u.username, u.email as user_email
            FROM unified_renewal_requests urr
            JOIN users u ON urr.user_id = u.id
            WHERE urr.id = ?
        ");
        $stmt->execute([$renewalId]);
        return $stmt->fetch();
    }
    
    /**
     * Get all renewal requests with filters
     * 
     * @param array $filters
     * @return array
     */
    public function getAllRenewalRequests($filters = []) {
        $query = "
            SELECT urr.*, u.username, u.email as user_email, admin.username as admin_name
            FROM unified_renewal_requests urr
            JOIN users u ON urr.user_id = u.id
            LEFT JOIN users admin ON urr.admin_reviewed_by = admin.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (isset($filters['request_type'])) {
            $query .= " AND urr.request_type = ?";
            $params[] = $filters['request_type'];
        }
        
        if (isset($filters['status'])) {
            $query .= " AND urr.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['user_id'])) {
            $query .= " AND urr.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        $query .= " ORDER BY urr.submitted_at DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get statistics
     * 
     * @return array
     */
    public function getStatistics() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'payment_pending' THEN 1 ELSE 0 END) as payment_pending,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN request_type = 'new_application' THEN 1 ELSE 0 END) as new_apps,
                SUM(CASE WHEN request_type = 'renewal' THEN 1 ELSE 0 END) as renewals
            FROM unified_renewal_requests
        ");
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateFileUpload($file, $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], $maxSize = 5242880) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds limit'];
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedTypes)) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Move uploaded file to destination
     * 
     * @param array $file
     * @param string $destination
     * @return string|false File path or false on failure
     */
    public function moveUploadedFile($file, $destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $destination . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $targetPath;
        }
        
        return false;
    }
}

// Usage example:
/*
require 'config.php';
require 'renewal_helper.php';

$helper = new UnifiedRenewalHelper($pdo);

// Check if user is existing tenant
if ($helper->isExistingTenant($userId)) {
    echo "This is a renewal request";
} else {
    echo "This is a new application";
}

// Get statistics
$stats = $helper->getStatistics();
echo "Total requests: " . $stats['total'];
*/
?>
