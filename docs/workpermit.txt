<?php
session_start();
require 'config.php';

// Ensure work_permits table has all required columns
try {
    $requiredColumns = [
        'uploaded_images' => "LONGTEXT DEFAULT NULL",
        'contractor_type' => "enum('inside','outside') DEFAULT 'inside'"
    ];
    
    $checkStmt = $pdo->query("SHOW COLUMNS FROM work_permits");
    $existingColumns = $checkStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    foreach ($requiredColumns as $colName => $colDef) {
        if (!in_array($colName, $existingColumns)) {
            $pdo->exec("ALTER TABLE work_permits ADD COLUMN $colName $colDef");
        }
    }
} catch (PDOException $e) {
    // Silently continue if column addition fails
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

// Fetch tenant application status, stall_id, and start date
$stmtTenant = $pdo->prepare("SELECT td.status, td.stall_id, p.payment_date as start_date
                             FROM tenant_details td 
                             LEFT JOIN payments p ON td.user_id = p.user_id AND p.status = 'approved'
                             WHERE td.user_id = ? 
                             ORDER BY p.payment_date ASC 
                             LIMIT 1");
$stmtTenant->execute([$userId]);
$tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);

$stallId = $tenantData['stall_id'] ?? null;

// Fetch stall details if stall_id exists
$stallDetails = null;
if ($stallId) {
    $stmtStall = $pdo->prepare("SELECT * FROM stalls WHERE id = ?");
    $stmtStall->execute([$stallId]);
    $stallDetails = $stmtStall->fetch(PDO::FETCH_ASSOC);
}

// Fetch existing work permits for this tenant
$existingPermits = [];
try {
    $stmtPermits = $pdo->prepare("SELECT * FROM work_permits WHERE tenant_name = ? ORDER BY created_at DESC");
    $stmtPermits->execute([$_SESSION['username'] ?? '']);
    $existingPermits = $stmtPermits->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $existingPermits = [];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_work_permit'])) {
// Debug: Show all POST data
error_log("POST data: " . print_r($_POST, true));

// Generate permit number if not provided
$permitNo = $_POST['permit_no'] ?? null;

// Try backup field if main is empty
if (empty($permitNo)) {
$permitNo = $_POST['permit_no_backup'] ?? null;
}

// Generate if still empty
if (empty($permitNo)) {
$now = new DateTime();
$permitNo = 'WP-' . $now->format('Ymd-His');
error_log("Generated permit number: " . $permitNo);
}

error_log("Final permit number: " . $permitNo);

$dateFiled = $_POST['date_filed'] ?? null;
$tenantName = $_SESSION['username'] ?? null;
$stallNumber = $_POST['stall_number'] ?? null;
$scopeOfWork = $_POST['scope_of_work'] ?? null;

// New contractor fields
$contractorType = $_POST['contractor_type'] ?? 'inside';
$isGatePass = ($contractorType === 'outside') ? 1 : 0;

$permitValidFrom = $_POST['permit_valid_from'] ?? null;
$permitValidTo = $_POST['permit_valid_to'] ?? null;
$timeFrom = $_POST['time_from'] ?? null;
$timeTo = $_POST['time_to'] ?? null;

// For Inside contractors, leave permit validity fields empty (admin will fill them)
if ($contractorType === 'inside') {
    $permitValidFrom = null;
    $permitValidTo = null;
    $timeFrom = null;
    $timeTo = null;
}

// Handle uploaded images
$uploadedImages = [];
for ($i = 0; $i < 20; $i++) { // Support up to 20 images
$imageKey = "uploaded_image_" . $i;
if (isset($_POST[$imageKey])) {
$uploadedImages[] = $_POST[$imageKey];
}
}
$imagesJson = json_encode($uploadedImages);

// Services
$securityPosting = isset($_POST['security_posting']) ? 1 : 0;
$securityRate = $_POST['security_rate'] ?? null;
$securityCharge = $_POST['security_charge'] ?? null;

$janitorialDeployment = isset($_POST['janitorial_deployment']) ? 1 : 0;
$janitorialRate = $_POST['janitorial_rate'] ?? null;
$janitorialCharge = $_POST['janitorial_charge'] ?? null;

$maintenance = isset($_POST['maintenance']) ? 1 : 0;
$maintenanceRate = $_POST['maintenance_rate'] ?? null;
$maintenanceCharge = $_POST['maintenance_charge'] ?? null;

// Collect personnel names
$personnel = [];
for ($i = 1; $i <= 20; $i++) {
$name = $_POST['personnel_' . $i] ?? null;
if (!empty($name)) {
$personnel[] = $name;
}
}
$personnel = implode(', ', $personnel);

// Check if permit number already exists
$checkStmt = $pdo->prepare("SELECT COUNT(*) FROM work_permits WHERE permit_no = ?");
$checkStmt->execute([$permitNo]);
$exists = $checkStmt->fetchColumn();

if ($exists) {
$_SESSION['work_permit_error_message'] = "Permit No. already exists. Please use a unique Permit No.";
} else {
try {
    // Direct INSERT with explicit columns matching the new schema
    $sql = "INSERT INTO work_permits (
        permit_no, 
        date_filed, 
        tenant_name, 
        stall_number, 
        scope_of_work,
        contractor_type,
        permit_valid_from, 
        permit_valid_to, 
        time_from, 
        time_to,
        uploaded_images,
        security_posting, 
        rate_security, 
        charge_security,
        janitorial_deployment, 
        rate_janitorial, 
        charge_janitorial,
        maintenance, 
        rate_maintenance, 
        charge_maintenance,
        personnel, 
        status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $permitNo,
        $dateFiled,
        $tenantName,
        $stallNumber ?? '',
        $scopeOfWork,
        $contractorType,
        $permitValidFrom,
        $permitValidTo,
        $timeFrom,
        $timeTo,
        $imagesJson,
        $securityPosting,
        $securityRate ?: null,
        $securityCharge ?: 'No Charge',
        $janitorialDeployment,
        $janitorialRate ?: null,
        $janitorialCharge ?: 'No Charge',
        $maintenance,
        $maintenanceRate ?: null,
        $maintenanceCharge ?: 'No Charge',
        $personnel ?: '',
        'pending'
    ]);
    
    $_SESSION['work_permit_success_message'] = "Work permit submitted successfully.";
    header("Location: work_permit_form.php");
    exit;
} catch (PDOException $e) {
    $_SESSION['work_permit_error_message'] = "Error submitting work permit: " . $e->getMessage();
}
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Permit - Xentro Mall</title>
    <style>
        :root {
          --primary: #059669;
          --primary-dark: #047857;
          --secondary: #0ea5e9;
          --accent: #f59e0b;
          --surface: #ffffff;
          --background: #f8fafc;
        }
        
        body { 
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #ecfdf5 100%);
            margin: 0;
            padding: 20px;
        }
        
        .form-container {
            max-width: 900px;
            margin: 0 auto;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            margin-right: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .mall-name {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }
        
        .mall-location {
            font-size: 16px;
            color: #64748b;
            margin: 0;
        }
        
        .form-title {
            font-size: 32px;
            font-weight: 800;
            text-align: center;
            margin: 30px 0;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            padding-bottom: 15px;
            border-bottom: 3px solid transparent;
            background-image: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%), linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            background-clip: padding-box, border-box;
            background-origin: padding-box, border-box;
            background-size: 100% 100%, 100% 3px;
            background-position: 0 0, 0 100%;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 25px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .form-row {
            display: flex;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .form-label {
            width: 180px;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
        }
        
        .form-input {
            flex: 1;
            border: 2px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 14px;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: #ffffff;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .form-input:disabled {
            background: #f8fafc;
            color: #64748b;
            cursor: not-allowed;
        }
        
        .authorization-text {
            font-size: 14px;
            line-height: 1.6;
            margin: 20px 0;
            text-align: justify;
            color: #4b5563;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }
        
        .scope-box {
            width: 100%;
            height: 100px;
            border: 2px solid #e2e8f0;
            padding: 12px;
            font-size: 14px;
            resize: vertical;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-family: "Inter", sans-serif;
        }
        
        .scope-box:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .validity-section {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .validity-label {
            width: 120px;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
        }
        
        .services-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .services-table th,
        .services-table td {
            border: 1px solid #e2e8f0;
            padding: 12px;
            text-align: center;
            font-size: 13px;
        }
        
        .services-table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .service-name {
            text-align: left;
            font-weight: 600;
            color: #374151;
        }
        
        .personnel-section {
            margin: 30px 0;
            padding: 25px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .personnel-title {
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 16px;
            color: var(--primary);
        }
        
        .personnel-list {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        
        .personnel-column {
            width: 48%;
            display: flex;
            flex-direction: column;
        }
        
        .personnel-row {
            display: flex;
            margin-bottom: 8px;
            align-items: center;
        }
        
        .personnel-number {
            width: 30px;
            font-weight: 600;
            font-size: 12px;
            color: var(--primary);
        }
        
        .personnel-name {
            flex: 1;
        }
        
        .personnel-name input {
            width: 100%;
            border: 2px solid #e2e8f0;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .personnel-name input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }
        
        .signature-section {
            margin-top: 40px;
            padding: 30px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 30%;
            text-align: center;
        }
        
        .signature-line {
            border-bottom: 2px solid var(--primary);
            height: 60px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .signature-title {
            font-weight: 700;
            font-size: 12px;
            margin-bottom: 5px;
            color: #374151;
        }
        
        .signature-subtitle {
            font-size: 11px;
            color: #64748b;
        }
        
        .note {
            font-size: 11px;
            font-style: italic;
            color: #64748b;
            margin-top: 15px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .submit-section {
            text-align: center;
            margin-top: 40px;
            padding: 30px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 14px 32px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .back-btn {
            background: #64748b;
            color: white;
            padding: 14px 24px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            margin-right: 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .back-btn:hover {
            background: #475569;
            transform: translateY(-2px);
        }
        
        .upload-btn {
            background: linear-gradient(135deg, var(--secondary) 0%, #0284c7 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        
        .image-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .image-preview-item .remove-btn {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .image-preview-item .remove-btn:hover {
            background: rgba(220, 38, 38, 1);
            transform: scale(1.1);
        }
        
        .message {
            padding: 16px 20px;
            margin-bottom: 24px;
            border-radius: 12px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .success {
            background: #f0fdf4;
            color: #166534;
            border-color: #22c55e;
        }
        
        .error {
            background: #fef2f2;
            color: #dc2626;
            border-color: #ef4444;
        }
        
        /* Contractor Type Radio Buttons */
        input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            accent-color: var(--primary);
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        input[type="radio"]:checked + label,
        input[type="checkbox"]:checked + label {
            color: var(--primary);
            font-weight: 500;
        }
        
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }
            
            body {
                padding: 0;
                background: white;
                margin: 0;
                font-size: 12px;
                line-height: 1.2;
            }
            
            .form-container {
                border: 2px solid #000;
                box-shadow: none;
                max-width: 100%;
                padding: 10px;
                margin: 0;
                height: auto;
                min-height: 277mm;
            }
            
            .header {
                margin-bottom: 3px;
            }
            
            .logo {
                width: 30px;
                height: 30px;
            }
            
            .mall-name {
                font-size: 14px;
                margin: 0;
            }
            
            .mall-location {
                font-size: 9px;
                margin: 0;
            }
            
            .form-title {
                font-size: 20px;
                border-bottom: 2px solid #000;
                padding-bottom: 5px;
                margin: 8px 0;
            }
            
            .form-section {
                margin-bottom: 5px;
                page-break-inside: avoid;
            }
            
            .form-row {
                margin-bottom: 3px;
            }
            
            .form-label {
                font-size: 11px;
                width: 110px;
                margin-bottom: 0;
                font-weight: bold;
            }
            
            .form-input {
                border: 1px solid #000;
                font-size: 11px;
                padding: 2px;
                height: 14px;
            }
            
            .authorization-text {
                font-size: 11px;
                text-align: justify;
                margin: 3px 0;
                line-height: 1.2;
            }
            
            .scope-box {
                border: 1px solid #000;
                height: 40px;
                font-size: 11px;
                padding: 3px;
            }
            
            .validity-section {
                margin-bottom: 3px;
            }
            
            .validity-label {
                font-size: 11px;
                width: 70px;
                font-weight: bold;
            }
            
            .services-table {
                border: 1px solid #000;
                margin: 5px 0;
            }
            
            .services-table th,
            .services-table td {
                border: 1px solid #000;
                font-size: 10px;
                padding: 2px;
                height: 18px;
            }
            
            .personnel-section {
                margin: 5px 0;
            }
            
            .personnel-title {
                font-size: 12px;
                margin-bottom: 5px;
                font-weight: bold;
            }
            
            .personnel-list {
                display: flex;
                justify-content: space-between;
                width: 100%;
            }
            
            .personnel-column {
                width: 48%;
                display: flex;
                flex-direction: column;
                border: 1px solid transparent;
                padding: 3px;
            }
            
            .personnel-column:first-child {
                margin-right: 2%;
            }
            
            .personnel-row {
                display: flex;
                align-items: center;
                margin-bottom: 3px;
            }
            
            .personnel-number {
                font-size: 10px;
                width: 25px;
                font-weight: bold;
            }
            
            .personnel-name input {
                display: none !important;
            }
            
            .personnel-name .print-name {
                display: block !important;
                font-size: 10px;
                margin-left: 5px;
            }
            
            .signature-section {
                margin-top: 3px;
            }
            
            .signature-row {
                margin-top: 3px;
            }
            
            .signature-line {
                border-bottom: 1px solid #000;
                height: 30px;
                margin-bottom: 2px;
            }
            
            .signature-title {
                font-size: 9px;
                margin-bottom: 1px;
                font-weight: bold;
            }
            
            .signature-subtitle {
                font-size: 8px;
            }
            
            .note {
                font-size: 9px;
                margin-top: 2px;
            }
            
            .submit-section,
            .back-btn {
                display: none !important;
            }
            
            /* Hide image upload section when printing */
            .image-preview-item,
            .upload-btn,
            #file_count {
                display: none !important;
            }
            
            /* Force single page */
            * {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <!-- Header Section -->
        <div class="header">
            <div class="logo-section">
                <img src="img/logo.jpg" alt="Xentro Mall Logo" class="logo">
                <div>
                    <div class="mall-name">XENTRO MALL</div>
                    <div class="mall-location">Calapan, Mindoro</div>
                </div>
            </div>
        </div>
        
        <!-- Form Title -->
        <div class="form-title">WORK PERMIT</div>
        
        <!-- Success/Error Messages -->
        <?php if (!empty($_SESSION['work_permit_error_message'])): ?>
            <div class="message error">
                <?php echo htmlspecialchars($_SESSION['work_permit_error_message']); unset($_SESSION['work_permit_error_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['work_permit_success_message'])): ?>
            <div class="message success">
                <?php echo htmlspecialchars($_SESSION['work_permit_success_message']); unset($_SESSION['work_permit_success_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Form -->
        <form method="POST">
            <input type="hidden" name="submit_work_permit" value="1">
            
            <!-- Permit Details -->
            <div class="form-section">
                <div class="form-row">
                    <div class="form-label">Permit No:</div>
                    <input type="text" name="permit_no" class="form-input" required readonly>
                    <input type="hidden" name="permit_no_backup" id="permit_no_backup">
                </div>
                <div class="form-row">
                    <div class="form-label">Date Filed:</div>
                    <input type="date" name="date_filed" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-label">whose:</div>
                    <input type="text" name="tenant_name" class="form-input" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" readonly>
                </div>
                <div class="form-row">
                    <div class="form-label">Stall Number:</div>
                    <input type="text" name="stall_number" class="form-input" value="<?php echo htmlspecialchars($stallDetails['stall_number'] ?? ''); ?>" readonly>
                </div>
            </div>
            
            <!-- Contractor Type Selection -->
            <div class="form-section">
                <div class="form-label" style="margin-bottom: 10px;">CONTRACTOR TYPE:</div>
                <div class="form-row">
                    <div style="display: flex; align-items: center; margin-right: 30px;">
                        <input type="radio" id="contractor_inside" name="contractor_type" value="inside" checked style="margin-right: 8px;" onchange="toggleContractorFields()">
                        <label for="contractor_inside" style="font-weight: normal;">🔘 Inside (In-house Maintenance)</label>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <input type="radio" id="contractor_outside" name="contractor_type" value="outside" style="margin-right: 8px;" onchange="toggleContractorFields()">
                        <label for="contractor_outside" style="font-weight: normal;">🔘 Outside (External Contractor)</label>
                    </div>
                </div>
                
                <!-- Gate Pass Notice for Outside Contractors -->
                <div id="gate_pass_notice" style="display: none; margin-top: 15px; padding: 10px; background: #e8f4fd; border: 1px solid #b3d9ff; border-radius: 5px; font-size: 13px; color: #0066cc;">
                    <strong>⚠️ Gate Pass Information:</strong><br>
                    Please complete this work permit form accurately and in full. This approved work permit will serve as your official gate pass for the external contractor's entry. Present this document to the security personnel upon arrival.
                </div>
            </div>
            
            <!-- Contractor Details (for Outside Contractors) -->
            <div id="outside_contractor_details" style="display: none;">
            </div>
            
            <!-- Authorization Text -->
            <div class="authorization-text">
                This is to authorized the Tenant, Employees or contractor's personnel names are listed below, to undertake construction and/or other works.
            </div>
            
            <!-- Scope of Work -->
            <div class="form-section">
                <div class="form-label" style="margin-bottom: 5px;">SCOPE OF WORK/S:</div>
                <textarea name="scope_of_work" class="scope-box" required></textarea>
                
                <!-- Picture Upload Section -->
                <div style="margin-top: 10px;">
                    <div class="form-label" style="margin-bottom: 5px;">ATTACH IMAGES:</div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="file" id="scope_images" name="scope_images[]" accept="image/*" multiple style="display: none;">
                        <button type="button" onclick="document.getElementById('scope_images').click()" class="upload-btn">
                            📷 Choose Images
                        </button>
                        <span id="file_count" style="font-size: 12px; color: #666;">No files selected</span>
                    </div>
                    <div id="image_preview" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px;"></div>
                </div>
            </div>
            
            <!-- Permit Validity -->
            <div class="form-section">
                <div class="form-label" style="margin-bottom: 10px;">PERMIT IS VALID:</div>
                
                <div class="validity-section">
                    <div class="validity-label">FROM:</div>
                    <input type="date" name="permit_valid_from" id="permit_valid_from" class="form-input" style="width: 150px;" disabled>
                    <div class="validity-label" style="margin-left: 20px;">TO:</div>
                    <input type="date" name="permit_valid_to" id="permit_valid_to" class="form-input" style="width: 150px;" disabled>
                </div>
                
                <div class="validity-section">
                    <div class="validity-label">DATE:</div>
                    <input type="date" name="work_date" id="work_date" class="form-input" style="width: 150px;" disabled>
                    <div class="validity-label" style="margin-left: 20px;">TIME:</div>
                    <input type="time" name="time_from" id="time_from" class="form-input" style="width: 80px;" disabled>
                    <span style="margin: 0 10px;">to</span>
                    <input type="time" name="time_to" id="time_to" class="form-input" style="width: 80px;" disabled>
                </div>
                
                <div id="admin_note" style="margin-top: 10px; padding: 8px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 12px; color: #856404;">
                    <strong>⚠️ Note:</strong> Permit validity dates and times will be filled by the admin upon review and approval.
                </div>
            </div>
            
            <!-- Services Needed -->
            <div class="form-section">
                <div class="form-label" style="margin-bottom: 10px;">SERVICES NEEDED:</div>
                
                <table class="services-table">
                    <tr>
                        <th>Service</th>
                        <th>Needed</th>
                        <th>Rate/hr</th>
                        <th>With Charge</th>
                        <th>No Charge</th>
                    </tr>
                    <tr>
                        <td class="service-name">Security Posting</td>
                        <td><input type="checkbox" name="security_posting" value="1"></td>
                        <td><input type="number" name="security_rate" class="rate-input" step="0.01" id="security_rate" readonly></td>
                        <td><input type="radio" name="security_charge" value="With Charge"></td>
                        <td><input type="radio" name="security_charge" value="No Charge"></td>
                    </tr>
                    <tr>
                        <td class="service-name">Janitorial Deployment</td>
                        <td><input type="checkbox" name="janitorial_deployment" value="1"></td>
                        <td><input type="number" name="janitorial_rate" class="rate-input" step="0.01" id="janitorial_rate" readonly></td>
                        <td><input type="radio" name="janitorial_charge" value="With Charge"></td>
                        <td><input type="radio" name="janitorial_charge" value="No Charge"></td>
                    </tr>
                    <tr>
                        <td class="service-name">Maintenance</td>
                        <td><input type="checkbox" name="maintenance" value="1"></td>
                        <td><input type="number" name="maintenance_rate" class="rate-input" step="0.01" id="maintenance_rate" readonly></td>
                        <td><input type="radio" name="maintenance_charge" value="With Charge"></td>
                        <td><input type="radio" name="maintenance_charge" value="No Charge"></td>
                    </tr>
                </table>
                
                <div id="service_rate_note" style="margin-top: 10px; font-size: 12px; color: #666; font-style: italic;">
                    Note: Service rates are set at ₱175/hour for Inside contractors (managed by admin)
                </div>
            </div>
            
            <!-- Personnel List -->
            <div class="personnel-section">
                <div class="personnel-title">NAME OF PERSONNEL:</div>
                <div class="personnel-list">
                    <div class="personnel-column">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <div class="personnel-row">
                                <div class="personnel-number"><?php echo $i; ?>.</div>
                                <div class="personnel-name">
                                    <input type="text" name="personnel_<?php echo $i; ?>" placeholder="Enter name" oninput="updatePrintName(this)">
                                    <span class="print-name" style="display: none;"></span>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="personnel-column">
                        <?php for ($i = 11; $i <= 20; $i++): ?>
                            <div class="personnel-row">
                                <div class="personnel-number"><?php echo $i; ?>.</div>
                                <div class="personnel-name">
                                    <input type="text" name="personnel_<?php echo $i; ?>" placeholder="Enter name" oninput="updatePrintName(this)">
                                    <span class="print-name" style="display: none;"></span>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="note">(attached another sheet for additional names)</div>
            </div>
            
            <!-- Signature Section -->
            <div class="signature-section">
                <div class="signature-row">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-title">(TENANT/CONTRACTOR AUTHORIZED SIGNATORY)</div>
                        <div class="signature-subtitle">Name/Group name/Signature</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-title">Approved by:</div>
                        <div class="signature-subtitle">Engineering Department</div>
                        <div class="signature-subtitle">Signature over printed name</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-title">Checked by:</div>
                        <div class="signature-subtitle">Mall Admin Authorized Signatory</div>
                        <div class="signature-subtitle">Signature over printed name</div>
                    </div>
                </div>
                
                <div class="signature-row" style="margin-top: 20px;">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-title">QC Guard on duty</div>
                        <div class="signature-subtitle">Signature over printed name</div>
                    </div>
                </div>
            </div>
            
            <!-- Submit Section -->
            <div class="submit-section">
                <a href="tenant_dashboard.php" class="back-btn">Back to Dashboard</a>
                <button type="submit" class="submit-btn">SUBMIT WORK PERMIT</button>
            </div>
        </form>
    </div>

    
    <!-- View Work Permit Button - Outside Form -->
    <?php if (!empty($existingPermits)): ?>
    <div style="text-align: center; margin-top: 20px; margin-bottom: 30px;">
        <button type="button" onclick="openPermitsModal()" style="background: #0066cc; color: white; padding: 14px 35px; border: none; border-radius: 5px; cursor: pointer; font-size: 15px; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2); transition: background 0.3s;">
            📋 View Work Permit
        </button>
    </div>
    <?php endif; ?>
    
    <script>
        // Image upload functionality
        let uploadedImages = [];
        
        document.getElementById('scope_images').addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            const preview = document.getElementById('image_preview');
            const fileCount = document.getElementById('file_count');
            
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const imageId = 'img_' + Date.now() + '_' + index;
                        uploadedImages.push({
                            id: imageId,
                            file: file,
                            url: e.target.result
                        });
                        
                        const previewItem = document.createElement('div');
                        previewItem.className = 'image-preview-item';
                        previewItem.id = imageId;
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-btn" onclick="removeImage('${imageId}')">×</button>
                        `;
                        
                        preview.appendChild(previewItem);
                        updateFileCount();
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Clear the file input
            e.target.value = '';
        });
        
        function removeImage(imageId) {
            const index = uploadedImages.findIndex(img => img.id === imageId);
            if (index > -1) {
                uploadedImages.splice(index, 1);
            }
            
            const element = document.getElementById(imageId);
            if (element) {
                element.remove();
            }
            
            updateFileCount();
        }
        
        function updateFileCount() {
            const fileCount = document.getElementById('file_count');
            const count = uploadedImages.length;
            fileCount.textContent = count > 0 ? `${count} file(s) selected` : 'No files selected';
        }
        
        // Modify form submission to handle images
        document.querySelector('form').addEventListener('submit', function(e) {
            // Add image data to form as hidden inputs
            uploadedImages.forEach((image, index) => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `uploaded_image_${index}`;
                hiddenInput.value = image.url;
                this.appendChild(hiddenInput);
            });
        });
        
        // Auto-generate permit number immediately
        document.addEventListener('DOMContentLoaded', function() {
            const permitNoInput = document.querySelector('input[name="permit_no"]');
            const permitNoBackup = document.getElementById('permit_no_backup');
            
            if (permitNoInput && permitNoBackup) {
                const generatePermitNo = () => {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const time = String(now.getHours()).padStart(2, '0') + String(now.getMinutes()).padStart(2, '0') + String(now.getSeconds()).padStart(2, '0');
                    const permitNo = `WP-${year}${month}${day}-${time}`;
                    
                    permitNoInput.value = permitNo;
                    permitNoBackup.value = permitNo;
                    
                    console.log('Generated permit number:', permitNo);
                };
                
                // Generate immediately
                generatePermitNo();
                
                // Regenerate every minute to ensure uniqueness
                setInterval(generatePermitNo, 60000);
                
                // Update backup when main field changes
                permitNoInput.addEventListener('input', function() {
                    permitNoBackup.value = this.value;
                });
            }
        });
        
        // Keyboard shortcut for printing (Ctrl+P)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'red';
                    isValid = false;
                } else {
                    field.style.borderColor = '#999';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
        
        // Contractor type toggle function
        function toggleContractorFields() {
            const contractorType = document.querySelector('input[name="contractor_type"]:checked').value;
            const outsideDetails = document.getElementById('outside_contractor_details');
            const gatePassNotice = document.getElementById('gate_pass_notice');
            const serviceRateNote = document.getElementById('service_rate_note');
            const adminNote = document.getElementById('admin_note');
            const securityRate = document.getElementById('security_rate');
            const janitorialRate = document.getElementById('janitorial_rate');
            const maintenanceRate = document.getElementById('maintenance_rate');
            
            // Permit validity fields
            const permitValidFrom = document.getElementById('permit_valid_from');
            const permitValidTo = document.getElementById('permit_valid_to');
            const workDate = document.getElementById('work_date');
            const timeFrom = document.getElementById('time_from');
            const timeTo = document.getElementById('time_to');
            
            if (contractorType === 'outside') {
                // Show outside contractor fields
                outsideDetails.style.display = 'block';
                gatePassNotice.style.display = 'block';
                
                // Enable permit validity fields for outside contractors
                permitValidFrom.disabled = false;
                permitValidFrom.style.backgroundColor = '#fff';
                permitValidTo.disabled = false;
                permitValidTo.style.backgroundColor = '#fff';
                workDate.disabled = false;
                workDate.style.backgroundColor = '#fff';
                timeFrom.disabled = false;
                timeFrom.style.backgroundColor = '#fff';
                timeTo.disabled = false;
                timeTo.style.backgroundColor = '#fff';
                
                // Hide admin note for outside contractors
                adminNote.style.display = 'none';
                
                // Make service rates editable for outside contractors
                securityRate.readOnly = false;
                securityRate.style.backgroundColor = '#fff';
                janitorialRate.readOnly = false;
                janitorialRate.style.backgroundColor = '#fff';
                maintenanceRate.readOnly = false;
                maintenanceRate.style.backgroundColor = '#fff';
                
                serviceRateNote.textContent = 'Note: Service rates are editable for Outside contractors';
            } else {
                // Hide outside contractor fields
                outsideDetails.style.display = 'none';
                gatePassNotice.style.display = 'none';
                
                // Disable permit validity fields for inside contractors (admin-only)
                permitValidFrom.disabled = true;
                permitValidFrom.style.backgroundColor = '#f5f5f5';
                permitValidTo.disabled = true;
                permitValidTo.style.backgroundColor = '#f5f5f5';
                workDate.disabled = true;
                workDate.style.backgroundColor = '#f5f5f5';
                timeFrom.disabled = true;
                timeFrom.style.backgroundColor = '#f5f5f5';
                timeTo.disabled = true;
                timeTo.style.backgroundColor = '#f5f5f5';
                
                // Show admin note for inside contractors
                adminNote.style.display = 'block';
                
                // Set fixed rates and make read-only for inside contractors
                securityRate.value = '175';
                securityRate.readOnly = true;
                securityRate.style.backgroundColor = '#f5f5f5';
                
                janitorialRate.value = '175';
                janitorialRate.readOnly = true;
                janitorialRate.style.backgroundColor = '#f5f5f5';
                
                maintenanceRate.value = '175';
                maintenanceRate.readOnly = true;
                maintenanceRate.style.backgroundColor = '#f5f5f5';
                
                serviceRateNote.textContent = 'Note: Service rates are set at ₱175/hour for Inside contractors (managed by admin)';
            }
        }
        
        // Initialize contractor fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleContractorFields(); // Set initial state
        });

        function updatePrintName(input) {
            const printSpan = input.nextElementSibling;
            printSpan.textContent = input.value;
        }

        // Handle print mode
        window.addEventListener('beforeprint', function() {
            // Update all print spans with current input values
            const inputs = document.querySelectorAll('.personnel-name input');
            inputs.forEach(input => {
                const printSpan = input.nextElementSibling;
                printSpan.textContent = input.value;
            });
        });

        // Modal functions for View Work Permit
        function openPermitsModal() {
            document.getElementById('permitsModal').style.display = 'block';
        }

        function closePermitsModal() {
            document.getElementById('permitsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('permitsModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <!-- Modal for Work Permits -->
    <div id="permitsModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 1000px; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <!-- Modal Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #0066cc; padding-bottom: 15px;">
                <h2 style="margin: 0; color: #0066cc;">📋 Your Work Permits</h2>
                <button onclick="closePermitsModal()" style="background: #dc3545; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold;">✕ Close</button>
            </div>

            <!-- Modal Content -->
            <table style="width: 100%; border-collapse: collapse; background: white;">
                <thead>
                    <tr style="background: #0066cc; color: white;">
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Permit No</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Date Filed</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Status</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Scope</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Valid From</th>
                        <th style="padding: 12px; text-align: left; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Valid To</th>
                        <th style="padding: 12px; text-align: center; font-size: 13px; font-weight: bold; border: 1px solid #004499;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingPermits as $permit): ?>
                    <tr style="border: 1px solid #ddd; background: <?php echo $permit['status'] === 'approved' ? '#f0f9f6' : '#fafafa'; ?>;">
                        <td style="padding: 12px; font-size: 13px; border: 1px solid #ddd;"><strong><?php echo htmlspecialchars($permit['permit_no']); ?></strong></td>
                        <td style="padding: 12px; font-size: 13px; border: 1px solid #ddd;"><?php echo $permit['date_filed'] ? date('M d, Y', strtotime($permit['date_filed'])) : '-'; ?></td>
                        <td style="padding: 12px; font-size: 13px; border: 1px solid #ddd;">
                            <span style="padding: 5px 10px; border-radius: 4px; font-weight: bold; font-size: 12px;
                                <?php 
                                if ($permit['status'] === 'approved') echo 'background: #d4edda; color: #155724;';
                                elseif ($permit['status'] === 'pending') echo 'background: #fff3cd; color: #856404;';
                                elseif ($permit['status'] === 'rejected') echo 'background: #f8d7da; color: #721c24;';
                                else echo 'background: #d1ecf1; color: #0c5460;';
                                ?>
                            ">
                                <?php echo strtoupper($permit['status']); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; font-size: 13px; border: 1px solid #ddd;"><?php echo htmlspecialchars(substr($permit['scope_of_work'] ?? '', 0, 25)) . (strlen($permit['scope_of_work'] ?? '') > 25 ? '...' : ''); ?></td>
                        <td style="padding: 12px; font-size: 13px; border: 1px solid #ddd;"><?php echo $permit['permit_valid_from'] ? date('M d, Y', strtotime($permit['permit_valid_from'])) : '-'; ?></td>
                        <td style="padding: 12px; font-size: 13px; border: 1px solid #ddd;"><?php echo $permit['permit_valid_to'] ? date('M d, Y', strtotime($permit['permit_valid_to'])) : '-'; ?></td>
                        <td style="padding: 12px; text-align: center; border: 1px solid #ddd;">
                            <a href="view_work_permit.php?permit_no=<?php echo urlencode($permit['permit_no']); ?>" onclick="closePermitsModal()" style="background: #28a745; color: white; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; text-decoration: none; display: inline-block; transition: background 0.3s;">📄 View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
