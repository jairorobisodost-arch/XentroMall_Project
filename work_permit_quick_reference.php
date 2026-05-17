<?php
/**
 * Quick Reference: How to Use Work Permits System
 * 
 * This file shows all the ways to work with permits
 */

require 'config.php';

// ===================================================
// 1. SUBMIT A NEW WORK PERMIT (via form)
// ===================================================

/**
 * When user fills form at work_permit_form.php and submits:
 * 
 * POST data includes:
 * - permit_no (auto-generated if empty)
 * - date_filed
 * - tenant_name (from session)
 * - stall_number
 * - scope_of_work
 * - contractor_type (inside/outside)
 * - permit_valid_from
 * - permit_valid_to
 * - time_from / time_to
 * - security_posting (checkbox)
 * - security_rate, charge_security
 * - janitorial_deployment (checkbox)
 * - janitorial_rate, charge_janitorial
 * - maintenance (checkbox)
 * - maintenance_rate, charge_maintenance
 * - personnel_1, personnel_2, etc
 * - uploaded_image_0, uploaded_image_1, etc
 */

// Form automatically inserts to work_permits table
// Status = 'pending' by default


// ===================================================
// 2. QUERY PERMITS (PHP Examples)
// ===================================================

// Get all permits
$stmt = $pdo->query("SELECT * FROM work_permits ORDER BY created_at DESC");
$allPermits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending permits
$stmt = $pdo->query("SELECT * FROM work_permits WHERE status = 'pending'");
$pendingPermits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved permits
$stmt = $pdo->query("SELECT * FROM work_permits WHERE status = 'approved'");
$approvedPermits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get permits for a specific tenant
$stmt = $pdo->prepare("SELECT * FROM work_permits WHERE tenant_name = ?");
$stmt->execute(['email@example.com']);
$tenantPermits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get single permit
$stmt = $pdo->prepare("SELECT * FROM work_permits WHERE permit_no = ?");
$stmt->execute(['WP-20260125-0001']);
$permit = $stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected
    FROM work_permits
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);


// ===================================================
// 3. UPDATE PERMITS (Admin Actions)
// ===================================================

// Approve a permit (with signature)
$permitNo = 'WP-20260125-0001';
$signature = 'base64_encoded_signature_here';

$stmt = $pdo->prepare("
    UPDATE work_permits 
    SET status = 'approved', 
        admin_signature = ?,
        admin_signed_at = NOW()
    WHERE permit_no = ?
");
$stmt->execute([$signature, $permitNo]);

// Reject a permit
$stmt = $pdo->prepare("
    UPDATE work_permits 
    SET status = 'rejected'
    WHERE permit_no = ?
");
$stmt->execute([$permitNo]);

// Update permit details
$stmt = $pdo->prepare("
    UPDATE work_permits 
    SET permit_valid_from = ?,
        permit_valid_to = ?,
        time_from = ?,
        time_to = ?,
        rate_security = ?,
        rate_janitorial = ?,
        rate_maintenance = ?
    WHERE permit_no = ?
");
$stmt->execute([
    '2026-01-25',
    '2026-01-26',
    '08:00:00',
    '17:00:00',
    175.00,
    175.00,
    175.00,
    $permitNo
]);


// ===================================================
// 4. VIEWS (Pre-made Queries)
// ===================================================

// Active permits (currently valid)
$stmt = $pdo->query("
    SELECT * FROM active_work_permits
");
$active = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pending permits
$stmt = $pdo->query("
    SELECT * FROM pending_work_permits
");
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expired permits
$stmt = $pdo->query("
    SELECT * FROM expired_work_permits
");
$expired = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ===================================================
// 5. DISPLAY IN HTML
// ===================================================

// Example: Show all active permits in table
?>
<table class="permits-table">
    <thead>
        <tr>
            <th>Permit No</th>
            <th>Tenant</th>
            <th>Stall</th>
            <th>Valid From</th>
            <th>Valid To</th>
            <th>Contractor</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($active as $permit): ?>
        <tr>
            <td><?= htmlspecialchars($permit['permit_no']) ?></td>
            <td><?= htmlspecialchars($permit['tenant_name']) ?></td>
            <td><?= htmlspecialchars($permit['stall_number'] ?? 'N/A') ?></td>
            <td><?= $permit['permit_valid_from'] ?></td>
            <td><?= $permit['permit_valid_to'] ?></td>
            <td><?= ucfirst($permit['contractor_type']) ?></td>
            <td><span class="badge badge-<?= strtolower($permit['status']) ?>"><?= ucfirst($permit['status']) ?></span></td>
            <td>
                <a href="view_permit.php?id=<?= urlencode($permit['permit_no']) ?>">View</a>
                <a href="edit_permit.php?id=<?= urlencode($permit['permit_no']) ?>">Edit</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php

// ===================================================
// 6. DATA STRUCTURE
// ===================================================

/**
 * Permit Object Structure:
 * {
 *   "permit_no": "WP-20260125-0001",
 *   "date_filed": "2026-01-25",
 *   "tenant_name": "email@example.com",
 *   "stall_number": "ST-001",
 *   "scope_of_work": "Air conditioning repair",
 *   "permit_valid_from": "2026-01-25",
 *   "permit_valid_to": "2026-01-26",
 *   "time_from": "08:00:00",
 *   "time_to": "17:00:00",
 *   "security_posting": 1,
 *   "rate_security": 175.00,
 *   "charge_security": "No Charge",
 *   "janitorial_deployment": 0,
 *   "rate_janitorial": null,
 *   "charge_janitorial": null,
 *   "maintenance": 1,
 *   "rate_maintenance": 95.00,
 *   "charge_maintenance": "No Charge",
 *   "personnel": "John Doe, Jane Smith",
 *   "contractor_type": "inside",
 *   "tenant_signature": "base64_encoded_data...",
 *   "admin_signature": null,
 *   "guard_signature": null,
 *   "uploaded_images": "[\"image1.jpg\", \"image2.jpg\"]",
 *   "status": "pending",
 *   "admin_signed_at": null,
 *   "guard_signed_at": null,
 *   "created_at": "2026-01-25 10:30:00",
 *   "updated_at": "2026-01-25 10:30:00"
 * }
 */

// ===================================================
// 7. COMMON SCENARIOS
// ===================================================

// Scenario 1: Admin approves a permit
function approvPermit($pdo, $permitNo, $adminSignature) {
    $stmt = $pdo->prepare("
        UPDATE work_permits 
        SET status = 'approved',
            admin_signature = ?,
            admin_signed_at = NOW()
        WHERE permit_no = ?
    ");
    return $stmt->execute([$adminSignature, $permitNo]);
}

// Scenario 2: Get permit with all related data
function getPermitDetails($pdo, $permitNo) {
    $stmt = $pdo->prepare("
        SELECT * FROM work_permits WHERE permit_no = ?
    ");
    $stmt->execute([$permitNo]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($permit) {
        // Parse JSON images if present
        if ($permit['uploaded_images']) {
            $permit['images'] = json_decode($permit['uploaded_images'], true);
        }
    }
    
    return $permit;
}

// Scenario 3: Get summary dashboard
function getDashboardStats($pdo) {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN permit_valid_to >= CURDATE() AND status='approved' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN permit_valid_to < CURDATE() AND status='approved' THEN 1 ELSE 0 END) as expired
        FROM work_permits
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

?>
