<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get permit number from URL
$permitNo = $_GET['permit_no'] ?? null;

if (!$permitNo) {
    die("No permit number provided.");
}

// Fetch the work permit details
try {
    $stmt = $pdo->prepare("SELECT * FROM work_permits WHERE permit_no = ?");
    $stmt->execute([$permitNo]);
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$permit) {
        die("Permit not found.");
    }
}
catch (PDOException $e) {
    die("Error fetching permit: " . $e->getMessage());
}

// Decode JSON fields
$uploadedImages = json_decode($permit['uploaded_images'] ?? '[]', true) ?? [];

// Parse personnel
$personnel = explode(',', $permit['personnel'] ?? '');
$personnel = array_filter(array_map('trim', $personnel));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Permit - <?php echo htmlspecialchars($permit['permit_no']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #333;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .logo {
            width: 50px;
            height: 50px;
            margin-right: 15px;
        }
        
        .mall-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .mall-location {
            font-size: 14px;
            color: #666;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-transform: uppercase;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }

        .status-banner {
            text-align: center;
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #17a2b8;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-row {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .form-label {
            width: 150px;
            font-weight: bold;
            font-size: 14px;
        }
        
        .form-display {
            flex: 1;
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ccc;
            background: #f9f9f9;
            border-radius: 3px;
            min-height: 28px;
            display: flex;
            align-items: center;
        }
        
        .authorization-text {
            font-size: 14px;
            line-height: 1.5;
            margin: 15px 0;
            text-align: justify;
        }
        
        .scope-box {
            width: 100%;
            min-height: 80px;
            border: 1px solid #ccc;
            padding: 8px;
            font-size: 14px;
            background: #f9f9f9;
            border-radius: 3px;
            word-wrap: break-word;
        }
        
        .validity-section {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .validity-label {
            width: 120px;
            font-weight: bold;
            font-size: 14px;
        }

        .validity-display {
            flex: 1;
            margin-right: 20px;
            display: flex;
            gap: 20px;
        }

        .validity-item {
            flex: 1;
        }

        .validity-item label {
            display: block;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
        }

        .validity-item input {
            width: 100%;
            padding: 6px;
            border: 1px solid #ccc;
            background: #f9f9f9;
            border-radius: 3px;
        }
        
        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .services-table th,
        .services-table td {
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
            font-size: 12px;
        }
        
        .services-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .service-name {
            text-align: left;
            font-weight: bold;
        }

        .checkbox-display {
            width: 20px;
            height: 20px;
            border: 2px solid #333;
            display: inline-block;
            text-align: center;
            line-height: 18px;
            font-weight: bold;
        }

        .checkbox-checked {
            background: #333;
            color: white;
        }
        
        .personnel-section {
            margin: 20px 0;
        }
        
        .personnel-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
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
            margin-bottom: 5px;
            align-items: center;
        }
        
        .personnel-number {
            width: 30px;
            font-weight: bold;
            font-size: 12px;
        }
        
        .personnel-display {
            flex: 1;
            padding: 6px;
            border: 1px solid #999;
            font-size: 12px;
            background: #f9f9f9;
            border-radius: 2px;
            min-height: 20px;
        }

        @media print {
            .personnel-display {
                border: none;
                background: transparent;
                padding: 4px 0;
                min-height: auto;
            }
        }
        
        .signature-section {
            margin-top: 30px;
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
            border-bottom: 2px solid #333;
            height: 60px;
            margin-bottom: 5px;
        }
        
        .signature-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 3px;
        }
        
        .signature-subtitle {
            font-size: 11px;
            color: #666;
        }

        .signature-display {
            border: 1px solid #ccc;
            height: 60px;
            margin-bottom: 5px;
            background: #f9f9f9;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .signature-display img {
            max-width: 90%;
            max-height: 55px;
        }

        .signature-display .no-signature {
            color: #999;
            font-style: italic;
            font-size: 12px;
        }
        
        .note {
            font-size: 11px;
            font-style: italic;
            color: #666;
            margin-top: 10px;
        }

        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }

        .btn {
            background: #333;
            color: white;
            padding: 10px 30px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin: 0 10px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #555;
        }

        .btn-back {
            background: #666;
        }

        .btn-back:hover {
            background: #888;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .form-container {
                box-shadow: none;
                border: none;
                padding: 10px;
                max-width: 100%;
            }

            .action-buttons {
                display: none;
            }

            .status-banner {
                display: none !important;
            }

            .header {
                margin-bottom: 10px;
            }

            .form-title {
                margin: 5px 0;
                font-size: 20px;
                padding-bottom: 5px;
            }

            .form-section {
                margin-bottom: 10px;
            }

            .form-row {
                margin-bottom: 8px;
            }

            .form-label {
                width: 120px;
                font-size: 12px;
            }

            .form-display {
                font-size: 12px;
                padding: 4px;
                min-height: 22px;
            }

            .validity-section {
                margin-bottom: 8px;
            }

            .services-table th,
            .services-table td {
                padding: 4px;
                font-size: 11px;
            }

            .personnel-section {
                margin: 10px 0;
            }

            .personnel-title {
                margin-bottom: 5px;
                font-size: 12px;
            }

            .personnel-list {
                gap: 5px;
            }

            .personnel-row {
                margin-bottom: 2px;
            }

            .personnel-display {
                border: none;
                background: transparent;
                padding: 2px 0;
                min-height: auto;
                font-size: 11px;
            }

            .signature-section {
                margin-top: 10px;
            }

            .signature-row {
                margin-top: 15px;
            }

            .signature-box {
                width: 30%;
            }

            .signature-display {
                height: 40px;
                margin-bottom: 2px;
            }

            .signature-title {
                font-size: 11px;
                margin-bottom: 2px;
            }

            .signature-subtitle {
                font-size: 10px;
            }

            /* Hide empty personnel rows on print */
            .personnel-row .personnel-display:empty {
                display: none;
            }

            .personnel-row:has(.personnel-display:empty) {
                display: none;
            }

            @page {
                size: legal;
                margin: 5mm;
            }
            
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

        <!-- Status Banner -->
        <div class="status-banner status-<?php echo strtolower($permit['status']); ?>">
            STATUS: <?php echo strtoupper($permit['status']); ?>
        </div>
        
        <!-- Permit Details -->
        <div class="form-section">
            <div class="form-row">
                <div class="form-label">Permit No:</div>
                <div class="form-display"><?php echo htmlspecialchars($permit['permit_no']); ?></div>
            </div>
            <div class="form-row">
                <div class="form-label">Date Filed:</div>
                <div class="form-display"><?php echo $permit['date_filed'] ? date('m/d/Y', strtotime($permit['date_filed'])) : '-'; ?></div>
            </div>
            <div class="form-row">
                <div class="form-label">whose:</div>
                <div class="form-display"><?php echo htmlspecialchars($permit['tenant_name'] ?? '-'); ?></div>
            </div>
            <div class="form-row">
                <div class="form-label">Stall Number:</div>
                <div class="form-display"><?php echo htmlspecialchars($permit['stall_number'] ?? '-'); ?></div>
            </div>
        </div>

        <!-- Contractor Type -->
        <div class="form-section">
            <div class="form-label" style="margin-bottom: 10px;">CONTRACTOR TYPE:</div>
            <div class="form-row">
                <div style="display: flex; align-items: center; margin-right: 30px;">
                    <span style="display: flex; align-items: center;">
                        <span class="checkbox-display <?php echo $permit['contractor_type'] === 'inside' ? 'checkbox-checked' : ''; ?>">
                            <?php echo $permit['contractor_type'] === 'inside' ? '✓' : ''; ?>
                        </span>
                        <label style="font-weight: normal; margin-left: 8px;">🔘 Inside (In-house Maintenance)</label>
                    </span>
                </div>
                <div style="display: flex; align-items: center;">
                    <span style="display: flex; align-items: center;">
                        <span class="checkbox-display <?php echo $permit['contractor_type'] === 'outside' ? 'checkbox-checked' : ''; ?>">
                            <?php echo $permit['contractor_type'] === 'outside' ? '✓' : ''; ?>
                        </span>
                        <label style="font-weight: normal; margin-left: 8px;">🔘 Outside (External Contractor)</label>
                    </span>
                </div>
            </div>
        </div>

        <!-- Authorization Text -->
        <div class="authorization-text">
            This is to authorized the Tenant, Employees or contractor's personnel names are listed below, to undertake construction and/or other works.
        </div>
        
        <!-- Scope of Work -->
        <div class="form-section">
            <div class="form-label" style="margin-bottom: 5px;">SCOPE OF WORK/S:</div>
            <div class="scope-box"><?php echo htmlspecialchars($permit['scope_of_work'] ?? ''); ?></div>
        </div>

        <!-- Permit Validity -->
        <div class="form-section">
            <div class="validity-section">
                <div class="validity-label">VALID FROM & TO:</div>
                <div class="validity-display">
                    <div class="validity-item">
                        <label>From</label>
                        <input type="text" readonly value="<?php echo $permit['permit_valid_from'] ? date('m/d/Y', strtotime($permit['permit_valid_from'])) : ''; ?>">
                    </div>
                    <div class="validity-item">
                        <label>To</label>
                        <input type="text" readonly value="<?php echo $permit['permit_valid_to'] ? date('m/d/Y', strtotime($permit['permit_valid_to'])) : ''; ?>">
                    </div>
                </div>
            </div>

            <div class="validity-section">
                <div class="validity-label">WORK TIME:</div>
                <div class="validity-display">
                    <div class="validity-item">
                        <label>From</label>
                        <input type="text" readonly value="<?php echo htmlspecialchars($permit['time_from'] ?? ''); ?>">
                    </div>
                    <div class="validity-item">
                        <label>To</label>
                        <input type="text" readonly value="<?php echo htmlspecialchars($permit['time_to'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Services Required -->
        <div class="form-section">
            <table class="services-table">
                <thead>
                    <tr>
                        <th colspan="3">SERVICES REQUIRED</th>
                        <th>RATE</th>
                        <th>CHARGE</th>
                    </tr>
                    <tr>
                        <th style="text-align: left;">Name of Services</th>
                        <th>Yes</th>
                        <th>No</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Security Posting -->
                    <tr>
                        <td class="service-name">Security Posting</td>
                        <td>
                            <span class="checkbox-display <?php echo $permit['security_posting'] ? 'checkbox-checked' : ''; ?>">
                                <?php echo $permit['security_posting'] ? '✓' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <span class="checkbox-display <?php echo !$permit['security_posting'] ? 'checkbox-checked' : ''; ?>">
                                <?php echo !$permit['security_posting'] ? '✓' : ''; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($permit['rate_security'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($permit['charge_security'] ?? '-'); ?></td>
                    </tr>
                    <!-- Janitorial Deployment -->
                    <tr>
                        <td class="service-name">Janitorial Deployment</td>
                        <td>
                            <span class="checkbox-display <?php echo $permit['janitorial_deployment'] ? 'checkbox-checked' : ''; ?>">
                                <?php echo $permit['janitorial_deployment'] ? '✓' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <span class="checkbox-display <?php echo !$permit['janitorial_deployment'] ? 'checkbox-checked' : ''; ?>">
                                <?php echo !$permit['janitorial_deployment'] ? '✓' : ''; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($permit['rate_janitorial'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($permit['charge_janitorial'] ?? '-'); ?></td>
                    </tr>
                    <!-- Maintenance -->
                    <tr>
                        <td class="service-name">Maintenance</td>
                        <td>
                            <span class="checkbox-display <?php echo $permit['maintenance'] ? 'checkbox-checked' : ''; ?>">
                                <?php echo $permit['maintenance'] ? '✓' : ''; ?>
                            </span>
                        </td>
                        <td>
                            <span class="checkbox-display <?php echo !$permit['maintenance'] ? 'checkbox-checked' : ''; ?>">
                                <?php echo !$permit['maintenance'] ? '✓' : ''; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($permit['rate_maintenance'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($permit['charge_maintenance'] ?? '-'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Personnel -->
        <div class="personnel-section">
            <div class="personnel-title">AUTHORIZED PERSONNEL/WORKERS:</div>
            <div class="personnel-list">
                <div class="personnel-column">
                    <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="personnel-row">
                        <div class="personnel-number"><?php echo $i; ?></div>
                        <div class="personnel-display">
                            <?php echo isset($personnel[$i - 1]) ? htmlspecialchars($personnel[$i - 1]) : ''; ?>
                        </div>
                    </div>
                    <?php
endfor; ?>
                </div>
                <div class="personnel-column">
                    <?php for ($i = 11; $i <= 20; $i++): ?>
                    <div class="personnel-row">
                        <div class="personnel-number"><?php echo $i; ?></div>
                        <div class="personnel-display">
                            <?php echo isset($personnel[$i - 1]) ? htmlspecialchars($personnel[$i - 1]) : ''; ?>
                        </div>
                    </div>
                    <?php
endfor; ?>
                </div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="signature-row">
                <!-- Tenant Signature -->
                <div class="signature-box">
                    <div class="signature-display">
                        <?php if (!empty($permit['tenant_signature'])): ?>
                            <img src="data:image/png;base64,<?php echo htmlspecialchars($permit['tenant_signature']); ?>" alt="Tenant Signature">
                        <?php
else: ?>
                            <span class="no-signature">No signature</span>
                        <?php
endif; ?>
                    </div>
                    <div class="signature-title">Tenant/Contractor</div>
                    <div class="signature-subtitle">Signature and Date</div>
                </div>

                <!-- Admin Signature -->
                <div class="signature-box">
                    <div class="signature-display">
                        <?php if (!empty($permit['admin_signature'])): ?>
                            <img src="data:image/png;base64,<?php echo htmlspecialchars($permit['admin_signature']); ?>" alt="Admin Signature">
                        <?php
else: ?>
                            <span class="no-signature">Pending</span>
                        <?php
endif; ?>
                    </div>
                    <div class="signature-title">Approved by:</div>
                    <div class="signature-subtitle">Building Admin</div>
                </div>

                <!-- Guard Signature -->
                <div class="signature-box">
                    <div class="signature-display">
                        <?php if (!empty($permit['guard_signature'])): ?>
                            <img src="data:image/png;base64,<?php echo htmlspecialchars($permit['guard_signature']); ?>" alt="Guard Signature">
                        <?php
else: ?>
                            <span class="no-signature">Pending</span>
                        <?php
endif; ?>
                    </div>
                    <div class="signature-title">Gate Guard</div>
                    <div class="signature-subtitle">Signature and Date</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn" onclick="window.print()">🖨️ PRINT</button>
            <button class="btn btn-back" onclick="window.history.back()">← BACK</button>
        </div>
    </div>
</body>
</html>
