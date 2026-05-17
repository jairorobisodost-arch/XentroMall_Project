<?php
session_start();
require 'config.php';
require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Check if user is an existing tenant with approved payment
$stmtCheckTenant = $pdo->prepare("
    SELECT COUNT(*) as payment_count
    FROM payments p
    WHERE p.user_id = ? AND p.status = 'approved'
");
$stmtCheckTenant->execute([$userId]);
$paymentStatus = $stmtCheckTenant->fetch(PDO::FETCH_ASSOC);
$isExistingTenant = ($paymentStatus['payment_count'] > 0);

if (!$isExistingTenant) {
    $_SESSION['error_message'] = "Only existing tenants with approved payments can submit renewal applications.";
    header('Location: tenant_dashboard.php');
    exit;
}

// Get tenant details
$stmtTenant = $pdo->prepare("
    SELECT td.id, td.tradename, td.stall_id, td.email, td.contact_person, td.mobile
    FROM tenant_details td
    WHERE td.user_id = ? AND td.status = 'approved'
    LIMIT 1
");
$stmtTenant->execute([$userId]);
$tenantData = $stmtTenant->fetch(PDO::FETCH_ASSOC);

if (!$tenantData) {
    $_SESSION['error_message'] = "No approved tenant record found.";
    header('Location: tenant_dashboard.php');
    exit;
}

// Get tenant ID from tenants table
$stmtUserEmail = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUserEmail->execute([$userId]);
$userEmail = $stmtUserEmail->fetchColumn();

$stmtTenantId = $pdo->prepare("SELECT id as tenant_id FROM tenants WHERE email = ?");
$stmtTenantId->execute([$userEmail]);
$tenantIdData = $stmtTenantId->fetch();
$tenantId = $tenantIdData['tenant_id'] ?? null;

// Get all tenant stalls for individual renewal selection
$tenantStalls = [];
$selectedStallId = $_GET['stall_id'] ?? null;
$autoSelect = $_GET['auto_select'] ?? false;

try {
    $stmtTenantStalls = $pdo->prepare("
        SELECT td.id as tenant_detail_id, td.tradename, td.status as tenant_status,
               s.id as stall_id, s.stall_number, s.description, s.monthly_rate, s.floor_area, s.image_path,
               tc.lease_start_date, tc.lease_expiration_date, tc.contract_status
        FROM tenant_details td
        LEFT JOIN stalls s ON td.stall_id = s.id
        LEFT JOIN tenant_lease_dates tld ON td.id = tld.tenant_detail_id
        LEFT JOIN tenant_contracts tc ON td.id = tc.tenant_detail_id
        WHERE td.user_id = ? AND td.status = 'approved'
        ORDER BY s.stall_number
    ");
    $stmtTenantStalls->execute([$userId]);
    $tenantStalls = $stmtTenantStalls->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $tenantStalls = [];
}

// If auto_select is true and stall_id is provided, find the specific stall
$selectedStall = null;
if ($selectedStallId && $autoSelect) {
    foreach ($tenantStalls as $stall) {
        if ($stall['stall_id'] == $selectedStallId) {
            $selectedStall = $stall;
            break;
        }
    }
}

// Get contract details for selected stall or default
$contractData = null;
if ($selectedStall) {
    // Use selected stall's contract data
    $contractData = [
        'lease_start_date' => $selectedStall['lease_start_date'],
        'lease_expiration_date' => $selectedStall['lease_expiration_date'],
        'contract_status' => $selectedStall['contract_status']
    ];
} elseif ($tenantId) {
    $stmtContract = $pdo->prepare("SELECT * FROM tenant_lease_dates WHERE tenant_id = ?");
    $stmtContract->execute([$tenantId]);
    $contractData = $stmtContract->fetch(PDO::FETCH_ASSOC);
}

// Check for existing pending renewal for specific stall using unified system
$pendingRenewal = null;
if ($userId && $selectedStallId) {
    $stmtPending = $pdo->prepare("SELECT * FROM unified_renewal_requests WHERE user_id = ? AND stall_id = ? AND status IN ('pending', 'approved', 'payment_pending') ORDER BY submitted_at DESC LIMIT 1");
    $stmtPending->execute([$userId, $selectedStallId]);
    $pendingRenewal = $stmtPending->fetch(PDO::FETCH_ASSOC);
} elseif ($userId) {
    $stmtPending = $pdo->prepare("SELECT * FROM unified_renewal_requests WHERE user_id = ? AND status IN ('pending', 'approved', 'payment_pending') ORDER BY submitted_at DESC LIMIT 1");
    $stmtPending->execute([$userId]);
    $pendingRenewal = $stmtPending->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_renewal_application'])) {
    try {
        // Check if there's already a pending renewal for this stall
        $selectedStallIdFromForm = $_POST['stall_id'] ?? $selectedStallId;
        $stmtCheckExisting = $pdo->prepare("
            SELECT COUNT(*) FROM unified_renewal_requests 
            WHERE user_id = ? AND stall_id = ? AND status IN ('pending', 'approved', 'payment_pending')
        ");
        $stmtCheckExisting->execute([$userId, $selectedStallIdFromForm]);
        $existingCount = $stmtCheckExisting->fetchColumn();
        
        if ($existingCount > 0) {
            $_SESSION['error_message'] = "You already have a pending renewal application for this stall. Please wait for the current application to be processed.";
            header('Location: tenant_dashboard.php?page=renewal');
            exit;
        }
        
        $uploadDir = 'uploads/renewal_applications/' . $userId . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $files = [
            'letter_of_intent',
            'business_profile',
            'business_registration',
            'valid_id',
            'bir_registration',
            'extended_bir_registration',
            'financial_statement'
        ];

        $uploadedFiles = [];

        foreach ($files as $file) {
            if (isset($_FILES[$file]) && $_FILES[$file]['error'] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES[$file]['tmp_name'];
                $fileName = basename($_FILES[$file]['name']);
                $targetFile = $uploadDir . uniqid() . '_' . $fileName;
                if (move_uploaded_file($tmpName, $targetFile)) {
                    $uploadedFiles[$file] = $targetFile;
                } else {
                    $uploadedFiles[$file] = null;
                }
            } else {
                $uploadedFiles[$file] = null;
            }
        }

        // Get selected stall from form or URL parameter
        $selectedStallIdFromForm = $_POST['stall_id'] ?? $selectedStallId;
        $selectedTenantDetailId = $_POST['tenant_detail_id'] ?? null;
        
        // Find the selected stall details
        $selectedStallData = null;
        if ($selectedStallIdFromForm) {
            foreach ($tenantStalls as $stall) {
                if ($stall['stall_id'] == $selectedStallIdFromForm) {
                    $selectedStallData = $stall;
                    break;
                }
            }
        }
        
        // Use selected stall data or fall back to default
        $finalTenantDetailId = $selectedTenantDetailId ?? $selectedStallData['tenant_detail_id'] ?? $tenantData['id'];
        $finalStallId = $selectedStallIdFromForm ?? $selectedStallData['stall_id'] ?? $tenantData['stall_id'];
        $finalTradename = $selectedStallData['tradename'] ?? $tenantData['tradename'];

        // Insert renewal application using unified system
        $stmt = $pdo->prepare("
            INSERT INTO unified_renewal_requests (
                user_id, tenant_id, tenant_detail_id, tradename, stall_id,
                letter_of_intent, business_profile, business_registration, valid_id,
                bir_registration, extended_bir_registration, financial_statement,
                request_type, status, submitted_at, total_amount
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'renewal', 'pending', NOW(), ?)
        ");

        // Calculate total amount (monthly rate only for renewals)
        $monthlyRate = $selectedStallData['monthly_rate'] ?? $tenantStalls[0]['monthly_rate'] ?? 0;
        $totalAmount = $monthlyRate; // Renewals are monthly only

        $stmt->execute([
            $userId,
            $tenantId,
            $finalTenantDetailId,
            $finalTradename,
            $finalStallId,
            $uploadedFiles['letter_of_intent'],
            $uploadedFiles['business_profile'],
            $uploadedFiles['business_registration'],
            $uploadedFiles['valid_id'],
            $uploadedFiles['bir_registration'],
            $uploadedFiles['extended_bir_registration'],
            $uploadedFiles['financial_statement'],
            $totalAmount
        ]);

        $applicationId = $pdo->lastInsertId();

        // Count uploaded documents
        $uploadedCount = count(array_filter($uploadedFiles));

        // Send email notification to admin
        $adminEmail = 'mallxentro5@gmail.com';
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'mallxentro5@gmail.com';
            $mail->Password = 'iwld cjlr kmcy bxab';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('mallxentro5@gmail.com', 'XentroMall System');
            $mail->addAddress($adminEmail, 'XentroMall Admin');

            $mail->isHTML(true);
            $mail->Subject = "🔄 Tenant Renewal Application - " . $tenantData['tradename'];
            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #ffffff; }
                    .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 40px 30px; text-align: center; border-radius: 12px 12px 0 0; }
                    .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                    .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
                    .content { background: #f9fafb; padding: 35px 30px; border-radius: 0 0 12px 12px; }
                    .info-box { background: white; padding: 25px; margin: 20px 0; border-radius: 10px; border-left: 5px solid #f59e0b; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
                    .info-box h3 { margin-top: 0; color: #f59e0b; font-size: 18px; }
                    .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
                    .info-row:last-child { border-bottom: none; }
                    .info-label { font-weight: bold; color: #374151; width: 180px; }
                    .info-value { color: #1f2937; flex: 1; }
                    .badge { display: inline-block; background: #f59e0b; color: white; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; color: #6b7280; font-size: 12px; }
                    .action-button { display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                    .doc-list { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 15px 0; }
                    .doc-item { padding: 8px 0; color: #1f2937; }
                    .doc-item::before { content: '✓ '; color: #10b981; font-weight: bold; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>🔄 Tenant Renewal Application</h1>
                        <p>XentroMall Management System</p>
                    </div>
                    <div class='content'>
                        <p style='font-size: 16px; color: #1f2937; margin-bottom: 20px;'>
                            <strong>Good news!</strong> An existing tenant has submitted a renewal application and is waiting for your review.
                        </p>

                        <div class='info-box'>
                            <h3>📋 Application Details</h3>
                            <div class='info-row'>
                                <div class='info-label'>Application ID:</div>
                                <div class='info-value'>#" . $applicationId . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Application Type:</div>
                                <div class='info-value'><span class='badge'>🔄 Renewal</span></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Documents Uploaded:</div>
                                <div class='info-value'><strong>" . $uploadedCount . " of 7</strong></div>
                            </div>
                        </div>

                        <div class='info-box'>
                            <h3>🏢 Tenant Information</h3>
                            <div class='info-row'>
                                <div class='info-label'>Trade Name:</div>
                                <div class='info-value'><strong>" . htmlspecialchars($tenantData['tradename']) . "</strong></div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Contact Person:</div>
                                <div class='info-value'>" . htmlspecialchars($tenantData['contact_person'] ?? 'N/A') . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Email:</div>
                                <div class='info-value'>" . htmlspecialchars($tenantData['email'] ?? 'N/A') . "</div>
                            </div>
                            <div class='info-row'>
                                <div class='info-label'>Mobile:</div>
                                <div class='info-value'>" . htmlspecialchars($tenantData['mobile'] ?? 'N/A') . "</div>
                            </div>
                        </div>

                        <div class='info-box'>
                            <h3>📄 Submitted Documents</h3>
                            <div class='doc-list'>" .
                                (isset($uploadedFiles['letter_of_intent']) && $uploadedFiles['letter_of_intent'] ? "<div class='doc-item'>Letter of Intent</div>" : "") .
                                (isset($uploadedFiles['business_profile']) && $uploadedFiles['business_profile'] ? "<div class='doc-item'>Business Profile</div>" : "") .
                                (isset($uploadedFiles['business_registration']) && $uploadedFiles['business_registration'] ? "<div class='doc-item'>Business Registration</div>" : "") .
                                (isset($uploadedFiles['valid_id']) && $uploadedFiles['valid_id'] ? "<div class='doc-item'>Valid ID</div>" : "") .
                                (isset($uploadedFiles['bir_registration']) && $uploadedFiles['bir_registration'] ? "<div class='doc-item'>BIR Registration</div>" : "") .
                                (isset($uploadedFiles['extended_bir_registration']) && $uploadedFiles['extended_bir_registration'] ? "<div class='doc-item'>Extended BIR Registration</div>" : "") .
                                (isset($uploadedFiles['financial_statement']) && $uploadedFiles['financial_statement'] ? "<div class='doc-item'>Financial Statement</div>" : "") .
                            "</div>
                        </div>

                        <div style='text-align: center; margin: 30px 0;'>
                            <p style='font-size: 15px; color: #374151; margin-bottom: 15px;'>
                                Please review the submitted documents and take appropriate action.
                            </p>
                            <a href='http://localhost/Jai/XentroMall/admin_renewal_management.php' class='action-button'>
                                Review Renewal Application →
                            </a>
                        </div>

                        <div class='footer'>
                            <p style='margin: 5px 0;'>This is an automated notification from XentroMall Management System</p>
                            <p style='margin: 5px 0;'>© " . date('Y') . " XentroMall. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>";

            $mail->send();
        } catch (Exception $e) {
            error_log("Email failed: " . $e->getMessage());
        }

        $_SESSION['success_message'] = "Renewal application submitted successfully! Waiting for admin approval.";
        header('Location: tenant_dashboard.php?page=renewal');
        exit;

    } catch (PDOException $e) {
        error_log("Database insert error: " . $e->getMessage());
        $_SESSION['error_message'] = "There was an error submitting your renewal application. Please try again later.";
        header('Location: renewal_application_form.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Renewal Application Submission</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .form-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            max-width: 700px;
            margin: 0 auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .form-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
        }
        .form-header h1 {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin: 0;
        }
        .form-header p {
            color: #6b7280;
            margin-top: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        input[type="file"]:hover {
            border-color: #667eea;
            background-color: #f9fafb;
        }
        .file-info {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .tenant-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }
        .tenant-info p {
            margin: 5px 0;
            font-size: 14px;
        }
        .stall-selection-section {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .stall-selection-section h3 {
            color: #92400e;
            margin: 0 0 10px 0;
            font-size: 18px;
            font-weight: 600;
        }
        .section-description {
            color: #78350f;
            margin: 0 0 15px 0;
            font-size: 14px;
        }
        .selected-stall-info {
            background: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 6px;
            padding: 15px;
        }
        .stall-highlight {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #065f46;
        }
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-warning {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
        }
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }
        .tenant-info {
            background: #f0f9ff;
            border-left: 4px solid #0284c7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .tenant-info p {
            margin: 5px 0;
            color: #0c4a6e;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="tenant_dashboard.php?page=renewal" class="back-link">
            <i class="fas fa-arrow-left mr-2"></i> Back to Renewal
        </a>

        <div class="form-header">
            <h1><i class="fas fa-sync-alt mr-2"></i> Renewal Application</h1>
            <p>Submit your renewal documents for admin review</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <?php if ($pendingRenewal): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                <span>You already have a pending renewal application. Please wait for admin approval.</span>
            </div>
        <?php else: ?>

        <div class="tenant-info">
            <p><strong>Trade Name:</strong> <?php echo htmlspecialchars($tenantData['tradename']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($tenantData['email'] ?? 'N/A'); ?></p>
        </div>

        <!-- Stall Selection Section -->
        <div class="stall-selection-section">
            <h3><i class="fas fa-store mr-2"></i> Select Stall to Renew</h3>
            <p class="section-description">Choose which stall you want to renew. Each stall requires a separate renewal application.</p>
            
            <?php if ($autoSelect && $selectedStall): ?>
                <div class="selected-stall-info">
                    <div class="stall-highlight">
                        <i class="fas fa-check-circle text-green-500"></i>
                        <span><strong>Auto-selected:</strong> Stall <?php echo htmlspecialchars($selectedStall['stall_number']); ?> - <?php echo htmlspecialchars($selectedStall['tradename']); ?></span>
                    </div>
                    <input type="hidden" name="stall_id" value="<?php echo $selectedStall['stall_id']; ?>">
                    <input type="hidden" name="tenant_detail_id" value="<?php echo $selectedStall['tenant_detail_id']; ?>">
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="stall_id">
                        <i class="fas fa-store mr-2"></i> Select Stall to Renew *
                    </label>
                    <select name="stall_id" id="stall_id" required>
                        <option value="">-- Select a stall to renew --</option>
                        <?php foreach ($tenantStalls as $stall): ?>
                            <option value="<?php echo $stall['stall_id']; ?>" 
                                    data-tenant-detail-id="<?php echo $stall['tenant_detail_id']; ?>"
                                    data-tradename="<?php echo htmlspecialchars($stall['tradename']); ?>"
                                    <?php echo ($selectedStallId && $stall['stall_id'] == $selectedStallId) ? 'selected' : ''; ?>>
                                Stall <?php echo htmlspecialchars($stall['stall_number']); ?> - <?php echo htmlspecialchars($stall['tradename']); ?>
                                <?php if ($stall['lease_expiration_date']): ?>
                                    (Expires: <?php echo date('M d, Y', strtotime($stall['lease_expiration_date'])); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="file-info">Select the stall you want to renew. Each stall requires individual renewal.</p>
                </div>
            <?php endif; ?>
        </div>

        <form action="renewal_application_form.php<?php echo $selectedStallId ? '?stall_id=' . $selectedStallId : ''; ?>" method="POST" enctype="multipart/form-data">
            <?php if (!$autoSelect): ?>
                <input type="hidden" name="tenant_detail_id" id="tenant_detail_id">
            <?php endif; ?>
            <div class="form-group">
                <label for="letter_of_intent">
                    <i class="fas fa-file-alt mr-2"></i> Letter of Intent
                </label>
                <input type="file" name="letter_of_intent" id="letter_of_intent" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                <p class="file-info">Upload your letter of intent (PDF, DOC, DOCX, JPG, PNG)</p>
            </div>

            <div class="form-group">
                <label for="business_profile">
                    <i class="fas fa-briefcase mr-2"></i> Business Profile
                </label>
                <input type="file" name="business_profile" id="business_profile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                <p class="file-info">Upload your business profile (PDF, DOC, DOCX, JPG, PNG)</p>
            </div>

            <div class="form-group">
                <label for="business_registration">
                    <i class="fas fa-certificate mr-2"></i> Business Registration
                </label>
                <input type="file" name="business_registration" id="business_registration" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                <p class="file-info">Upload your business registration (PDF, DOC, DOCX, JPG, PNG)</p>
            </div>

            <div class="form-group">
                <label for="valid_id">
                    <i class="fas fa-id-card mr-2"></i> Valid ID
                </label>
                <input type="file" name="valid_id" id="valid_id" accept=".jpg,.jpeg,.png,.pdf" required>
                <p class="file-info">Upload a valid ID (JPG, PNG, PDF)</p>
            </div>

            <div class="form-group">
                <label for="bir_registration">
                    <i class="fas fa-file-invoice mr-2"></i> BIR Registration
                </label>
                <input type="file" name="bir_registration" id="bir_registration" accept=".pdf,.jpg,.jpeg,.png" required>
                <p class="file-info">Upload your BIR registration (PDF, JPG, PNG)</p>
            </div>

            <div class="form-group">
                <label for="extended_bir_registration">
                    <i class="fas fa-file-pdf mr-2"></i> Extended BIR Registration
                </label>
                <input type="file" name="extended_bir_registration" id="extended_bir_registration" accept=".pdf,.jpg,.jpeg,.png" required>
                <p class="file-info">Upload your extended BIR registration (PDF, JPG, PNG)</p>
            </div>

            <div class="form-group">
                <label for="financial_statement">
                    <i class="fas fa-chart-line mr-2"></i> Financial Statement
                </label>
                <input type="file" name="financial_statement" id="financial_statement" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                <p class="file-info">Upload your financial statement (PDF, DOC, DOCX, JPG, PNG)</p>
            </div>

            <button type="submit" name="submit_renewal_application" class="submit-btn">
                <i class="fas fa-paper-plane mr-2"></i> Submit Renewal Application
            </button>
        </form>

        <?php endif; ?>
    </div>

    <script>
        // Handle stall selection change
        document.addEventListener('DOMContentLoaded', function() {
            const stallSelect = document.getElementById('stall_id');
            const tenantDetailInput = document.getElementById('tenant_detail_id');
            
            if (stallSelect && tenantDetailInput) {
                stallSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const tenantDetailId = selectedOption.getAttribute('data-tenant-detail-id');
                    
                    if (tenantDetailId) {
                        tenantDetailInput.value = tenantDetailId;
                    }
                });
                
                // Set initial value if option is pre-selected
                if (stallSelect.value) {
                    const selectedOption = stallSelect.options[stallSelect.selectedIndex];
                    const tenantDetailId = selectedOption.getAttribute('data-tenant-detail-id');
                    
                    if (tenantDetailId) {
                        tenantDetailInput.value = tenantDetailId;
                    }
                }
            }
        });
    </script>
</body>
</html>
